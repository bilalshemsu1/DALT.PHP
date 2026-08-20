Our issue tracker now has a useful server-rendered path: a workspace contains projects,
and each project contains issues we can create, open, close, edit, and delete. We will
not throw that working backend away. We will move one screen—the project screen—to
React while DALT continues to own its URL, database query, validation, session flash,
and form submission.

This is a migration, not a restart. The page will still work at the same URL and its
links and form will still use normal HTTP requests. React will first take responsibility
for rendering. In the next lesson, we can give it a JSON endpoint because we will have
a stable frontend boundary to connect.

## Make the existing frontend toolchain real

The project already describes React, TypeScript, Tailwind, and Vite in `package.json`.
Install those dependencies now:

```bash
npm install
```

`npm install` may report package audit advisories as the ecosystem changes. Record them,
but do not run a forced audit fix during a feature change: a forced upgrade can change
major versions and make the lesson's code unreliable.

Open `vite.config.mjs`. In the `build.rollupOptions` block, change the input from the old
JavaScript file to the TypeScript React entry point we are about to create:

```js
build: {
  manifest: true,
  outDir: resolve(__dirname, 'public/build'),
  emptyOutDir: true,
  rollupOptions: {
    input: resolve(__dirname, 'resources/app/main.tsx')
  }
}
```

Vite will now begin at `main.tsx`, follow its imports, transform JSX, and write the
browser bundle to `public/build`. Delete `resources/js/app.js`; keeping two entry points
would leave us wondering which one owns the screen.

## Give Tailwind our application colors

Open `resources/css/input.css`. Keep the existing Tailwind import and the line that stops
Tailwind scanning the separate `.dalt` learning platform. Add the palette and base layer:

```css
@import "tailwindcss";

@source not "../../.dalt";

@theme {
  --color-canvas: #f7f8fa;
  --color-surface: #ffffff;
  --color-line: #dfe3e8;
  --color-line-strong: #c8ced6;
  --color-ink: #17202a;
  --color-muted: #52606d;
  --color-accent: #087f5b;
  --color-accent-dark: #066c4d;
  --color-accent-soft: #dff7ed;
}

@layer base {
  :root {
    color-scheme: light;
    font-family: ui-sans-serif, system-ui, -apple-system,
      BlinkMacSystemFont, "Segoe UI", sans-serif;
  }

  body {
    min-height: 100vh;
    margin: 0;
    background: var(--color-canvas);
    color: var(--color-ink);
  }
}
```

Names such as `bg-canvas`, `border-line`, and `text-accent` will now refer to this small
product palette. Most styling can live beside the element it affects while the shared
visual decisions remain in one place.

## Pass server data across a deliberate boundary

React cannot read PHP arrays. PHP can, however, encode the values as JSON. Replace
`resources/views/projects/show.view.php` with a thin document shell. Start by translating
the controller and session values into a frontend-shaped array:

```php
<!doctype html>
<?php
$workspaceId = (int) ($workspace['id'] ?? 0);
$workspaceName = (string) ($workspace['name'] ?? '');
$projectId = (int) ($project['id'] ?? 0);
$projectName = (string) ($project['name'] ?? '');
$errors = Core\Session::get('errors', []);
$errors = is_array($errors) ? $errors : [];
$success = Core\Session::get('success');
$success = is_string($success) ? $success : null;
$oldTitle = old('title');
$oldTitle = is_string($oldTitle) ? $oldTitle : '';
$oldDescription = old('description');
$oldDescription = is_string($oldDescription) ? $oldDescription : '';

$projectPageData = [
    'workspace' => ['id' => $workspaceId, 'name' => $workspaceName],
    'project' => ['id' => $projectId, 'name' => $projectName],
    'issues' => array_map(
        static fn (array $issue): array => [
            'id' => (int) ($issue['id'] ?? 0),
            'title' => (string) ($issue['title'] ?? ''),
            'description' => (string) ($issue['description'] ?? ''),
            'status' => (string) ($issue['status'] ?? ''),
        ],
        $issues,
    ),
    'form' => [
        'csrfToken' => csrf_token(),
        'old' => ['title' => $oldTitle, 'description' => $oldDescription],
        'errors' => [
            'title' => is_string($errors['title'] ?? null) ? $errors['title'] : null,
            'description' => is_string($errors['description'] ?? null)
                ? $errors['description']
                : null,
        ],
    ],
    'success' => $success,
];

$projectPageJson = json_encode(
    $projectPageData,
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
);
?>
```

We select the exact fields the screen needs rather than exposing database rows by
accident. The `JSON_HEX_*` flags also prevent user text containing HTML characters from
ending the script element early. React will still render that text as text, not markup.

Finish the view with the mount point and data element:

```php
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?= htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8') ?> · DALT Issues</title>
  <?= vite('resources/app/main.tsx') ?>
</head>
<body>
  <div id="root"></div>
  <script id="project-page-data" type="application/json"><?= $projectPageJson ?></script>
  <noscript>This project screen requires JavaScript. Enable it and reload the page.</noscript>
</body>
</html>
```

`vite()` reads the development server or production manifest and adds the correct assets.
The JSON script is data, not executable JavaScript. The empty `root` element is the only
part React owns.

## Do not trust JSON just because TypeScript exists

Create `resources/app/project-page-data.ts`. First describe the shape our component may
use:

```ts
export type IssueStatus = 'open' | 'closed'

export type Issue = {
  id: number
  title: string
  description: string
  status: IssueStatus
}

export type ProjectPageData = {
  workspace: { id: number; name: string }
  project: { id: number; name: string }
  issues: Issue[]
  form: {
    csrfToken: string
    old: { title: string; description: string }
    errors: { title?: string; description?: string }
  }
  success: string | null
}
```

Types check our own code, but `JSON.parse()` returns an outside value. Add small runtime
guards below the types:

```ts
function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function recordAt(record: Record<string, unknown>, key: string) {
  const value = record[key]
  if (!isRecord(value)) throw new Error(`Project page data is missing ${key}.`)
  return value
}

function stringAt(record: Record<string, unknown>, key: string): string {
  const value = record[key]
  if (typeof value !== 'string') throw new Error(`Invalid ${key}.`)
  return value
}

function integerAt(record: Record<string, unknown>, key: string): number {
  const value = record[key]
  if (typeof value !== 'number' || !Number.isInteger(value) || value < 1) {
    throw new Error(`Invalid ${key}.`)
  }
  return value
}

function optionalError(record: Record<string, unknown>, key: string) {
  const value = record[key]
  if (value === undefined || value === null) return undefined
  if (typeof value !== 'string') throw new Error(`Invalid ${key} error.`)
  return value
}
```

Now parse one issue and the complete page:

```ts
function parseIssue(value: unknown): Issue {
  if (!isRecord(value)) throw new Error('Invalid issue.')
  const status = stringAt(value, 'status')
  if (status !== 'open' && status !== 'closed') throw new Error('Invalid issue status.')

  return {
    id: integerAt(value, 'id'),
    title: stringAt(value, 'title'),
    description: stringAt(value, 'description'),
    status,
  }
}

export function parseProjectPageData(value: unknown): ProjectPageData {
  if (!isRecord(value)) throw new Error('Project page data must be an object.')
  const workspace = recordAt(value, 'workspace')
  const project = recordAt(value, 'project')
  const form = recordAt(value, 'form')
  const old = recordAt(form, 'old')
  const errors = recordAt(form, 'errors')

  if (!Array.isArray(value.issues)) throw new Error('Invalid issues list.')
  if (value.success !== null && typeof value.success !== 'string') {
    throw new Error('Invalid success message.')
  }

  return {
    workspace: { id: integerAt(workspace, 'id'), name: stringAt(workspace, 'name') },
    project: { id: integerAt(project, 'id'), name: stringAt(project, 'name') },
    issues: value.issues.map(parseIssue),
    form: {
      csrfToken: stringAt(form, 'csrfToken'),
      old: { title: stringAt(old, 'title'), description: stringAt(old, 'description') },
      errors: {
        title: optionalError(errors, 'title'),
        description: optionalError(errors, 'description'),
      },
    },
    success: value.success,
  }
}

export function readProjectPageData(): ProjectPageData {
  const source = document.getElementById('project-page-data')
  if (!(source instanceof HTMLScriptElement)) throw new Error('Project page data was not found.')

  let value: unknown
  try {
    value = JSON.parse(source.textContent ?? '')
  } catch {
    throw new Error('Project page data is not valid JSON.')
  }

  return parseProjectPageData(value)
}
```

This boundary is deliberately stricter than a type assertion. If the backend later sends
an impossible status or omits a name, the screen fails visibly instead of allowing bad
data to spread through the component tree.

## Render the project as components

Create `resources/app/ProjectPage.tsx`. Import the types and begin with one issue row:

```tsx
import type { Issue, ProjectPageData } from './project-page-data'

function IssueRow({ issue, workspaceId, projectId }: {
  issue: Issue
  workspaceId: number
  projectId: number
}) {
  const isClosed = issue.status === 'closed'

  return (
    <li className="min-w-0 border-b border-line">
      <a
        className="block rounded-lg py-5 ps-1 pe-2 text-ink no-underline hover:bg-[#f0f3f5] focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-[#78d6b7]"
        href={`/workspaces/${workspaceId}/projects/${projectId}/issues/${issue.id}`}
      >
        <div className="flex items-start justify-between gap-4">
          <h3 className="min-w-0 break-words text-[15px] leading-6 font-bold">{issue.title}</h3>
          <span className={isClosed
            ? 'shrink-0 rounded-full bg-[#e9ecef] px-2 py-1 text-[11px] font-extrabold text-[#46515c]'
            : 'shrink-0 rounded-full bg-accent-soft px-2 py-1 text-[11px] font-extrabold text-[#075b43]'}>
            {isClosed ? 'Closed' : 'Open'}
          </span>
        </div>
        {issue.description !== '' && (
          <p className="mt-2 max-w-[68ch] whitespace-pre-wrap break-words text-[13px] leading-6 text-muted">
            {issue.description}
          </p>
        )}
      </a>
    </li>
  )
}
```

The anchor remains an anchor. React does not require client-side routing, and preserving
normal links keeps this first migration small and dependable.

Add the create form. It also remains a normal protected POST, including the session values
and accessible error relationships we already built:

```tsx
function CreateIssueForm({ data }: { data: ProjectPageData }) {
  const { workspace, project, form } = data

  return (
    <section className="rounded-[14px] border border-line bg-surface p-[22px]" aria-labelledby="create-issue-title">
      <h2 id="create-issue-title" className="text-lg font-bold">Create an issue</h2>
      <p className="mt-2 mb-5 text-sm leading-6 text-muted">Capture one clear piece of work for this project.</p>
      <form method="POST" action={`/workspaces/${workspace.id}/projects/${project.id}/issues`}>
        <input type="hidden" name="_token" value={form.csrfToken} />

        <label className="mb-2 block text-sm font-bold" htmlFor="issue-title">Title</label>
        <input
          className="min-h-11 w-full rounded-[9px] border border-line-strong px-3 py-2.5 text-base focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#78d6b7] aria-[invalid=true]:border-[#b42318]"
          id="issue-title" name="title" type="text" defaultValue={form.old.title}
          minLength={2} maxLength={100} required
          aria-invalid={form.errors.title !== undefined}
          aria-describedby={form.errors.title === undefined ? undefined : 'issue-title-error'}
        />
        {form.errors.title !== undefined && (
          <p id="issue-title-error" className="mt-2 text-[13px] text-[#a51d14]" role="alert">{form.errors.title}</p>
        )}

        <label className="mt-4 mb-2 block text-sm font-bold" htmlFor="issue-description">
          Description <span className="text-xs font-normal text-muted">(optional)</span>
        </label>
        <textarea
          className="min-h-[116px] w-full resize-y rounded-[9px] border border-line-strong px-3 py-2.5 text-base leading-6 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-[#78d6b7] aria-[invalid=true]:border-[#b42318]"
          id="issue-description" name="description" defaultValue={form.old.description}
          maxLength={1000} aria-invalid={form.errors.description !== undefined}
        />
        {form.errors.description !== undefined && (
          <p className="mt-1.5 text-xs text-[#a51d14]" role="alert">{form.errors.description}</p>
        )}

        <button className="mt-[18px] min-h-11 w-full rounded-[9px] bg-accent px-3.5 py-2.5 font-bold text-white hover:bg-accent-dark focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-[#78d6b7]" type="submit">
          Create issue
        </button>
      </form>
    </section>
  )
}
```

Finish the file with the page component. This is the screen-level composition: product
header, project context, flash message, issue list or empty state, and creation form.

```tsx
export function ProjectPage({ data }: { data: ProjectPageData }) {
  const { workspace, project, issues, success } = data

  return (
    <>
      <header className="border-b border-line bg-surface">
        <div className="mx-auto flex min-h-16 w-[calc(100%_-_40px)] max-w-[960px] items-center justify-between gap-5 max-sm:w-[calc(100%_-_32px)]">
          <a className="inline-flex items-center gap-2.5 font-bold text-ink no-underline" href="/">
            <span className="h-6 w-2.5 rounded-[3px] bg-accent" aria-hidden="true" /> DALT Issues
          </a>
          <span className="text-[13px] text-muted">Local development</span>
        </div>
      </header>

      <main className="mx-auto w-[calc(100%_-_40px)] max-w-[960px] py-14 max-sm:w-[calc(100%_-_32px)] sm:py-[72px]">
        <a className="text-sm font-semibold text-muted no-underline hover:text-accent" href={`/workspaces/${workspace.id}`}>
          Back to {workspace.name}
        </a>
        <header className="mt-10 border-b border-line-strong pb-7">
          <h1 className="max-w-[760px] break-words text-[clamp(36px,6vw,56px)] leading-[1.02] font-bold tracking-[-0.04em]">{project.name}</h1>
          <p className="mt-4 max-w-[620px] leading-7 text-muted">Track the work, decisions, and progress for this project through issues.</p>
        </header>

        {success !== null && <p className="mt-6 rounded-[10px] bg-accent-soft px-3.5 py-3 text-sm font-semibold text-[#075b43]" role="status">{success}</p>}

        <div className="mt-12 grid items-start gap-7 sm:grid-cols-[minmax(0,1fr)_340px]">
          <section aria-labelledby="issues-title">
            <div className="flex items-end justify-between gap-5">
              <h2 id="issues-title" className="text-[22px] font-bold">Issues</h2>
              <span className="text-[13px] font-semibold text-muted">{issues.length} {issues.length === 1 ? 'issue' : 'issues'}</span>
            </div>
            {issues.length === 0 ? (
              <div className="mt-[18px] rounded-[14px] border border-dashed border-line-strong bg-surface px-5 py-10 text-center">
                <h3 className="text-lg font-bold">No issues yet</h3>
                <p className="mt-2.5 text-sm text-muted">Create the first issue to capture a concrete piece of work.</p>
              </div>
            ) : (
              <ol className="mt-[18px] list-none border-t border-line-strong p-0">
                {issues.map((issue) => <IssueRow key={issue.id} issue={issue} workspaceId={workspace.id} projectId={project.id} />)}
              </ol>
            )}
          </section>
          <CreateIssueForm data={data} />
        </div>
      </main>
    </>
  )
}
```

On smaller screens the grid becomes one column automatically. Long names and descriptions
wrap instead of widening the page, and every interactive element keeps a visible keyboard
focus. These are part of the feature, not a later polish pass.

## Mount exactly one React root

Create `resources/app/main.tsx`:

```tsx
import { StrictMode } from 'react'
import type { ReactNode } from 'react'
import { createRoot } from 'react-dom/client'
import '../css/input.css'
import { ProjectPage } from './ProjectPage'
import { readProjectPageData } from './project-page-data'

const root = document.getElementById('root')
if (!(root instanceof HTMLElement)) throw new Error('React root was not found.')

let projectScreen: ReactNode

try {
  projectScreen = <ProjectPage data={readProjectPageData()} />
} catch {
  projectScreen = (
    <main className="mx-auto w-[calc(100%_-_32px)] max-w-[680px] py-20 text-ink" role="alert">
      <h1 className="text-3xl font-bold">The project screen could not start</h1>
      <p className="mt-4 leading-7 text-muted">The page received data it could not safely understand. Reload the page. If the problem continues, check the server output.</p>
      <button className="mt-6 min-h-11 rounded-[9px] bg-accent px-5 py-2.5 font-bold text-white" type="button" onClick={() => window.location.reload()}>Reload project</button>
    </main>
  )
}

createRoot(root).render(<StrictMode>{projectScreen}</StrictMode>)
```

One root owns one region. `StrictMode` helps expose unsafe component behavior during
development. If the server/frontend contract is broken, the learner gets a useful recovery
screen instead of a blank page.

## Prove the migration

Run the static checks and production build:

```bash
npm run typecheck
npm run lint
npm run build
```

Vite transpiles TypeScript, but it does not perform type checking for us; that is why the
first command remains separate. All three commands should exit successfully.

Start DALT and open the existing project URL:

```bash
php artisan serve
```

Check the behavior, not only the appearance:

1. The project, issue count, existing issue links, and status labels render.
2. Submit an invalid title. The request returns through DALT and React renders the error
   with the attempted value preserved.
3. Create a valid issue. The redirect shows it in the list and the success message clears
   after a refresh.
4. Follow an issue link and the workspace link. Both still use their existing server URLs.
5. Narrow the browser. The form moves below the list without horizontal scrolling.

We now have React rendering a real product screen, but we have not pretended the entire
application is a client-side app. That is the useful checkpoint: the backend behavior is
unchanged, the frontend boundary is explicit, and the next change can be one capability
rather than another rewrite.

If Git is available, save this working point:

```bash
git add vite.config.mjs resources/css/input.css resources/js/app.js \
  resources/app/main.tsx resources/app/ProjectPage.tsx \
  resources/app/project-page-data.ts resources/views/projects/show.view.php
git commit -m "Move project screen to React"
```

The next lesson will stop embedding the issue list in the document and let React read it
from a DALT JSON endpoint. This screen will remain the visible product while the data path
changes underneath it.
