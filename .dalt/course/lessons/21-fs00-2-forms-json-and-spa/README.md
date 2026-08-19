# FS00.2 — What changes when JavaScript takes over a form?

Lesson ID: FS00.2  
Title: What changes when JavaScript takes over a form?  
Part: 00 — Web fundamentals  
Order: 2  
Status: Published  
Estimated effort: 30–45 minutes  
Difficulty: Foundation  
Prerequisites: FS00.1 — What happens when you open a web page?  
Project milestone: B00 — Trace the system  
Primary source dossier: FSO_PART_00.md  
Last reviewed: 2026-08-19

## Why this matters

The two forms on the observation page look almost identical — each has a text field and a button. But submit the first one and the browser leaves the page; submit the second one and the page stays put while a message changes underneath it.

That difference is one of the first real choices we make in frontend development. A browser already knows how to submit a form and navigate to whatever comes back. JavaScript can stop that default behavior, make the request itself, read the response, and update the page in place.

React will make this second style much more organized, but it doesn’t invent the underlying browser behavior — it just gives it structure. Let’s watch both versions happen before we give the pattern a framework name.

## Before you start

Required:

- FS00.1 — What happens when you open a web page?
- A browser with Developer Tools and this DALT application running locally.

Recommended:

- Keep the Network panel open with **Preserve log** enabled. The ordinary form navigates away, and without Preserve log its first request can disappear from view.

Going deeper in DALT Core — optional:

- [Routing](/learn/lessons/02-routing) shows how DALT decides which handler answers a form submission. It is optional and not needed to complete this lesson.

## By the end

You should be able to:

- predict what a plain HTML form will request and what the browser will do next;
- explain what `preventDefault()` changes and what it does not change;
- distinguish an HTML document response from a JSON response;
- explain how JavaScript can change the current page without requesting a new document;
- separate what the browser currently displays from what the server or database stores.

## Predict before reading

Open the [Part 00 form observation fixture](/learn/fullstack/observe/forms) in a new tab, but do not inspect its source yet. You will see two forms that look similar.

Before submitting either one, write down your prediction:

| Form | What request will happen? | Will the browser navigate? | What will come back? |
| --- | --- | --- | --- |
| Ordinary HTML form |  |  |  |
| JavaScript-controlled form |  |  |  |

Also predict what will happen if you refresh after the JavaScript-controlled form changes the message on screen. Will the message still be there?

Don’t worry about getting this right. The point is just to make our expectation visible before the Network panel hands us the answer.

## Mental model

There are two different paths hiding behind what looks like the same user action.

```text
ordinary form submit
        ↓
browser performs its default submission
        ↓
server sends a response
        ↓
browser follows that response, often by navigating
```

```text
JavaScript-controlled submit
        ↓
JavaScript prevents the default submission
        ↓
JavaScript chooses and sends a request
        ↓
JavaScript uses the response to update the current page
```

The second path still has a server. It still uses HTTP. Nothing about the network changed — only who decides what happens after the submit changed: the browser’s built-in form behavior, or JavaScript we wrote ourselves.

## 1. First, let the browser submit the form

On the observation fixture, the first form is an ordinary HTML form. Here’s what its important attributes look like:

```html
<form method="post" action="/learn/fullstack/observe/forms/traditional">
  <label>
    Preview title
    <input name="title" value="Browser-created request">
  </label>
  <button type="submit">Submit ordinary form</button>
</form>
```

The `action` says where the data should go. The `method` says how it should be sent. The `name` on the input gives the submitted value a key.

There’s no JavaScript in this example telling the browser what to do. Press the button, and the browser just follows its normal form behavior:

```text
you submit the form
        ↓
browser sends POST form data
        ↓
server responds with a redirect
        ↓
browser requests the next document with GET
        ↓
browser displays the returned HTML
```

Open the Network panel and submit the ordinary form. With Preserve log enabled, we should be able to find the `POST`, the redirect response, and the document `GET` that follows it.

The redirect matters because the server isn’t sending the final page directly in the `POST` response — it’s telling the browser where to go next. The browser follows that instruction and requests a document it can render.

The form data has an encoding too. With this simple form and no custom `enctype`, the request will normally use a content type like this:

```text
Content-Type: application/x-www-form-urlencoded

title=Browser-created+request
```

The browser chose that representation itself, because it was the one performing the form submission.

## 2. Now let JavaScript take over

The second form has the same broad shape, but its submit event is handled by JavaScript. The important first line is this one:

```js
form.addEventListener('submit', function (event) {
  event.preventDefault()

  // JavaScript decides what request to make next.
})
```

`preventDefault()` does exactly one thing: it stops the browser’s built-in form submission for this event. It doesn’t send the data, call the server, or update the screen on its own — it just hands those next steps to our code.

The fixture’s script then goes and makes its own request, with JSON:

```js
fetch(form.action, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ title: title })
})
```

We don’t need to learn every detail of `fetch` yet — just notice the division of work:

1. the submit event starts the JavaScript;
2. `preventDefault()` stops the normal navigation;
3. JavaScript constructs a `POST` request;
4. the request body is JSON because the script chose that format;
5. JavaScript reads the response and changes the current document.

Submit the JavaScript-controlled form and compare the Network panel with the first one. We should see a request, but no redirect and no new document request after it. The address bar stays put, and the status message inside the page changes instead.

## 3. The response is data, not a page

The JavaScript form receives a response like this:

```json
{
  "accepted": true,
  "message": "The server received the preview request."
}
```

Its response header says:

```text
Content-Type: application/json; charset=UTF-8
```

That header and body describe data. They don’t tell the browser to replace the current document with a new HTML page — the JavaScript chooses to read the JSON and place the message into an element that’s already there.

Conceptually, the path is:

```text
submit
        ↓
JavaScript sends POST with JSON
        ↓
server returns JSON
        ↓
JavaScript reads the message
        ↓
the current page displays the message
```

This is why “the page didn’t reload” is not the same as “nothing happened.” An HTTP request still happened — only the document navigation was prevented.

## 4. The page has a working representation

When the browser receives HTML, it parses the document into a working representation called the **DOM** — the Document Object Model. JavaScript can find elements in that representation and change their text, attributes, or children.

The fixture’s status message is one small example:

```html
<p id="json-preview-status">
  No JavaScript-controlled request has been sent yet.
</p>
```

Once the JSON response arrives, JavaScript changes the message that element represents. The browser paints the updated result straight away, without fetching a replacement HTML document.

React will later give us a more structured way to describe these updates. For now, keep the simpler model:

```text
HTML document
        ↓
browser creates a DOM
        ↓
JavaScript can change the DOM
        ↓
the visible page changes
```

## 5. What “single-page application” means

An SPA, or single-page application, keeps one application shell loaded and lets JavaScript handle most interactions inside it. It can still make plenty of HTTP requests for data — “single page” doesn’t mean “one request forever,” and it doesn’t mean “there is no server.”

The useful contrast is this:

```text
traditional page interaction
        ↓
server response replaces the current document
```

```text
SPA-style interaction
        ↓
JavaScript exchanges data and updates the current document
```

Some SPA interactions do navigate to a new URL, and some frameworks request new data while keeping the shell exactly where it is. Routing belongs later. For now, the idea worth keeping is that a full document replacement isn’t required for every interaction.

## 6. What stayed on screen, and what was saved?

After submitting the JavaScript-controlled form, refresh the fixture.

The message disappears. That tells us something important: changing the current document isn’t the same as storing a record. The fixture accepted the request and returned JSON, but it deliberately never saves the preview title anywhere.

Keep these three ideas separate:

```text
what the browser currently displays
        ≠
what the server knows about
        ≠
what a database stores
```

Later, when the issue tracker creates a real issue, a successful response will need to be wired to real server and database behavior if we want that issue to survive a refresh. For this lesson, the disappearing message is our evidence that the state lived only in the browser document, and nowhere else.

## Try it

Now let’s repeat the experiment, but pay attention to the request body this time, not just the navigation.

**Mode: manual-proof.** You will compare two real browser interactions and explain the difference using Network evidence. Nothing is submitted to an automated verifier.

### Before you touch the forms

Write down your prediction for each form:

- What starts the request?
- What method and URL will appear?
- Will the browser request another document?
- What content type will the request and response use?
- What will still be visible after the submit?

### Run the ordinary form

Clear the Network log, submit a short preview title in the ordinary form, and inspect the sequence. Find the `POST`, the redirect, and the document `GET` that follows it. Open the request payload and response headers.

### Run the JavaScript-controlled form

Reload the fixture, clear the log, and submit a different preview title with the JavaScript-controlled form. Find its `POST`, inspect the JSON request body and response, and confirm that the document did not navigate.

Then refresh once more. Record what happened to the message.

### Compare the evidence

Complete this trace for both interactions:

```text
user action
    ↓
what initiated the request
    ↓
method, URL, and request body
    ↓
response status, content type, and body
    ↓
what the browser did next
```

Your explanation is ready when it can answer why two forms that look alike produce different browser behavior.

### Look more closely at `Content-Type`

Before opening the headers, predict the request content type for each form. Then check your prediction:

- the ordinary form normally sends `application/x-www-form-urlencoded`;
- the JavaScript-controlled form sends `application/json` because the script built a JSON body and declared it with a header.

That difference is a useful fingerprint. It shows which submission model actually ran.

### If you need a nudge

<details>
<summary>Finding the ordinary form sequence</summary>

Clear the Network log, submit the ordinary form, and look for a `POST` followed by a document `GET`. Preserve log keeps the first request visible after navigation.
</details>

<details>
<summary>Recognizing the JavaScript-controlled request</summary>

Its response has `Content-Type: application/json`. The address bar does not change, and the status message inside the existing page does.
</details>

<details>
<summary>Checking whether the result was stored</summary>

Refresh after the JavaScript-controlled submit. The fixture is designed to show a UI change without persistence, so the message should return to its initial text.
</details>

<details>
<summary>Reference explanation — read after an honest attempt</summary>

The ordinary form lets the browser submit its encoded form data. The server returns a redirect, and the browser follows it with a document `GET`, so the current page is replaced. The JavaScript form calls `preventDefault()`, sends its own JSON `POST`, reads the JSON response, and changes text in the existing document. Neither interaction writes a database record.
</details>

## Common mistakes

### “A form always reloads the page”

A plain form normally follows the browser’s default submission behavior. JavaScript can prevent that default and choose a different path.

### “`preventDefault()` sends the request”

It does not. It only stops the default action. JavaScript still has to construct and send a request if the interaction should contact a server.

### “JSON automatically becomes the screen”

JSON is data. The screen changes because JavaScript reads the data and chooses to update the current document.

### “No navigation means no HTTP”

The JavaScript-controlled form still sends an HTTP request. The Network panel separates data exchange from document navigation.

### “I saw the new message, so it was saved”

The browser can display a temporary change. Refreshing is a simple test for whether the change existed only in the current document; it is not, by itself, a complete persistence test for a real application.

## When this goes wrong

If you cannot see the ordinary form’s first `POST`, enable Preserve log before submitting. The navigation can otherwise clear the earlier entries.

If the ordinary form seems to jump too quickly, filter the Network panel by **Doc** and inspect the redirect before the following document request.

If the JavaScript form navigates, reload the fixture normally and confirm that browser JavaScript is enabled. The form should stay on the same document when its handler calls `preventDefault()`.

If the JavaScript request appears but the message does not change, inspect the response body and its `Content-Type`. A successful request and a successful UI update are separate steps.

If the message returns after refresh, that is the expected result for this fixture. It is showing you the difference between browser-visible state and stored state.

## In the project

This completes the browser-side half of B00 — **Trace the system**. The issue tracker starts later, but its React interface will lean on exactly this distinction:

```text
user interaction
        ↓
browser-side code
        ↓
HTTP request
        ↓
server response
        ↓
visible UI update
```

Part 01 hands us the JavaScript in that path to write ourselves. Part 03 uses React to build the interface. Part 04 replaces this small fixture with a real server connection.

## Closed-book checkpoint

Close the lesson and answer these before opening any reveal or resource — no peeking.

1. What does the browser normally do when a plain HTML form is submitted?
2. What does `preventDefault()` change, and what work still remains afterward?
3. Why can a JSON response change the visible page without a new HTML document?
4. Why can the ordinary form produce a `POST`, a redirect, and then a document `GET`?
5. If a message disappears after refresh, what does that suggest about where the message lived?
6. Why can an SPA continue to communicate with a server even when the current document stays loaded?

After you answer, reopen the lesson and correct your notes in a different colour. The correction is part of the learning here, not evidence that we failed the exercise.

## Resources

### Read

- [MDN: Your first form](https://developer.mozilla.org/en-US/docs/Learn_web_development/Extensions/Forms/Your_first_form) — read the sections introducing `form`, `action`, `method`, controls, and submission. Stop before the broader validation topics.

### Reference

- [MDN: JSON](https://developer.mozilla.org/en-US/docs/Learn_web_development/Core/Scripting/JSON) — use the introduction to reinforce that JSON represents structured data.
- [MDN: Single-page application](https://developer.mozilla.org/en-US/docs/Glossary/SPA) — a short reference for the SPA model, not a framework tutorial.

## You are done when

- [ ] I predicted and inspected both fixture submissions.
- [ ] I can explain the difference between browser-default submission and JavaScript-controlled submission.
- [ ] I can explain why JSON can update the UI without replacing the document.
- [ ] I can separate visible browser state from server and database storage.
- [ ] I attempted the closed-book checkpoint before checking the explanation.

---

## Maintainer source record

- Source dossier: `docs/dalt-fullstack/sources/FSO_PART_00.md`
- Official sources: MDN HTML forms, JSON, SPA glossary; Chrome DevTools Network documentation
- Versions: web documentation consulted 2026-08-13
- Consulted: 2026-08-15
- DALT files inspected: `.dalt/routes/routes.php`, `.dalt/Http/controllers/learn/fullstack-observation.php`, `.dalt/resources/views/learn/fullstack-observation.view.php`
- Curriculum authority: `CURRICULUM.md` §10 FS00.2 — topics and required outcome
- Laravel source: not applicable to this web-fundamentals lesson
- Wording pass: 2026-08-19 — prose voice re-aligned toward Full Stack Open's first-person-plural, plainer-sentence register (owner request); structure, headings, exercises, code, and depth unchanged
