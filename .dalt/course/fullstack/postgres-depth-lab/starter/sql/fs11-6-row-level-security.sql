\echo '--- 1. the restricted role comes first, because policies name it ---'
CREATE ROLE issue_app LOGIN NOINHERIT NOBYPASSRLS PASSWORD 'local-development-only';
GRANT USAGE ON SCHEMA public TO issue_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON issues TO issue_app;

\echo '--- 2. enable and force row-level security on the tenant table ---'
ALTER TABLE issues ENABLE ROW LEVEL SECURITY;
ALTER TABLE issues FORCE ROW LEVEL SECURITY;

CREATE POLICY issues_tenant_select ON issues FOR SELECT TO issue_app
    USING (workspace_id = NULLIF(current_setting('app.workspace_id', true), '')::bigint);

CREATE POLICY issues_tenant_insert ON issues FOR INSERT TO issue_app
    WITH CHECK (workspace_id = NULLIF(current_setting('app.workspace_id', true), '')::bigint);

CREATE POLICY issues_tenant_update ON issues FOR UPDATE TO issue_app
    USING (workspace_id = NULLIF(current_setting('app.workspace_id', true), '')::bigint)
    WITH CHECK (workspace_id = NULLIF(current_setting('app.workspace_id', true), '')::bigint);

\echo '--- 3. with workspace 1 in context, only workspace 1 exists ---'
SET ROLE issue_app;
BEGIN;
SELECT set_config('app.workspace_id', '1', true);
SELECT count(*) AS visible_rows FROM issues;
SELECT count(*) AS visible_from_workspace_2 FROM issues WHERE workspace_id = 2;
SELECT count(DISTINCT workspace_id) AS distinct_workspaces_visible FROM issues;
COMMIT;
RESET ROLE;

\echo '--- 4. a write for our own tenant is allowed ---'
SET ROLE issue_app;
BEGIN;
SELECT set_config('app.workspace_id', '1', true);
INSERT INTO issues (workspace_id, project_id, title, body, status, priority)
VALUES (1, 1, 'Our own issue', 'body', 'open', 1);
SELECT count(*) AS inserted FROM issues WHERE title = 'Our own issue';
ROLLBACK;
RESET ROLE;

\echo '--- 5. a write claiming another tenant is refused by the database ---'
SET ROLE issue_app;
BEGIN;
SELECT set_config('app.workspace_id', '1', true);
\set ON_ERROR_STOP 0
INSERT INTO issues (workspace_id, project_id, title, body, status, priority)
VALUES (2, 2, 'Someone else''s issue', 'body', 'open', 1);
\set ON_ERROR_STOP 1
ROLLBACK;
RESET ROLE;

\echo '--- 6. an update cannot move a row out of our tenant either ---'
SET ROLE issue_app;
BEGIN;
SELECT set_config('app.workspace_id', '1', true);
WITH changed AS (
    UPDATE issues SET title = 'should not change' WHERE workspace_id = 2 RETURNING 1
)
SELECT count(*) AS rows_updated_in_other_tenant FROM changed;
ROLLBACK;
RESET ROLE;

\echo '--- 7. with no tenant context, nothing is visible ---'
SET ROLE issue_app;
BEGIN;
SELECT count(*) AS visible_without_context FROM issues;
COMMIT;
RESET ROLE;

\echo '--- 8. why the policy says NULLIF and not a bare cast ---'
BEGIN;
SELECT set_config('app.workspace_id', '1', true);
COMMIT;
SELECT current_setting('app.workspace_id', true) IS NULL AS looks_like_null,
       current_setting('app.workspace_id', true) = ''  AS is_really_empty;
\set ON_ERROR_STOP 0
SELECT ''::bigint AS a_bare_cast_of_the_empty_string;
\set ON_ERROR_STOP 1
