# FS05.3 — JSON contracts, validation, and error responses

Lesson ID: FS05.3
Lesson format: Concise theory
Part: 05 — DALT API and PostgreSQL
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS05.2
Last reviewed: 2026-08-22

We will turn untrusted JSON into a small API contract whose success and failure responses tell the truth.

> **Helpful background:** [A request through DALT routes, controllers, and responses](/learn/lessons/65-fs05-2-dalt-request-route-and-response)

## What we will learn

- design a resource operation as request, success, and failure cases;
- distinguish malformed JSON from valid JSON that breaks an application rule;
- return stable JSON errors that React can understand without guessing.

## A contract describes observable behavior

An API contract is the agreement at the HTTP boundary. For creating an issue, write the agreement before the controller details:

```text
POST /api/issues

request:  { "title": "Trace a request", "priority": "high" }
success:  201 + the created Issue JSON
failure:  400 malformed JSON
          422 valid JSON with invalid fields
```

The TypeScript client from Part 04 sends only the creation fields and parses the returned issue. PHP must not accept extra power merely because a caller sends another key. A client that adds `"is_admin": true` has not changed our contract.

Path, query, and body continue to answer different questions:

```text
/api/issues/ISS-41     which single issue
?status=todo           which subset of a collection
{"status":"done"}     proposed new data
```

One fact should have one authority. If a PATCH URL names `ISS-41`, do not also accept a conflicting issue ID from its body.

## Decode JSON as an external value

DALT's `Request::input()` reads form data from `$_POST`; it does not parse JSON. A JSON controller reads the raw body and decodes it explicitly:

```php
try {
    $raw = file_get_contents('php://input');
    $decoded = json_decode(
        $raw === false ? '' : $raw,
        associative: false,
        flags: JSON_THROW_ON_ERROR,
    );
} catch (JsonException) {
    return Response::json([
        'error' => [
            'code' => 'invalid_json',
            'message' => 'Request body is not valid JSON.',
        ],
    ], 400);
}

if (!$decoded instanceof stdClass) {
    return Response::json([
        'error' => [
            'code' => 'invalid_body',
            'message' => 'Request body must be a JSON object.',
        ],
    ], 400);
}

$input = (array) $decoded;
```

`JSON_THROW_ON_ERROR` separates decoding failure from a legitimate JSON `null`. Decoding to objects first also preserves a useful distinction: `{}` becomes `stdClass`, while `[]` becomes an array. Both would become an empty PHP array if we decoded with `associative: true`.

Malformed bytes and the wrong top-level JSON kind mean the server cannot obtain the promised request shape, so this contract answers 400.

## Allowlist before validating

Create a new array from the keys this operation accepts. Do not remove a few known-dangerous keys from everything the caller supplied:

```php
$title = $input['title'] ?? null;
$priority = $input['priority'] ?? 'medium';

$errors = [];

if (!is_string($title) || trim($title) === '') {
    $errors['title'] = 'A title is required.';
}

if (!is_string($priority)
    || !in_array($priority, ['low', 'medium', 'high'], true)) {
    $errors['priority'] = 'Choose low, medium, or high.';
}
```

The allowlist is the assignments to `$title` and `$priority`: no other input becomes part of the create operation. Validation then proves the accepted fields satisfy application rules. Use strict comparisons for controlled vocabularies so PHP does not coerce unlike values into equality.

This boundary must validate runtime facts even when our React client has TypeScript types. Anyone can call the endpoint, and browser JavaScript can send data that never passed through our compiler.

## Make failures stable and useful

Valid JSON with invalid fields deserves a field-level response:

```php
if ($errors !== []) {
    return Response::json([
        'error' => [
            'code' => 'validation_failed',
            'message' => 'Some fields need attention.',
            'fields' => $errors,
        ],
    ], 422);
}
```

Use one recognizable error shape. `code` gives client code a stable value; `message` gives a useful summary; `fields` lets a form place feedback beside an input. Do not return a plain string from one controller and a differently named object from another.

DALT's `ValidationException` is intentionally wired to flash errors and redirect HTML forms. For expected JSON API failures, return an explicit JSON response as above. Otherwise an API caller can receive HTML or a redirect where it expected JSON.

Common status claims in this application are:

| Status | What the response claims |
|---|---|
| 200 | The read or update succeeded; here is the result |
| 201 | A new resource was created; here it is |
| 204 | The operation succeeded and has no response body |
| 400 | The request representation could not be understood |
| 404 | The addressed resource does not exist |
| 422 | The representation was understood but violates field rules |

An empty issue list is `200` with `[]`, not 404. The collection exists; it simply has no members. A `204` response has no JSON body, so the client must not call `response.json()` for it.

## Return only after the operation succeeds

Once validation passes, application code can create the issue. For now, imagine `$issues->create(...)` owns that operation; the database arrives in Batch 7:

```php
$issue = $issues->create([
    'title' => trim($title),
    'priority' => $priority,
]);

return Response::json($issue, 201, [
    'Location' => '/api/issues/' . rawurlencode($issue['id']),
]);
```

Return the stored result, not a client-side guess. The 201 status and `Location` header make creation explicit. If encoding the response fails, `Response::json` throws rather than emitting corrupt JSON.

## Try it

**Workspace:** `.dalt/workspace/fs05-api-contract`. The experiment reuses the resettable Part 04 fixture; it does not modify our DALT application.

**Starting state:** copy and start the fixture from the repository root:

```bash
rm -rf .dalt/workspace/fs05-api-contract
cp -R .dalt/course/fullstack/react-server-fixture .dalt/workspace/fs05-api-contract
php -S 127.0.0.1:8034 .dalt/workspace/fs05-api-contract/fixture-api.php
```

Keep that terminal running. In a second terminal, send one valid request, one malformed body, and one valid body with an invalid field:

```bash
curl -i -X POST http://127.0.0.1:8034/api/issues \
  -H 'Content-Type: application/json' \
  --data '{"title":"Trace a request","priority":"high"}'

curl -i -X POST http://127.0.0.1:8034/api/issues \
  -H 'Content-Type: application/json' \
  --data '{"title":'

curl -i -X POST http://127.0.0.1:8034/api/issues \
  -H 'Content-Type: application/json' \
  --data '{"title":"   "}'
```

The responses have statuses `201`, `400`, and `422` in that order. The 201 body contains the created issue. Both failures use an `error` object, and the 422 response identifies `title` in `error.fields`.

**Expected result:** changing only the request body crosses three different contract branches. HTTP status and JSON shape agree about what happened.

**Reset:** stop the PHP server with `Ctrl+C` and delete `.dalt/workspace/fs05-api-contract`. Starting a fresh copy restores the fixture data.

## What to notice

Parsing proves syntax and top-level shape; validation proves application rules. Those are different failures and produce different responses. The controller accepts only named fields, and every branch returns JSON the client can classify predictably.

## Check your understanding

1. Why is `{"title":` a 400 rather than a 422?
2. Why does a valid TypeScript type not remove server validation?
3. What happens to an extra `is_admin` key in the allowlist approach?
4. Why is an empty collection a 200 rather than a 404?

<details><summary>Check your answers</summary>

1. It is not complete JSON, so field rules cannot yet be evaluated.
2. The server receives runtime bytes from arbitrary clients, not compiler proof.
3. Nothing reads it, so it never enters the create operation.
4. The collection exists and the empty array truthfully represents its current members.
</details>

## Next

Our API boundary is honest but still temporary; Batch 7 begins by modeling the related data PostgreSQL will preserve.

<details><summary>Maintainer source record</summary>

- Source dossier: `FSO_PART_03.md` and the former FS05.1 API-design lesson.
- Official sources: PHP manual pages for `php://input`, `json_decode`, `JSON_THROW_ON_ERROR`, and exceptions; RFC 9110 HTTP semantics; RFC 9457 problem-details guidance consulted for stable machine-readable errors.
- Versions: PHP 8.2 minimum; DALT current `Response` and front-controller behavior.
- Consulted: 2026-08-22.
- Curriculum authority: `docs/dalt-fullstack-theory/CURRICULUM.md`, Batch 6, FS05.3.
- DALT files inspected: `framework/Core/Request.php`, `Response.php`, `Validator.php`, `ValidationException.php`, `ExceptionHandler.php`, `public/index.php`, and the executable Part 04 fixture lifecycle test.
- Reused material: resource carriers, allowlisting, 400/404/422 distinctions, stable error shape, raw JSON decoding, and response-status guidance from former FS05.1.
</details>
