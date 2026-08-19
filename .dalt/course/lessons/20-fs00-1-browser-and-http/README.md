# FS00.1 — What happens when you open a web page?

Lesson ID: FS00.1  
Title: What happens when you open a web page?  
Part: 00 — Web fundamentals  
Order: 1  
Status: Published  
Estimated effort: 30–45 minutes  
Difficulty: Foundation  
Prerequisites: None  
Project milestone: B00 — Trace the system  
Primary source dossier: FSO_PART_00.md  
Last reviewed: 2026-08-19

## Why this matters

Let’s type `/learn/fullstack` into the address bar and press Enter. A page appears a moment later. It feels like one action, but several things happened along the way: the browser contacted a server, the server chose a response, and the browser decided what to do with what came back.

That boundary is where we’ll keep looking when the issue tracker later behaves strangely. If the screen is empty, the request may have gone to the wrong URL. If the request succeeded but the page is wrong, the response body may not contain what we expected. If the browser never made the request, the problem sits somewhere before the server.

Before React, TypeScript, or DALT add their own vocabulary, we’re going to watch this basic exchange happen and learn to describe it precisely.

## Before you start

Required:

- A browser with Developer Tools. Chrome/Chromium and Firefox are both fine.
- This DALT application running locally.

Recommended:

- Keep a small note open. You will write down a guess, then compare it with evidence.

Going deeper in DALT Core — optional:

- [Request Lifecycle](/learn/lessons/01-request-lifecycle) follows a request inside DALT. It is not needed here; this lesson stays at the browser/server boundary.

## By the end

You should be able to:

- follow a page visit from the address bar to the rendered document;
- find a request’s method, URL, headers, optional body, and response status;
- find a response’s headers and body, including its `Content-Type`;
- tell the difference between an HTML document and JSON data;
- use the Network panel to replace “the app is broken” with a specific observation.

## Predict before reading

Before opening Developer Tools, make a guess about what the browser will do.

1. When you load `/learn/fullstack`, will the browser make one request or several?
2. Which response will contain the words **DALT Fullstack**: the first document response, a stylesheet, or a script?
3. If the server returns HTML, who turns that HTML into the page you see?
4. If the server returns JSON, will the browser automatically replace the page with it?

Write your answers down. They don’t need to be correct — a useful prediction just gives us something definite to compare against the browser later.

## Mental model

Here is the whole journey, in its smallest useful form:

```text
you enter a URL
        ↓
the browser creates an HTTP request
        ↓
the server handles the request
        ↓
the server sends an HTTP response
        ↓
the browser interprets the response
```

The browser is the **client**. It starts the conversation and decides what to do with the response. The server is a separate process: it receives the request, runs application code, and sends something back. HTTP is just the protocol the two of them use to talk.

We’re keeping that model deliberately plain. Later, the browser will run JavaScript, the server will call DALT code, and PostgreSQL will sit behind the server. Every new layer adds detail — none of them remove this boundary.

## 1. Start with the request

Open the DALT Fullstack journey in your browser:

**→ [/learn/fullstack](/learn/fullstack)**

Open Developer Tools, choose **Network**, and reload the page. The first entry that matters is the document request. Select it and open the **Headers** panel.

For a normal page visit, the browser usually sends a `GET`. That’s the browser asking the server to return a representation of the URL — the URL identifies what it wants. Headers carry the rest of the context: which response formats the browser will accept, which cookies it already has, and so on.

Here’s the shape of the message. It won’t match your machine byte for byte, but the parts will:

```text
GET /learn/fullstack HTTP/1.1
Host: localhost:8000
Accept: text/html, ...
Cookie: ...
```

The exact host, port, and headers will vary from one machine to the next. What matters is that every request has the same identifiable parts:

- **method** — what kind of operation the client is asking for;
- **URL** — where the request is going;
- **request headers** — extra information about the request;
- **request body** — optional data sent with the request.

A simple `GET` usually has no body. We’ll see forms and API calls send one soon enough.

## 2. Read the response

Stay on the same Network entry and look at the response side. The server answers with its own set of information:

- a **status code** describing the result;
- **response headers** describing the response;
- a **response body** containing the representation itself.

The response for the page should look roughly like this:

```text
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8

<!doctype html>
<html>
  ...
</html>
```

`200` tells us the server successfully returned a response. `Content-Type` tells the browser what the body actually represents. Here it’s HTML, so the browser parses it as a document and renders it.

A `404` would mean the server couldn’t find what this URL asked for. A `500` would mean the server hit an error while handling it. A status code is useful evidence, but it isn’t the whole story: a `200` can still carry the wrong data or the wrong markup.

Now let’s compare that with JSON:

```text
Content-Type: application/json

{"message":"The server received the request."}
```

JSON is structured data, not automatically a new page. JavaScript can read that data and decide what to display with it, but the browser won’t turn a JSON response into a document on its own.

## 3. One page can mean several requests

Now look at the rest of the Network panel. Loading one visible page can produce requests for its HTML document, its stylesheet, its JavaScript, fonts, images — a whole list.

```text
browser → GET /learn/fullstack       → HTML document
browser → GET /assets/app.css        → CSS
browser → GET /assets/app.js         → JavaScript
browser → GET /assets/icon.svg       → image
```

The address bar gave us one visible action, but the HTML document can link to resources the browser fetches separately. That’s why the honest answer to “how many requests did the page make?” is usually more than one.

Pick one stylesheet or script request. Its method will probably also be `GET`, but its `Content-Type` and response body will look nothing like the document’s. The browser treats each response according to what it says it is.

This is exactly why the Network panel beats a vague report like “the page loaded slowly.” It lets us ask which resource was requested, what status came back, and what the browser actually received.

## Try it

Let’s follow one real page visit instead of talking about an imaginary one.

**Mode: manual-proof.** You will prove the model with browser evidence and a trace in your own notes. The platform does not inspect or grade the note.

### First, make your guess

Open `/learn/fullstack` in a new tab. Before reloading it, write down:

- which request you expect to be the document request;
- which response you expect to contain the page title;
- whether you expect any other requests after the document arrives.

### Now inspect the exchange

Open **Network**, enable **Preserve log** if your browser provides it, and reload the page. Select the document request and record:

- the method and URL;
- one request header;
- the status;
- one response header;
- the kind of body returned.

Then select one additional request, such as a stylesheet or script. Notice what is different about its response.

### Tell the story in your own words

Write this sentence twice, once for the document and once for the additional resource:

```text
The browser requested __________ using __________.
The server answered with __________, and the browser used it as __________.
```

### Check yourself

Your note is complete when another person could use it to answer these questions without opening the lesson:

- What did the browser ask for?
- Where did it ask?
- What did the server return?
- How did the browser know what the response represented?
- What did the browser do next?

If your explanation says only “the app loaded the page,” replace “the app” with the actual request, response, and browser action.

### If you need a nudge

<details>
<summary>Finding the document request</summary>

Filter the Network panel by **Doc**, or choose the entry whose response preview contains the page’s HTML. The request name may be `/learn/fullstack` or a shortened version of it.
</details>

<details>
<summary>Finding the message pieces</summary>

The Headers panel normally groups the method, URL, and status under General. Request Headers and Response Headers are separate groups. Preview or Response shows the body.
</details>

<details>
<summary>Reference trace — read after your attempt</summary>

The browser sends a `GET` request for `/learn/fullstack`. DALT’s router selects the Fullstack handler, which returns an HTML response. The browser reads the response’s content type, parses the HTML, renders the document, and then requests any linked assets it needs.
</details>

## Common mistakes

### “The browser got the page” is enough

That sentence hides the useful evidence. A debugging trace should name the method, URL, status, content type, and what the browser did with the body.

### “A `200` means the screen must be correct”

`200` means the server successfully returned a response. The response can still contain unexpected HTML or data, and browser-side code can still display it incorrectly.

### “HTML and JSON are just two spellings of the same thing”

They are both response bodies, but they play different roles here. HTML can be parsed as a document. JSON is data that code must choose to use.

### “The address bar tells me everything that happened”

The address bar shows the current document URL, not every request that produced the document. The Network panel shows the rest of the conversation.

## When this goes wrong

If the Network panel is empty, open it before reloading. Developer Tools cannot show a request that happened before the panel started recording.

If there are too many entries, filter by **Doc** first. Once you understand the document request, inspect the CSS, JavaScript, font, and image requests one at a time.

If the application does not load, check the address and whether the local server is running. You can practise the same inventory on another local page, then return to the DALT page so your final trace describes the actual course route.

If you cannot find the response body, select the request and use **Response** or **Preview**. The body is the part that contains the representation the server sent.

## In the project

This is the first half of B00 — **Trace the system**. The issue tracker doesn’t exist yet. What we’re building right now is a habit we’ll lean on throughout the whole track:

```text
something looks wrong
        ↓
inspect the browser evidence
        ↓
identify the request and response
        ↓
choose the layer worth investigating
```

In Part 04, the browser will request issue data from a server. In Part 05, that server will ask PostgreSQL for it. The path gets longer from here — the first question stays exactly the same.

## Closed-book checkpoint

Close this lesson before answering. Don’t look back until you’ve written something for each question — even a rough one.

1. What four pieces can you identify in an HTTP request?
2. What three pieces can you identify in an HTTP response?
3. A response has `Content-Type: application/json`. What must happen before that data changes the visible page?
4. Why can loading one URL produce requests for a document, a stylesheet, and a script?
5. Draw the path from entering a URL to the browser rendering the response.

Then reopen the lesson and correct your answers in a different colour. The corrections tell us something real: what we actually remembered, versus what we only recognized while reading.

## Resources

### Read

- [MDN: An overview of HTTP](https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/Overview) — read the introduction, client/server roles, HTTP flow, and the request/response message sections.

### Reference

- [Chrome DevTools Network panel](https://developer.chrome.com/docs/devtools/network/) — use the sections related to requests, headers, responses, and initiators. Firefox Developer Tools are fine too.

## You are done when

- [ ] I inspected a real DALT document request in Developer Tools.
- [ ] I can identify the request and response parts without guessing.
- [ ] I can explain why HTML and JSON do not automatically produce the same browser behavior.
- [ ] I wrote and recalled a browser/server trace.

---

## Maintainer source record

- Source dossier: `docs/dalt-fullstack/sources/FSO_PART_00.md`
- Official sources: MDN HTTP Overview; Chrome DevTools Network documentation
- Versions: web documentation consulted 2026-08-13
- Consulted: 2026-08-15
- DALT files inspected: `.dalt/routes/routes.php`, `.dalt/Http/controllers/learn/index.php`, `.dalt/Core/MarkdownRenderer.php`
- Curriculum authority: `CURRICULUM.md` §10 FS00.1 — core questions, required outcomes and practice
- Laravel source: not applicable to this web-fundamentals lesson
- Wording pass: 2026-08-19 — prose voice re-aligned toward Full Stack Open's first-person-plural, plainer-sentence register (owner request); structure, headings, exercises, code, and depth unchanged
