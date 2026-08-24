# FS06.5 — Membership, roles, ownership, and authorization

Lesson ID: FS06.5
Lesson format: Concise theory
Part: 06 — Testing, users, and authorization
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Foundation
Prerequisites: FS06.4
Last reviewed: 2026-08-22

We will make access depend on server-owned identity and database relationships, not on what React displays or the request claims.

> **Helpful background:** [CSRF and cookie-authenticated writes](/learn/lessons/69-fs06-4-csrf-and-cookie-authenticated-writes)

## What we will learn

- separate authentication from authorization;
- model workspace membership, small roles, and resource ownership;
- enforce a resource rule on the server and prove denied writes change nothing.

## Knowing the actor is only the beginning

Authentication answers **who sent this request?** Authorization answers **may that actor perform this action on this resource?** A valid session is not permission to edit every issue.

```text
request → session actor → resource → membership → action rule → response or write
```

React should hide actions the user cannot perform, but that is interface guidance. Anyone can send a request without React. The rule must still hold when a test or command calls the API directly.

Use status codes to preserve the distinction:

- `401` when no authenticated identity exists;
- `403` when an authenticated actor is known but the rule denies them;
- optionally `404` instead of `403` when revealing the resource's existence would itself leak information.

Choose the `403` or concealed `404` policy once and keep it consistent. Sending a signed-in but forbidden user back to login cannot fix their permission.

## Relationships make the policy possible

A workspace membership is a relationship, not a boolean column on `users`:

```sql
CREATE TABLE workspace_memberships (
  workspace_id BIGINT NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
  user_id      BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role         VARCHAR(20) NOT NULL DEFAULT 'member'
               CHECK (role IN ('member', 'owner')),
  PRIMARY KEY (workspace_id, user_id)
);

ALTER TABLE issues
  ADD COLUMN creator_id BIGINT NOT NULL
  REFERENCES users(id) ON DELETE RESTRICT;

CREATE INDEX idx_memberships_user ON workspace_memberships (user_id);
```

The composite primary key prevents two contradictory memberships for one user and workspace. The check keeps the role vocabulary small. The index supports questions that begin with a user, such as “which workspaces can I open?”

Roles should express current product rules. We do not need a generic permission engine to say that an owner may manage a workspace while a member may participate in it.

## The server owns actor identity

The client supplies an issue title and project. The authenticated session supplies the creator:

```php
$actorId = $auth->id();

$database->query(
    'INSERT INTO issues (project_id, title, creator_id) VALUES (?, ?, ?)',
    [$projectId, $title, $actorId],
);
```

Never accept `creator_id`, `user_id`, or `role` from JSON as proof about the caller:

```php
// Wrong: the caller can claim to be any user.
$creatorId = $request->json()['creator_id'];
```

A foreign key can prove that this submitted ID exists. It cannot prove that the caller owns that identity or is currently permitted to act.

## Compose visibility and action rules

Suppose an issue may be edited by its creator or a workspace owner, but only while that actor belongs to the workspace. Load the resource facts first:

```php
$issue = $database->query(
    'SELECT issues.id, issues.creator_id, projects.workspace_id
     FROM issues
     JOIN projects ON projects.id = issues.project_id
     WHERE issues.id = ?',
    [$issueId],
)->find();
```

Then make the ordered decision:

```php
if ($auth->guest()) {
    return jsonError(401, 'unauthenticated');
}

if ($issue === false) {
    return jsonError(404, 'not_found');
}

$membership = $database->query(
    'SELECT role FROM workspace_memberships
     WHERE workspace_id = ? AND user_id = ?',
    [$issue['workspace_id'], $auth->id()],
)->find();

if ($membership === false) {
    return jsonError(403, 'forbidden');
}

$mayEdit = (int) $issue['creator_id'] === $auth->id()
    || $membership['role'] === 'owner';

if (!$mayEdit) {
    return jsonError(403, 'forbidden');
}
```

Membership is the outer boundary; creator or owner is the action rule. Checking only the creator would let a former member keep editing. Checking only membership would let every member edit every issue.

General authentication can be middleware because it needs only the request and session. Resource authorization belongs near the resource lookup because it needs database facts. DALT's built-in `auth` middleware redirects guests to `/login`, which suits HTML pages; a JSON API should use a small API-specific equivalent that returns its promised `401` JSON envelope.

## Test direct requests, not buttons

An authorization test needs different actors and durable-state assertions:

```php
$before = issueRow($issueId);

$response = apiPatch(
    "/api/issues/{$issueId}",
    ['title' => 'Bob was here'],
    as: $bob,
    csrf: csrfToken($bob),
);

expect($response->statusCode)->toBe(403);
expect(issueRow($issueId))->toEqual($before);
```

Supply valid CSRF proof so the request reaches authorization. A `419` tests the previous boundary, not this one.

Pair denials with permitted cases:

```php
expect(editIssue(as: $anonymous))->toHaveStatus(401);
expect(editIssue(as: $nonMember))->toHaveStatus(403);
expect(editIssue(as: $ordinaryMember))->toHaveStatus(403);
expect(editIssue(as: $creator))->toHaveStatus(200);
expect(editIssue(as: $workspaceOwner))->toHaveStatus(200);
```

Otherwise, “deny everyone” passes every negative test. Also test the former-creator case after removing their membership; that distinguishes the composed rule from a creator-only shortcut.

## Try it

**Workspace:** continue with `.dalt/workspace/fs06-auth-boundaries`. If needed, recreate it:

```bash
mkdir -p .dalt/workspace
cp -r .dalt/course/fullstack/auth-boundaries-lab/starter \
  .dalt/workspace/fs06-auth-boundaries
```

**Starting state:** `scripts/authorization.php` creates four users, three memberships, and one issue in SQLite, then asks the same policy about several actors. Run:

```bash
php .dalt/workspace/fs06-auth-boundaries/scripts/authorization.php
```

The exact output is:

```text
anonymous edit: 401
non-member edit: 403
member non-creator edit: 403
denied title unchanged: yes
creator edit: 200
former creator edit: 403
owner edit: 200
forged creator stored as: alice@example.com
```

**Expected result:** identity, membership, and the action rule each affect the decision. Failed mutations leave the title unchanged, and a submitted `creator_id` cannot replace the server actor.

**Reset:** delete `.dalt/workspace/fs06-auth-boundaries` whenever you finish experimenting.

## What to notice

Alice may edit as creator only while she is a member. Olivia may edit as workspace owner. Bob and Charlie fail for different reasons, yet neither denial changes the row. The final insert stores Alice because server context—not submitted JSON—decides the actor.

## Check your understanding

1. What fact distinguishes `401` from `403`?
2. Why are membership and issue ownership separate checks?
3. Why must `creator_id` come from the session?
4. Why does every deny test need an allow test and a state assertion?

<details><summary>Check your answers</summary>

1. Whether the server has authenticated an identity at all.
2. Membership controls workspace access; ownership or role controls a particular action. Either can change independently.
3. Request data is controlled by the caller, while the server owns the authenticated session.
4. The allow case catches “deny everyone”; the state assertion catches a handler that writes before returning an error.
</details>

## Next

Part 07 brings this authenticated API into React routing and frontend session state without moving the security boundary into the browser.

<details><summary>Maintainer source record</summary>

- Source dossier: Full Stack Open Part 4 research notes.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: OWASP Authorization Cheat Sheet and API Security Top 10 Broken Object Level Authorization; PostgreSQL constraints documentation.
- Versions: PHP 8.4.1; DALT database and authentication APIs as inspected on 2026-08-22.
- Consulted: 2026-08-22.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 8, FS06.5.
- DALT files inspected: `framework/Core/Authenticator.php`, `Middleware/Auth.php`, `Database.php`, and `functions.php`.
- Reused material: authentication/authorization separation, membership schema, server-derived creator, ordered resource checks, API middleware caveat, and paired behavior tests from the former FS06.3.
</details>
