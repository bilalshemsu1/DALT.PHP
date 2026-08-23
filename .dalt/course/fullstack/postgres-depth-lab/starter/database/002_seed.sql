-- Two tenants, ten projects, and enough issues that a sequential scan is a decision
-- rather than a rounding error.
INSERT INTO workspaces (name) VALUES ('Northwind'), ('Contoso');

INSERT INTO projects (workspace_id, name)
SELECT 1 + (n % 2), 'Project ' || n
FROM generate_series(1, 10) AS n;

INSERT INTO issues (workspace_id, project_id, title, body, status, priority, created_at)
SELECT
    1 + (n % 2),
    1 + (n % 10),
    'Issue ' || n || ' ' || (ARRAY['timeout', 'deadlock', 'regression', 'crash', 'typo'])[1 + (n % 5)],
    'The ' || (ARRAY['login form', 'issue list', 'search page', 'export job', 'webhook'])[1 + (n % 5)]
        || ' reported a ' || (ARRAY['timeout', 'deadlock', 'regression', 'crash', 'typo'])[1 + (n % 5)]
        || ' after the ' || (ARRAY['deploy', 'migration', 'rollback', 'restart', 'upgrade'])[1 + (n % 5)] || '.',
    CASE WHEN n % 20 = 0 THEN 'open' ELSE 'closed' END,
    1 + (n % 4),
    now() - (n || ' minutes')::interval
FROM generate_series(1, 200000) AS n;

ANALYZE;
