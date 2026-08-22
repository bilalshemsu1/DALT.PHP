# FS05.1 — Modern PHP values, types, arrays, functions, and exceptions

Lesson ID: FS05.1
Lesson format: Concise theory
Part: 05 — DALT API and PostgreSQL
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Foundation
Prerequisites: FS04.4
Last reviewed: 2026-08-22

We will learn the small set of modern PHP tools we need to turn application data into deliberate server behavior.

> **Helpful background:** [Typed API functions and UI boundaries](/learn/lessons/35-fs04-3-separating-transport-from-ui)

## What we will learn

- distinguish values from the variables that hold them;
- use PHP arrays and typed functions for issue-tracker data;
- reject an impossible value with an exception and handle it at a useful boundary.

## Values have runtime types

PHP is dynamically typed: the value in a variable determines its type at runtime. We do not write a type beside every local variable, but the values are still different:

```php
$issueNumber = 41;          // int
$title = 'Trace a request'; // string
$closed = false;            // bool
$dueAt = null;              // null
```

PHP may convert values in some operations. That convenience can hide mistakes at a boundary, so application files should begin with strict scalar checking:

```php
<?php

declare(strict_types=1);
```

With strict types, a call from that file does not silently turn `'41'` into `41` for a parameter declared as `int`. Strict mode does not turn PHP into TypeScript, and it does not type an array's individual keys. It makes declared scalar function calls more predictable.

## One array type serves two common shapes

A PHP array is an ordered map. Integer keys make it useful as a list:

```php
$statuses = ['todo', 'in_progress', 'done'];
echo $statuses[0]; // todo
```

String keys make it useful as an application record:

```php
$issue = [
    'id' => 'ISS-41',
    'title' => 'Trace a request',
    'status' => 'todo',
];

echo $issue['title'];
```

Always quote string keys. Reading a missing key raises a warning, so external input must be checked before we rely on its shape. Later, database rows and decoded JSON will often arrive as associative arrays like this one.

A list of records is simply a nested array. `foreach` visits each value without making us manage an index:

```php
$issues = [$issue, $anotherIssue];

foreach ($issues as $currentIssue) {
    echo $currentIssue['title'], PHP_EOL;
}
```

## Typed functions state a local contract

A function gives a name to one operation. Parameter and return declarations make its outer contract executable:

```php
/** @param array{id: string, title: string, status: string} $issue */
function issueSummary(array $issue): string
{
    return sprintf(
        '#%s [%s] %s',
        $issue['id'],
        strtoupper($issue['status']),
        $issue['title'],
    );
}
```

PHP enforces `array` at runtime and enforces the `string` return. The docblock describes the expected keys for people and capable editors, but PHP itself does not enforce that array shape. This is why validation at request and database boundaries still matters.

Prefer a return value over changing an unrelated global variable. Inputs enter through parameters, output leaves through `return`, and the function is easier to reason about and test.

## Exceptions separate failure from the happy path

An unsupported status is not a summary. We can stop the operation at the point that discovers the problem:

```php
if (!in_array($issue['status'], ['todo', 'in_progress', 'done'], true)) {
    throw new InvalidArgumentException('Issue status is invalid.');
}
```

The final `true` asks `in_array` for a strict comparison, so PHP does not use loose conversion while checking. `throw` transfers control up the call stack until a matching `catch` handles the exception:

```php
try {
    echo issueSummary($issue), PHP_EOL;
} catch (InvalidArgumentException $exception) {
    echo 'Rejected issue: ', $exception->getMessage(), PHP_EOL;
}
```

We catch only where we can make a useful decision. A low-level function should not guess whether a failure becomes JSON, HTML, a log entry, or a test failure. In the next lessons, the HTTP boundary will make that decision.

## Try it

**Workspace:** `.dalt/workspace/fs05-php`.

**Starting state:** copy the course-owned starter and enter it:

```bash
rm -rf .dalt/workspace/fs05-php
cp -R .dalt/course/fullstack/php-foundations-lab/starter .dalt/workspace/fs05-php
cd .dalt/workspace/fs05-php
```

Run the script:

```bash
php issue-summary.php
```

The exact output is:

```text
#ISS-41 [TODO] Trace a request
Rejected issue: Issue status is invalid.
```

Change the second issue's status from `blocked` to `done`, then run it again. Both issues now render and the rejection line disappears.

**Expected result:** the same typed function accepts both arrays, returns a string for valid data, and throws before it can describe invalid data.

**Reset:** return to the repository root and delete `.dalt/workspace/fs05-php`. Copy the starter again whenever you want the original invalid example.

## What to notice

The variable syntax is new, but the boundary reasoning is familiar. Values exist at runtime, a function makes a local promise, and untrusted structure requires evidence. PHP's types help with the function's outer shape; validation handles the deeper application rules.

## Check your understanding

1. What does `declare(strict_types=1)` make stricter?
2. How do a list and an associative record differ in PHP?
3. Does an `array` parameter prove that a `title` key exists?
4. Why catch the exception outside `issueSummary`?

<details><summary>Check your answers</summary>

1. Scalar type handling for function calls made from that file; it does not type every variable.
2. They use the same array type, but a list normally uses integer keys while a record uses named string keys.
3. No. PHP enforces only that the value is an array; validation or another model must establish its keys.
4. The caller knows what a failure should mean in its context; the formatter does not.
</details>

## Next

With enough PHP to read application code, we will follow one request through DALT's front controller, router, controller, and response.

<details><summary>Maintainer source record</summary>

- Source dossier: existing Part 05 PHP explanations and the repository's PHP 8.2 requirement.
- Official sources: PHP manual pages for the type system, arrays, type declarations, functions, exceptions, and `in_array`.
- Versions: PHP 8.2 minimum (`composer.json` requires `^8.2`).
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 6, FS05.1.
- DALT files inspected: `composer.json`, `framework/Core/Request.php`, `framework/Core/Response.php`, representative controllers, and root tests.
- Reused material: issue-shaped arrays and boundary vocabulary from former FS05.1; the language foundation itself fills a documented curriculum gap.
</details>
