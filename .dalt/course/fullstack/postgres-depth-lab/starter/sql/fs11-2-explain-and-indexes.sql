\echo '--- 1. the real query: one workspace, open issues, newest first ---'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT id, title, created_at
FROM issues
WHERE workspace_id = 1 AND status = 'open'
ORDER BY created_at DESC
LIMIT 20;

\echo '--- 2. an index in the wrong column order still leaves work behind ---'
CREATE INDEX issues_status_workspace_idx ON issues (status, workspace_id);
ANALYZE issues;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT id, title, created_at
FROM issues
WHERE workspace_id = 1 AND status = 'open'
ORDER BY created_at DESC
LIMIT 20;

\echo '--- 3. an index shaped like the whole query: equality, then order ---'
CREATE INDEX issues_workspace_status_created_idx
    ON issues (workspace_id, status, created_at DESC);
ANALYZE issues;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT id, title, created_at
FROM issues
WHERE workspace_id = 1 AND status = 'open'
ORDER BY created_at DESC
LIMIT 20;

\echo '--- 4. a partial index only covers the rows we actually query ---'
CREATE INDEX issues_open_recent_idx
    ON issues (workspace_id, created_at DESC)
    WHERE status = 'open';
ANALYZE issues;
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT id, title, created_at
FROM issues
WHERE workspace_id = 1 AND status = 'open'
ORDER BY created_at DESC
LIMIT 20;

\echo '--- 5. what each index costs on disk ---'
SELECT indexrelname AS index, pg_size_pretty(pg_relation_size(indexrelid)) AS size
FROM pg_stat_user_indexes
WHERE relname = 'issues'
ORDER BY pg_relation_size(indexrelid) DESC;
