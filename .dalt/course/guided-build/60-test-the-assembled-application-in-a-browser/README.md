# Test the assembled application in a browser

Every test we have written so far looks at one layer. Component tests render React
against a mocked network; PHP tests call our routes with a synthetic request. Neither
has ever opened our application. We will add Playwright journeys that drive a real
browser against a real DALT server and a real PostgreSQL database, and cover the paths
where a person actually gets stuck.

> **Helpful background:** Playwright's [locator guide](https://playwright.dev/docs/locators)
> explains why role- and label-based selectors are more durable than CSS ones.

## Install the runner and its browser

```bash
npm install --save-dev @playwright/test@1.62.1
npx playwright install chromium
```

The second command downloads a browser build pinned to this Playwright version. That
pinning is the point: a browser test that silently follows whatever Chromium the
machine happens to have is a test whose failures nobody can reproduce.

## Give the browser its own database

Browser tests are slow and awkward to debug, so they must never begin from "whatever
the last run left behind". They also must never touch the database we are developing
against.

We already have a safe test database from Lesson 39 — `PostgresTestDatabase` creates
`dalt_issue_tracker_test`, refuses any name that does not end in `_test`, and rebuilds
the schema from our real migrations. We can reuse all of it.

Create `scripts/prepare-browser-database.php`:

```php
$database = PostgresTestDatabase::fresh();
$factory = new Factory($database);

$owner = $factory->user([
    'name' => 'Ada Lovelace',
    'email' => 'ada@example.test',
    'password' => 'correct-horse-battery',
]);
$member = $factory->user([
    'name' => 'Grace Hopper',
    'email' => 'grace@example.test',
    'password' => 'correct-horse-battery',
]);

$workspace = $factory->ownedWorkspace($owner, ['name' => 'Studio']);
$factory->membership($workspace['id'], $member, 'member');
$project = $factory->project($workspace['id'], ['name' => 'Launch']);
```

The factories from Lesson 59 are doing real work here. Because they hash passwords
properly, the accounts they create can actually log in through the browser — and
because they write through the same schema, a browser journey and a PHP test are
looking at the same kind of data.

Twelve issues, so that a second page exists and is not empty:

```php
for ($n = 1; $n <= 12; $n++) {
    $issue = $factory->issue($project, [
        'title' => $n === 1 ? 'Timeout on the export job' : "Routine issue {$n}",
        'status' => $n % 4 === 0 ? 'closed' : 'open',
        'assignee_id' => $n === 1 ? $member : null,
        'priority' => $n === 1 ? 'urgent' : 'medium',
    ]);
    // …the first issue also gets a label, a comment, and an activity entry…
}
```

Then the piece a browser test cannot invent for itself. Lesson 45 stores only a hash of
an invitation token, so the script writes the plaintext token — and the ids it created
— where the specs can read them:

```php
file_put_contents(
    BASE_PATH . 'tests/browser/.fixtures.json',
    json_encode([
        'workspaceId' => $workspace['id'],
        'projectId' => $project,
        'invitationToken' => $invitation['token'],
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n",
);
```

That file is generated output, so add it and Playwright's own artifacts to
`.gitignore`:

```text
/test-results
/playwright-report
/tests/browser/.fixtures.json
```

## Point the test server at the test database

Create `playwright.config.ts`:

```ts
const port = 8123
const baseURL = `http://127.0.0.1:${port}`

export default defineConfig({
  testDir: './tests/browser',
  workers: 1,
  fullyParallel: false,
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  globalSetup: './tests/browser/global-setup.ts',
  webServer: {
    command: `DB_NAME=dalt_issue_tracker_test php artisan serve 127.0.0.1 ${port}`,
    url: baseURL,
    reuseExistingServer: false,
    timeout: 30_000,
  },
})
```

Four decisions there are worth stating.

`DB_NAME` is exported on the command rather than edited into `.env`. Our bootstrap
calls `Dotenv::createImmutable(...)->safeLoad()`, and *immutable* means a variable
already present in the environment wins. So the browser server reads the test database
and the file we develop against is never opened. Check it yourself:

```bash
DB_NAME=dalt_issue_tracker_test php scripts/database-status.php
```

```text
Driver: pgsql
Database: dalt_issue_tracker_test
```

`workers: 1` because every journey shares one seeded database. Parallel browser tests
that mutate shared rows fail in ways that depend on timing, and a flaky test teaches
people to ignore red.

`reuseExistingServer: false` so a run never quietly attaches to a development server
that is pointed at the wrong database.

`trace: 'retain-on-failure'` and `screenshot: 'only-on-failure'` because we will not be
watching. A failure we did not see needs evidence, and Playwright's trace viewer
replays the whole run:

```bash
npx playwright show-trace test-results/<the-failing-test>/trace.zip
```

The global setup simply runs our PHP script:

```ts
export default function globalSetup(): void {
  const output = execFileSync('php', ['scripts/prepare-browser-database.php'], {
    encoding: 'utf8',
  })

  process.stdout.write(output)
}
```

## Share the seeded ids with a small helper

Create `tests/browser/fixtures.ts`:

```ts
// __dirname rather than import.meta.url: Playwright compiles these files to
// CommonJS unless the package declares ES modules, and this project does not.
export const seeded: SeededState = JSON.parse(
  readFileSync(join(__dirname, '.fixtures.json'), 'utf8'),
) as SeededState

export async function logIn(page: Page, email: string): Promise<void> {
  await page.goto('/login')
  await page.getByLabel('Email').fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Log in' }).click()
  await expect(page.getByRole('navigation', { name: 'Primary' })).toBeVisible()
}
```

`logIn` ends on an assertion rather than a click. Returning as soon as the button is
pressed would let the next step race the redirect; waiting for the signed-in navigation
means the helper only returns once the application really is logged in.

Every selector here is a role or a label — `getByLabel('Email')`,
`getByRole('button', { name: 'Log in' })`. Those are the names a screen reader
announces, so a test written this way also fails when the interface becomes
unusable for someone navigating by keyboard.

## Journey one: getting in and staying in

`tests/browser/auth.spec.ts` covers the identity boundary end to end:

```ts
test('a guest is sent to the login page and can register a new account', async ({ page }) => {
  await page.goto('/dashboard')
  await expect(page).toHaveURL(/\/login$/)
  // …registration…
  await expect(page.getByRole('heading', { name: 'Dashboard', level: 1 })).toBeVisible()
  await expect(page.getByText('Join or create a workspace to begin.')).toBeVisible()
  await expect(page.getByRole('region', { name: 'Assigned to me' }))
    .toContainText('Nothing needs attention here.')
})
```

That last pair of assertions is Lesson 58 being checked by something other than Lesson
58. A brand new account must reach an intentionally empty dashboard, not a blank one.

Then the deep link, which is the whole reason Lesson 12 made DALT serve the React shell
for React-owned URLs:

```ts
await page.goto(projectUrl())
await expect(page.getByRole('heading', { name: 'Launch', level: 1 })).toBeVisible()

await page.reload()
await expect(page.getByRole('heading', { name: 'Launch', level: 1 })).toBeVisible()
// Newest first, ten per page: issue 12 is the first row on page one.
await expect(page.getByRole('heading', { name: 'Routine issue 12', level: 3 })).toBeVisible()
```

A pasted link and a refresh are the same request as far as our server is concerned, and
until now nothing proved they worked.

Logging out has to remove the session, not merely the buttons:

```ts
await page.getByRole('button', { name: /log out/i }).click()
await expect(page).toHaveURL(/\/login$/)

// The session is really gone, not merely hidden by the interface.
await page.goto(projectUrl())
await expect(page).toHaveURL(/\/login$/)
```

And a session that ends somewhere else — another tab, an expiry — must stop the
application at its next request:

```ts
await context.clearCookies()
await page.goto(`/workspaces/${seeded.workspaceId}`)
await expect(page).toHaveURL(/\/login$/)
```

## Journey two: two people, one workspace

`tests/browser/collaboration.spec.ts` starts with the flow that spans two accounts and
an identity boundary — the one no single-user test could ever cover:

```ts
await page.goto(`/invitations/${seeded.invitationToken}`)
await expect(page.getByRole('heading', { name: 'Join Studio', level: 1 })).toBeVisible()
await expect(page.getByText('This invitation grants the member role to alan@example.test.'))
  .toBeVisible()

await page.getByRole('link', { name: 'Create account' }).click()
// …register as alan@example.test…

await expect(page).toHaveURL(new RegExp(`/invitations/${seeded.invitationToken}$`))
await page.getByRole('button', { name: /accept/i }).click()

await expect(page).toHaveURL(new RegExp(`/workspaces/${seeded.workspaceId}$`))
await expect(page.getByRole('heading', { name: 'Studio', level: 1 })).toBeVisible()
```

Read the middle assertion carefully. After registering, the browser comes **back** to
the invitation. That is the destination-preservation Lesson 46 built, and this is the
first time anything has actually walked through it.

Next, permission from the user's side. Lesson 59's matrix proved the API refuses a
member; this proves the interface and the server agree:

```ts
// React hides what a member may not do…
await expect(page.getByRole('link', { name: 'Edit workspace' })).toHaveCount(0)
await expect(page.getByRole('link', { name: 'Delete workspace' })).toHaveCount(0)

// Typing the owner-only URL directly reaches the same refusal.
await page.goto(`/workspaces/${seeded.workspaceId}/edit`)
await expect(page.getByRole('heading', { name: '403', level: 1 })).toBeVisible()
await expect(page.getByText('Forbidden')).toBeVisible()
```

Both halves matter. A hidden button is a courtesy; the 403 is the boundary.

Then the ordinary work a member came to do — find an issue, read its history, add to it:

```ts
await page.getByLabel('Search').fill('timeout')
await page.getByRole('button', { name: 'Apply filters' }).click()

await page.getByRole('link', { name: /Timeout on the export job/ }).click()
await expect(page.getByText('Reproduced on staging.')).toBeVisible()

await page.getByLabel('Add a comment').fill('Taking a look this afternoon.')
await page.getByRole('button', { name: 'Add comment' }).click()

await expect(page.getByText('Taking a look this afternoon.')).toBeVisible()
await expect(page.getByText('Reproduced on staging.')).toBeVisible()
```

The last line is deliberate. Asserting only the new comment would pass if posting had
replaced the conversation instead of extending it.

Finally, filtering and pagination as a person experiences them:

```ts
const rows = page.getByRole('region', { name: 'Issues' }).getByRole('listitem')
await expect(rows).toHaveCount(10)

// Page two holds the remaining two of twelve.
await page.goto(`${projectUrl()}?page=2`)
await expect(rows).toHaveCount(2)

// A refreshed page keeps the same view, because the page number lives in the URL.
await page.reload()
await expect(rows).toHaveCount(2)
```

## One habit that will bite you

Our first version of the closed-status check read the count directly:

```ts
const closedCount = await rows.count()
expect(closedCount).toBeGreaterThan(0)   // Received: 0
```

It failed against a page that was rendering three closed issues perfectly well.
`count()` reads the DOM once, with no waiting, and it ran before React had replaced the
list. `expect(locator)` assertions retry until the timeout; plain reads do not:

```ts
// Three of the twelve seeded issues are closed. `count()` reads the DOM once with
// no waiting, so the assertion has to be the expectation, not a bare read.
await page.goto(`${projectUrl()}?status=closed`)
await expect(rows).toHaveCount(3)
```

If a browser test is ever flaky, look for a value read outside an `expect` first.

## Typecheck the tests we just wrote

Our `tsconfig.json` includes only `resources`, and eslint is scoped there too — both
deliberately, because they are configured for the Vite application. That leaves the
specs unchecked, and Playwright transpiles without type checking, so a mistake would
only appear as a confusing runtime failure.

Add `tsconfig.browser-tests.json`:

```json
{
  "extends": "./tsconfig.json",
  "compilerOptions": {
    "types": ["node", "@playwright/test"],
    "module": "CommonJS",
    "moduleResolution": "node"
  },
  "include": ["tests/browser", "playwright.config.ts"]
}
```

And two scripts in `package.json`:

```json
"test:browser": "playwright test",
"typecheck:browser": "tsc --noEmit -p tsconfig.browser-tests.json"
```

## Prove the journeys can fail

A green browser suite is worth nothing until we have watched it go red for a real
reason. Break the owner-only capability check in `app/Http/WorkspaceAccess.php` so it
compares against a role that never exists:

```php
if (in_array($capability, $ownerOnly, true) && $workspace['role'] === 'nobody') {
    abort(403);
}
```

Every PHP unit remains happy about its own layer, and the browser suite reports:

```text
✓  1 a second person accepts an invitation and joins the workspace (1.8s)
✘  2 a member can collaborate but cannot administer the workspace (6.7s)
✓  3 a member assigns an issue to themselves and comments on it (2.1s)
✓  4 filters narrow the list and pagination keeps its place in the URL (2.8s)
```

One test, the right one, with a screenshot and a trace of the member reaching a page
they should never have seen. Put the check back.

## Run the whole gate

```bash
npm run typecheck
npm run typecheck:browser
npm run lint
npm test
npm run test:browser
npm run build
php vendor/bin/pest tests/Feature/PolicyMatrixTest.php tests/Feature/DataIntegrityTest.php
```

Seven browser journeys pass in about sixteen seconds, all 42 component tests pass, both
type checks and eslint are clean, the 33 focused PHP tests pass with 116 assertions,
and Vite produces the production bundle.

We now have three layers of proof that do different jobs: component tests for narrow
interface edge cases, PHP integration tests for the API contract and its authorization
matrix, and a handful of browser journeys that prove the assembled application actually
works for a person. The next batch takes that application and prepares it to ship.
