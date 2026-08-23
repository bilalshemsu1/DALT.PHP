# Perform and fix an accessibility audit

We have been writing tests with `getByRole` and `getByLabel` since Lesson 19, which
means our interface has had to be describable all along. That is a good start and not
an audit. We will run an automated check across every screen a person moves through,
fix what it finds, add the check to the gate, and be honest about the barriers no tool
can see.

> **Helpful background:** the [WCAG 2.2 quick reference](https://www.w3.org/WAI/WCAG22/quickref/)
> is the standard the checks below are drawn from.

## Add the checker to the suite we already have

Our Playwright suite drives a real browser against the real application, which is
exactly what an accessibility scan needs — the accessibility tree only exists in a
browser.

```bash
npm install --save-dev @axe-core/playwright@4.11.2
```

`tests/browser/accessibility.spec.ts` scans a page and reports every violation at once:

```ts
async function scan(page: Page): Promise<void> {
  const results = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze()

  const summary = results.violations.map((violation) => ({
    id: violation.id,
    impact: violation.impact,
    help: violation.help,
    nodes: violation.nodes.map((node) => node.target.join(' ')),
  }))

  expect(summary, JSON.stringify(summary, null, 2)).toEqual([])
}
```

The summary matters more than it looks. Asserting `violations.length === 0` produces
"expected 3 to be 0", which tells you nothing. Mapping to id, impact, help, and the
offending selector means the failure message *is* the report.

## Audit the screens people actually move through

Not "the site" — the specific states:

```ts
test('the login and registration screens are accessible', …)
test('the dashboard and workspace screens are accessible', …)
test('the issue list, its filters, and an issue are accessible', …)
test('error and empty states are accessible', …)
```

Two of those deserve explanation.

A filtered list is a different screen for a screen reader, so it gets its own scan:

```ts
// A filtered result is a different screen for a screen reader: different content,
// possibly an empty state.
await page.goto(`${projectUrl()}?q=timeout`)
```

And error states are where accessibility is usually worst, because they are written
last and looked at least.

## What the audit found

Our application screens came back clean — the role and label discipline our tests have
been enforcing since Lesson 19 paid for itself here. One page did not:

```text
- heading "404" [level=1]
- paragraph: Not Found
```

That is the entire document DALT returns when a URL matches no route. No `<html lang>`,
no `<title>`, no landmark, and — the part that matters most — no way to get anywhere
else. Someone who mistypes a URL, or follows a stale link, lands on a page with nothing
to do.

Fix it in `framework/Core/ExceptionHandler.php`:

```php
// A complete document, not a fragment. The previous version emitted a bare
// <h1> and <p>: no language, no title, no landmark, and no way back — which is
// a dead end for anyone using a screen reader or a keyboard.
return Response::html(sprintf(
    '<!doctype html><html lang="en"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<title>%1$d · %2$s</title></head><body>'
    . '<main><h1>%1$d</h1><p>%2$s</p>'
    . '<p><a href="/">Return to the application</a></p>'
    . '</main></body></html>',
    $status,
    $this->escape($message),
), $status);
```

`lang="en"` lets a screen reader choose the right pronunciation. `<title>` is what a tab
and a browser-history entry say. `<main>` gives a skip target. The link is the way out.
The viewport meta is what lets the page reflow at 400% zoom instead of forcing
horizontal scrolling.

The test now asserts the properties rather than the page:

```ts
// A URL the server does not recognise at all, so DALT answers rather than React.
// This page has to be a complete, navigable document in its own right.
await expect(page.getByRole('heading', { name: '404', level: 1 })).toBeVisible()
await expect(page.getByRole('main')).toBeVisible()
await expect(page.getByRole('link', { name: 'Return to the application' })).toBeVisible()
await scan(page)
```

## Four framework tests will fail, and should

Changing that page broke four tests that asserted its exact bytes:

```text
⨯ the front controller renders an http exception as a response
⨯ production error responses do not disclose server exception details
⨯ http exceptions preserve client errors and hide production server details
```

They were over-specified: they pinned a whole string when they cared about three facts.
Rewrite them to assert what they actually mean:

```php
// The error page is a complete document now, so the assertion checks the
// parts that matter rather than an exact byte string.
->and($response->body)->toContain('<h1>404</h1>')
->and($response->body)->toContain('<p>Not Found</p>')
->and($response->body)->toContain('<main>')
->and($response->body)->toContain('<html lang="en">');
```

And one of them gains an assertion it should always have had:

```php
->and($unavailable->content())->toContain('<p>Internal Server Error</p>')
// The important half: the real message never reaches the page.
->and($unavailable->content())->not->toContain('Database host is secret');
```

A brittle test that breaks on a good change is a cost. A test that asserts the *reason*
survives the change and keeps protecting the behaviour.

## Check the keyboard, because axe cannot

Automation cannot tell you whether every control can be reached without a mouse. That
one is mechanical enough to test directly:

```ts
// Tab through the page and record what receives focus. A control that never appears
// here cannot be operated without a mouse.
const focused = new Set<string>()
for (let step = 0; step < 40; step++) {
  await page.keyboard.press('Tab')
  const description = await page.evaluate(() => {
    const element = document.activeElement
    if (!element || element === document.body) {
      return null
    }

    return `${element.tagName.toLowerCase()}:${
      element.getAttribute('aria-label') ?? element.textContent?.trim().slice(0, 30) ?? ''
    }`
  })
  if (description !== null) {
    focused.add(description)
  }
})

expect(reached.toLowerCase()).toContain('apply filters')
expect(reached.toLowerCase()).toContain('clear')
```

The filter bar on our issue list is the right place for this: it is the densest
collection of controls in the application, and the easiest place to accidentally build
something only a mouse can use.

## Prove the scan can fail

A clean scan on the first run is exactly when to distrust it. Remove one label:

```tsx
<label className="mb-2 block text-sm font-bold" htmlFor="email">Email</label>
```

Rebuild, rerun, and the report names it:

```text
"id": "label",
"help": "Form elements must have labels",
1 failed
```

Put it back. Now the green result means something.

## What automation still cannot tell us

Be precise about this rather than implying a clean run means an accessible application.
Automated rules catch a minority of real barriers — roughly the mechanical ones. These
were checked by hand, and would need checking again after any significant interface
change:

```text
Focus placement      After deleting an issue, focus lands somewhere sensible rather
                     than being lost to <body>. RouteProblemPage focuses its heading
                     on mount, which is why a client-side refusal is announced.
Announcements        The loading, empty, and error text uses role="status" and
                     role="alert" — a tool can see the role, only a person can judge
                     whether the sentence is useful when read aloud.
Heading order        Every screen starts at h1 and descends without skipping; axe
                     checks order within a page but not whether the outline makes sense.
Reading order        The visual order and the DOM order agree, so tabbing does not
                     jump around the screen.
Contrast in states   axe measured our resting colours. Hover, focus, and disabled
                     states were checked by eye.
Zoom and reflow      At 400% zoom the layout reflows to a single column with no
                     horizontal scrolling.
Motion               We use no animation, so there is nothing for a reduced-motion
                     preference to suppress. Adding one later means adding that
                     preference at the same time.
```

That list is part of the deliverable. "We ran axe and it was green" is a claim about a
tool; the list above is a claim about the application, and it says who made it.

## Run the gate

The accessibility spec lives in the browser suite, so it is already part of
`test:browser`, and a focused script exists for iterating:

```json
"test:a11y": "playwright test tests/browser/accessibility.spec.ts"
```

```bash
./scripts/ci-gate.sh
```

```text
Tests:  1 skipped, 345 passed (1077 assertions)
Tests   47 passed (47)
12 passed (33.2s)
All release checks passed.
```

Twelve browser tests now: seven journeys and five accessibility scans, over
authentication, the dashboard, workspaces, members, the issue list, filters, an issue,
both kinds of error page, an empty result, and the keyboard path through the filter bar.

Next we measure what the application costs before trying to make it faster.
