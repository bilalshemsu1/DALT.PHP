# FS00.2 — HTML documents, meaning, and browser defaults

Lesson ID: FS00.2
Lesson format: Concise theory
Part: 00 — Web foundations
Status: Published
Estimated effort: 25–35 minutes
Difficulty: Foundation
Prerequisites: FS00.1
Last reviewed: 2026-08-22

## What we will learn

An HTML response is not a picture of a page. It is a document whose elements describe
what the content means. The browser parses that text into a tree, gives useful elements
default behavior, and makes the structure available to CSS, JavaScript, search engines,
and assistive technology.

By the end, we can:

- read the basic structure of an HTML document;
- choose elements for their meaning instead of their default appearance;
- inspect the browser's parsed document in Developer Tools.

### From text to a document tree

A small complete document looks like this:

```html
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Issue desk</title>
  </head>
  <body>
    <main>
      <h1>Issue desk</h1>
      <p>Track work that needs attention.</p>
    </main>
  </body>
</html>
```

`<!doctype html>` tells the browser to use modern standards mode. `<head>` contains
information about the document; `<body>` contains what the page presents. The `lang`
attribute identifies the document language, while `charset="utf-8"` tells the browser
how bytes become characters.

An element usually has an opening tag, content, and a closing tag:

```html
<p>Track work that needs attention.</p>
```

Elements nest. The `<p>` above is a child of `<main>`, which is a child of `<body>`.
The browser turns those relationships into the **DOM**—the Document Object Model.
Developer Tools shows the DOM after parsing, so it can include corrections the browser
made to imperfect source HTML.

### Meaning before appearance

These two fragments can be styled to look identical:

```html
<div class="large-text">Open issues</div>
```

```html
<h2>Open issues</h2>
```

Only the second says “this begins a section at heading level two.” That meaning is
called **semantics**. CSS can change how an `<h2>` looks, but it does not turn a `<div>`
into a heading.

Common structural elements communicate useful roles:

```html
<header>Introductory content</header>
<nav>Primary navigation links</nav>
<main>Content unique to this page</main>
<section aria-labelledby="open-heading">
  <h2 id="open-heading">Open issues</h2>
</section>
<aside>Related information</aside>
<footer>Closing information</footer>
```

We normally use one visible `<main>` for the page's unique content. A `<section>` groups
a themed region and should usually have a heading. An `<article>` is for content that
can stand on its own. `<div>` and `<span>` remain useful when we need a wrapper without
additional meaning; they are not mistakes, only neutral tools.

### Browser defaults are useful evidence

Without CSS, headings are large, links are underlined, lists have markers, and buttons
look clickable. Those styles are browser defaults. More importantly, elements also
carry behavior and meaning: a link has a destination, a button can be activated from
the keyboard, and a label can name a form control.

This gives us a durable order for building interfaces:

```text
choose meaningful HTML
        ↓
verify the document structure
        ↓
use CSS to control appearance and layout
        ↓
use JavaScript for behavior that HTML does not provide
```

Tailwind later changes the CSS-writing workflow. It does not change this order.

## Try it

**Workspace:** No workspace copy is needed. Use the Part 00 observation page and the
browser's Elements panel.

1. Open [/learn/fullstack/observe/forms](/learn/fullstack/observe/forms).
2. Open Developer Tools → **Elements** or **Inspector**.
3. Find `<main>`, `<header>`, the first `<section>`, its `<h2>`, and the form's `<label>`.
4. Expand the nodes and describe their parent/child relationships.
5. Temporarily remove the first section's `class` attribute in Developer Tools.
6. Confirm that its appearance changes while it remains a `<section>` with a heading.

**Expected result:** the Elements panel shows a nested document tree. Removing classes
changes presentation, but the semantic element names and heading structure remain.

**Reset:** reload the page. Changes made in the Elements panel are temporary and do
not modify course files.

<details>
<summary>What should the first section look like in the tree?</summary>

Its important shape is `section → h2 + form`. Styling adds many classes, but those
classes do not replace the relationship or the element names.
</details>

## What to notice

The DOM is not a screenshot. It is a structured representation that several consumers
can use. A sighted user may recognize a large bold line as a heading, but software
needs the `<h2>` relationship. Likewise, a control that merely looks like a button is
not automatically keyboard-operable.

Avoid two opposite mistakes:

- Do not replace every wrapper with a semantic element. Use `<div>` when no element
  describes the grouping honestly.
- Do not choose `<h3>` because its default size looks right. Heading levels describe
  the document outline; CSS controls the size.

## Check your understanding

Without reopening the examples:

1. What different jobs do `<head>` and `<body>` perform?
2. What is the DOM?
3. Why is a styled `<div>` not equivalent to an `<h2>`?
4. When is `<div>` the right element?
5. Which should come first: meaningful HTML or Tailwind classes, and why?

## Next

We have a meaningful document. Next we will use HTML's built-in form behavior to turn
labels, controls, names, and a submit button into a real HTTP request.

<details>
<summary>Maintainer source record</summary>

- Source dossier: `docs/dalt-fullstack/sources/FSO_PART_00.md`
- Official sources: MDN Basic HTML syntax; MDN Structuring documents; MDN HTML accessibility guidance
- Versions: living HTML and MDN documentation current on 2026-08-22
- Consulted: 2026-08-22
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md` Batch 1, FS00.2
- DALT files inspected: `.dalt/resources/views/layouts/head.php`; `.dalt/resources/views/learn/fullstack-observation.view.php`
- Reused material: semantic and browser-boundary ideas previously compressed into FS03.4; no former standalone FS00.2 lesson covered this gap
</details>
