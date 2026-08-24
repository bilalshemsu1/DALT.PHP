# FS00.3 — Native forms and the HTTP request they create

Lesson ID: FS00.3
Lesson format: Concise theory
Part: 00 — Web foundations
Status: Published
Estimated effort: 25–35 minutes
Difficulty: Foundation
Prerequisites: FS00.2
Last reviewed: 2026-08-22

## What we will learn

HTML forms already know how to collect named values, send an HTTP request, and load
the server's response. JavaScript can enhance that process later, but we should first
understand the reliable browser behavior underneath it.

By the end, we can:

- connect labels, controls, and a submit button correctly;
- predict the request created by `action`, `method`, and `name`;
- explain why a successful POST often redirects to a GET.

### A form is a request description

Here is a complete form for creating an issue preview:

```html
<form method="post" action="/issues">
  <label for="issue-title">Issue title</label>
  <input id="issue-title" name="title" required>
  <button type="submit">Create issue</button>
</form>
```

Each part has a separate job:

- `action` supplies the request target;
- `method` supplies the HTTP method;
- `name="title"` supplies the key sent with the input's current value;
- `required` lets the browser reject an empty submission;
- `type="submit"` makes the button submit its form.

The label's `for` value matches the input's `id`. This gives the control an accessible
name and makes clicking the label focus the input. The `id` connects the label; the
`name` controls submission. An input without a `name` can appear and accept typing but
its value is not included in ordinary form data.

If the learner enters `Broken login`, the browser creates a request shaped like this:

```http
POST /issues HTTP/1.1
Content-Type: application/x-www-form-urlencoded

title=Broken+login
```

The browser encodes the named value. It also leaves the current page and renders the
response to this request. No event listener and no `fetch()` call are required.

### GET and POST put data in different places

A form with no `method` uses GET. Its values become part of the URL:

```html
<form action="/issues" method="get">
  <label for="query">Search issues</label>
  <input id="query" name="q">
  <button type="submit">Search</button>
</form>
```

Submitting `login` navigates to a URL such as:

```text
/issues?q=login
```

That is useful for searches and filters because the result can be bookmarked, copied,
and revisited. POST sends the values in the request body instead. We use it for actions
that create or change server state. POST is not encryption: sensitive traffic still
needs HTTPS.

### POST, redirect, GET

After accepting a form POST, a server often redirects:

```http
HTTP/1.1 303 See Other
Location: /issues/42
```

The browser follows that response with another request:

```http
GET /issues/42 HTTP/1.1
```

This sequence is commonly called **POST/Redirect/GET**. The final address represents a
page that can be refreshed safely. Without the redirect, refreshing a page produced
directly by POST may ask the browser to submit the same change again.

The status codes tell the story:

```text
POST /issues        → 303 See Other
GET  /issues/42     → 200 OK
```

The form did not “turn into” a GET. There were two separate request/response exchanges.

## Try it

**Workspace:** No workspace copy is needed. Use the Part 00 observation page and the
browser's Network panel.

1. Open [/learn/fullstack/observe/forms](/learn/fullstack/observe/forms).
2. Open Developer Tools → **Network** and enable “Preserve log” if the browser offers it.
3. In **Ordinary HTML form**, keep or change the preview title and submit.
4. Find the POST request and inspect its method, request URL, form data, and response.
5. Find the GET that follows it. Compare its URL and status with the POST response's
   `Location` header.
6. Return to the observation page, remove the input's `name` in the Elements panel,
   submit again, and inspect the request body.

**Expected result:** the first submission records a POST with a named `title`, a `303`
response, and a following GET. After removing `name`, the visible input still accepts
text but its title is absent from the submitted form data.

**Reset:** reload the observation page. Developer Tools edits are temporary, and this
fixture does not save either preview title.

<details>
<summary>If the Network panel appears empty</summary>

Open the panel before submitting and try again. Navigation may clear earlier entries
unless “Preserve log” is enabled.
</details>

## What to notice

Native forms are not a primitive version of an application. They are a complete web
interaction with useful accessibility, validation, keyboard behavior, encoding, and
navigation defaults. A JavaScript interface should preserve or deliberately replace
those capabilities.

Do not confuse these pairs:

- `id` identifies an element and connects its label; `name` identifies submitted data.
- `required` is useful immediate feedback; server-side validation is still necessary
  because requests can be sent without this page.
- POST puts values in a body; HTTPS protects traffic in transit.
- a redirect response is not the final page response.

## Check your understanding

Without reopening the examples:

1. Which form attributes decide the request method and target?
2. Why does a useful input usually need both `id` and `name`?
3. When is a GET form a better fit than a POST form?
4. What two requests occur in POST/Redirect/GET?
5. Why does browser validation not replace server validation?

## Next

We can now describe the browser-managed baseline. Next we will intercept the same
submit event with JavaScript, send JSON, update the existing DOM, and identify exactly
what changes in a single-page application.

<details>
<summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 0 research notes
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: MDN Your first form; MDN Sending form data; MDN HTTP redirections
- Versions: living HTML, HTTP, and MDN documentation current on 2026-08-22
- Consulted: 2026-08-22
- Curriculum authority: DALT Fullstack theory curriculum Batch 1, FS00.3
- DALT files inspected: `.dalt/Http/controllers/learn/fullstack-observation.php`; `.dalt/resources/views/learn/fullstack-observation.view.php`
- Reused material: the native half of the former FS00.2 lesson, separated so the browser baseline is learned before JavaScript enhancement
</details>
