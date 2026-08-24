# FS00.1 — Browser, server, request, response

Lesson ID: FS00.1
Lesson format: Concise theory
Part: 00 — Web foundations
Status: Published
Estimated effort: 25–35 minutes
Difficulty: Foundation
Prerequisites: None
Last reviewed: 2026-08-22

## What we will learn

Opening a page feels like one action. Underneath, a browser sends an HTTP request and
a server sends an HTTP response. We will learn to identify both messages and use the
Network panel to say exactly where a web problem begins.

By the end, we can:

- identify the method, URL, headers, and optional body of a request;
- identify the status, headers, and optional body of a response;
- explain why one page visit can produce several requests.

HTTP is a **protocol**: an agreed way for two programs to exchange messages. The
browser is the **client** because it starts this exchange. DALT is part of the server
application that receives the request and chooses a response.

```text
browser creates a request
        ↓
server handles it
        ↓
server creates a response
        ↓
browser interprets the response
```

### Read the request

When we visit `/learn/fullstack`, the document request has a shape like this:

```http
GET /learn/fullstack HTTP/1.1
Host: localhost:8000
Accept: text/html, ...
```

- `GET` is the **method**: the kind of operation requested.
- `/learn/fullstack` is the path inside the URL.
- Headers such as `Accept` add context about the request.
- A request may also have a body. This `GET` normally does not.

The Network panel may display HTTP/2 or HTTP/3 rather than the illustrative HTTP/1.1
text above. The wire format changes, but we still reason about the same method, URL,
headers, and optional content.

### Read the response

The server answers with another message:

```http
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8

<!doctype html>
<html lang="en">
  ...
</html>
```

`200` is the status code. It says the request was handled successfully; it does not
promise that the returned page is the page we intended. `Content-Type` describes the
body. Here the body is HTML, so the browser parses it as a document.

Useful first distinctions are:

```text
200  the server returned a successful response
302/303  look somewhere else; the Location header says where
404  the requested resource was not found
500  the server failed while handling the request
```

### One page, several requests

The first HTML response can refer to stylesheets, scripts, fonts, and images. The
browser requests those separately:

```text
GET /learn/fullstack              → HTML document
GET /dalt-assets/assets/app.css   → stylesheet
GET /dalt-assets/assets/app.js    → JavaScript
```

That is why the address bar shows one URL while the Network panel shows a conversation.

## Try it

**Workspace:** No workspace copy is needed. We will inspect the running DALT course
in the browser.

1. Open [/learn/fullstack](/learn/fullstack).
2. Open Developer Tools → **Network**, then reload.
3. Select the entry whose type is `document`.
4. Record its method, URL, status, one request header, one response header, and body
   type.
5. Select one stylesheet or script request and record the same facts.

**Expected result:** the document request returns HTML, while the additional resource
has its own request, content type, and body. Exact protocol versions and asset names
may differ on your machine.

**Reset:** clear the Network log and reload the page. This observation changes no
course or application files.

<details>
<summary>Need help finding the document?</summary>

Use the Network panel's **Doc** filter. The response preview for the right entry
contains the HTML behind the Fullstack page.
</details>

## What to notice

A useful debugging sentence names both sides:

```text
The browser sent GET /learn/fullstack.
The server answered 200 with HTML, so the browser parsed a document.
```

“The page is broken” gives us no next action. “No request was sent”, “the request
returned 404”, and “the response was 200 but contained unexpected HTML” each point to
a different layer.

Two common traps:

- A `200` response can still contain the wrong data or markup.
- The document request is not the same request as its CSS or JavaScript assets.

## Check your understanding

Close the Network panel before answering:

1. What starts an HTTP exchange: the browser or the server?
2. Name the four parts we look for in a request.
3. What does `Content-Type` tell the browser?
4. Why can entering one URL cause several requests?
5. If a response is `200` but the screen is wrong, what evidence would you inspect next?

## Next

We can now identify the HTML response. Next we will open that body and learn how its
elements give a document structure and meaning.

<details>
<summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 0 research notes
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: MDN HTTP Overview; MDN HTTP Messages; Chrome/Firefox Network tools
- Versions: HTTP documentation current on 2026-08-22; protocol examples use readable HTTP/1.1 syntax
- Consulted: 2026-08-22
- Curriculum authority: DALT Fullstack theory curriculum Batch 1, FS00.1
- DALT files inspected: `.dalt/routes/routes.php`; `.dalt/Http/controllers/learn/fullstack.php`; `.dalt/resources/views/learn/fullstack.view.php`
- Reused material: the request/response trace and multiple-resource explanation from the former FS00.1
</details>
