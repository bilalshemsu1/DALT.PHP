\echo '--- 1. what LIKE actually does ---'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT count(*) FROM issues WHERE body LIKE '%deadlock%';

\echo '--- 2. LIKE cannot match a different form of the same word ---'
SELECT count(*) AS like_deploys FROM issues WHERE body LIKE '%deploys%';
SELECT count(*) AS like_deploy  FROM issues WHERE body LIKE '%deploy%';

\echo '--- 3. a document is words reduced to their stems, with positions ---'
SELECT to_tsvector('english', 'The export job reported a deadlock after the deploys.');

\echo '--- 4. the same question as text search, still with no index ---'
ALTER TABLE issues
    ADD COLUMN search_document tsvector
    GENERATED ALWAYS AS (
        to_tsvector('english', title || ' ' || body)
    ) STORED;
ANALYZE issues;

EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT count(*)
FROM issues
WHERE search_document @@ plainto_tsquery('english', 'deadlock export');

\echo '--- 5. a GIN index over the document column ---'
CREATE INDEX issues_search_idx ON issues USING GIN (search_document);
ANALYZE issues;

EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT count(*)
FROM issues
WHERE search_document @@ plainto_tsquery('english', 'deadlock export');

\echo '--- 6. stemming: one query form finds every form of the word ---'
SELECT count(*) AS matches_deploys
FROM issues
WHERE search_document @@ plainto_tsquery('english', 'deploys');

\echo '--- 7. rank the matches instead of returning them in table order ---'
SELECT id, round(ts_rank(search_document, query)::numeric, 4) AS rank
FROM issues, plainto_tsquery('english', 'deadlock export') AS query
WHERE search_document @@ query
ORDER BY rank DESC, id
LIMIT 5;
