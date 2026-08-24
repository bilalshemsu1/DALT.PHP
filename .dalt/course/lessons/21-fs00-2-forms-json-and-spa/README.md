# FS00.4 — JavaScript enhancement, JSON, and the SPA model

Lesson ID: FS00.4
Lesson format: Concise theory
Part: 00 — Web foundations
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS00.3
Last reviewed: 2026-08-22

## What we will learn

A native form asks the browser to navigate. JavaScript can intercept the same submit
event, send an HTTP request itself, read data from the response, and update the
document already on screen. That is the small mechanism underneath much of the
single-page application experience.

By the end, we can:

- explain exactly what `preventDefault()` changes;
- distinguish form-encoded data, JSON text, and JavaScript values;
- trace an in-page update without confusing it with persistence;
- describe an SPA without pretending the server disappeared.

### JavaScript takes responsibility for the next steps

We can start from an ordinary form that still has useful native structure:

```html
<form id="preview-form" method="post" action="/api/previews">
  <label for="preview-title">Preview title</label>
  <input id="preview-title" name="title" required>
  <button type="submit">Send preview</button>
</form>

<p id="preview-status" aria-live="polite">Nothing sent yet.</p>
```

Then JavaScript listens for submission:

```js
const form = document.querySelector('#preview-form')

form.addEventListener('submit', function (event) {
  event.preventDefault()
  // Our code now decides how to send and display the result.
})
```

`preventDefault()` only cancels the browser's normal submission for this event. It
does not send a request, validate server rules, update the DOM, or save anything.
Those responsibilities now belong to our program.

The form remains valuable if JavaScript fails to load because it still has `method`,
`action`, labels, names, and a submit button. Whether its server route supports that
fallback is an application decision, but keeping meaningful HTML gives us the option.

### Values, JSON text, and HTTP are different layers

JavaScript can read the form and create an ordinary value:

```js
const title = new FormData(form).get('title')
const payload = { title: title }
```

`payload` is a JavaScript object in browser memory. HTTP cannot transmit that object
directly. `JSON.stringify()` turns it into JSON text:

```js
const body = JSON.stringify(payload)
// body is the string: {"title":"Broken login"}
```

Now `fetch()` can send the text and describe its representation with a header:

```js
const response = await fetch(form.action, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: body,
})
```

The resulting request is conceptually:

```http
POST /api/previews HTTP/1.1
Content-Type: application/json

{"title":"Broken login"}
```

The `Content-Type` header is a claim about the body. If we claim JSON but send invalid
JSON, the server cannot safely interpret it. The server must still parse and validate
the body; JSON describes representation, not trustworthiness.

### Data comes back and the current DOM changes

Suppose the server answers with JSON:

```json
{
  "accepted": true,
  "message": "The server received the preview request."
}
```

Our code reads the response body and updates an existing element:

```js
const data = await response.json()
const status = document.querySelector('#preview-status')

status.textContent = data.message
```

The browser did not load a replacement HTML document. One document stayed loaded,
an HTTP exchange carried data, and JavaScript changed its DOM:

```text
submit event
    ↓ prevent the native navigation
POST JSON
    ↓ receive JSON
change an element in the current DOM
```

We should also plan for failure. `fetch()` resolves even when the server returns many
HTTP error statuses, so real code checks `response.ok` before treating the body as a
success:

```js
if (!response.ok) {
  throw new Error(`Request failed with ${response.status}`)
}
```

Later lessons will give loading, failure, and retry states proper UI. Here the key is
that JavaScript owns all of those states once it replaces browser navigation.

### What “single-page application” means

A single-page application, or SPA, keeps an application document loaded while
JavaScript handles many interactions and requests data as needed. “Single page” does
not mean one request, one URL, no backend, or no HTML. It means interactions usually
do not replace the entire document.

```text
traditional interaction: request → response → replace document
SPA-style interaction:    request → response → update current document
```

React will later let us describe the desired interface from state instead of editing
DOM elements one by one. HTTP, JSON, validation, and server responsibility remain.

## Try it

**Workspace:** No workspace copy is needed. Use the Part 00 observation page and the
browser's Network and Elements panels.

1. Open [/learn/fullstack/observe/forms](/learn/fullstack/observe/forms).
2. Open Developer Tools → **Network**, then submit **JavaScript-controlled form**.
3. Inspect the request method, `Content-Type`, JSON payload, response body, and type.
4. Confirm there is no redirect and no following document request.
5. In **Elements**, find `#json-preview-status` and watch its text change on another
   submission.
6. Refresh the page and observe the status text again.
7. Disable JavaScript in Developer Tools if your browser makes that convenient, reload,
   and submit the form once more. Record how responsibility shifts back to the form.

**Expected result:** with JavaScript enabled, one POST carries JSON, one JSON response
returns, the address stays unchanged, and the existing status element changes. A
refresh restores its initial text because the fixture did not persist that UI state.

**Reset:** re-enable JavaScript if necessary and reload the page. The fixture stores no
preview titles.

<details>
<summary>If the request navigates unexpectedly</summary>

Confirm JavaScript is enabled and reload before retrying. If the submit listener never
runs, the browser correctly falls back to the form's `method` and `action`.
</details>

## What to notice

An updated screen is not proof of saved data. The DOM is browser memory; a server may
accept a request without persisting it; a database record survives only when server
code writes one. Refreshing is a useful probe because it discards the current document
and asks the server for another representation.

Keep these boundaries separate:

- a JavaScript object is not JSON text;
- JSON syntax is not evidence that its values are valid;
- preventing navigation is not preventing HTTP;
- changing the DOM is not database persistence;
- an SPA still depends on browser and server behavior.

## Check your understanding

Without reopening the examples:

1. What does `preventDefault()` do, and what does it not do?
2. Why do we use both `JSON.stringify()` and `Content-Type: application/json`?
3. Why should code inspect `response.ok`?
4. What changed in the browser when the status message appeared?
5. Which statement about an SPA is false: it keeps a document loaded, it can use many
   HTTP requests, or it has no server?

## Next

We can now trace documents, native submissions, and JavaScript-enhanced exchanges.
Build B00 asks us to close the notes and explain that complete path. Part 01 then gives
us the JavaScript fluency needed before TypeScript and React.

<details>
<summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 0 research notes
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: MDN Sending forms through JavaScript; MDN Fetch API; MDN Using JSON; MDN DOM introduction
- Versions: browser APIs and MDN documentation current on 2026-08-22
- Consulted: 2026-08-22
- Curriculum authority: DALT Fullstack theory curriculum Batch 1, FS00.4
- DALT files inspected: `.dalt/Http/controllers/learn/fullstack-observation.php`; `.dalt/resources/views/learn/fullstack-observation.view.php`
- Reused material: the JavaScript, JSON, DOM, SPA, and persistence boundaries from the former combined FS00.2 lesson; native form mechanics moved to FS00.3
</details>
