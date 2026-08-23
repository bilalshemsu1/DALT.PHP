\echo '--- 1. how much of the table does each filter actually match? ---'
SELECT status, count(*) FROM issues GROUP BY status ORDER BY status;

\echo '--- 2. no index yet: a filter on status must read the whole table ---'
EXPLAIN (ANALYZE, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT id, title FROM issues WHERE status = 'open';

CREATE INDEX issues_status_idx ON issues (status);
ANALYZE issues;

\echo '--- 3. with the index: the rare value uses it ---'
EXPLAIN (ANALYZE, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT id, title FROM issues WHERE status = 'open';

\echo '--- 4. the same index, the common value: the planner declines it ---'
EXPLAIN (ANALYZE, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT id, title FROM issues WHERE status = 'closed';

\echo '--- 5. a unique-ish lookup is what an index is best at ---'
EXPLAIN (ANALYZE, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT id, title FROM issues WHERE id = 123456;
