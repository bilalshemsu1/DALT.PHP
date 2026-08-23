\echo '--- 1. give issues a flexible attributes column ---'
ALTER TABLE issues ADD COLUMN attributes jsonb NOT NULL DEFAULT '{}'::jsonb;

UPDATE issues SET attributes = jsonb_build_object(
    'source',  (ARRAY['web', 'email', 'api'])[1 + (id % 3)],
    'browser', (ARRAY['firefox', 'chrome', 'safari'])[1 + (id % 3)],
    'tags',    to_jsonb(ARRAY[(ARRAY['ux', 'billing', 'infra'])[1 + (id % 3)]])
);
ANALYZE issues;

SELECT attributes FROM issues WHERE id = 1;

\echo '--- 2. reading one key, with no index for it ---'
EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT count(*) FROM issues WHERE attributes ->> 'source' = 'api';

\echo '--- 3. containment plus a GIN index answers the same question ---'
CREATE INDEX issues_attributes_idx ON issues USING GIN (attributes jsonb_path_ops);
ANALYZE issues;

EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT count(*) FROM issues WHERE attributes @> '{"source": "api"}';

\echo '--- 4. the index answers containment, not the text operator ---'
EXPLAIN (ANALYZE, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT count(*) FROM issues WHERE attributes ->> 'source' = 'api';

\echo '--- 5. what the database will not check for you ---'
UPDATE issues SET attributes = attributes || '{"sorce": "wbe"}'::jsonb WHERE id = 2;
SELECT attributes FROM issues WHERE id = 2;
SELECT count(*) AS rows_with_a_misspelled_key FROM issues WHERE attributes ? 'sorce';

\echo '--- 6. a key everyone queries deserves a column ---'
ALTER TABLE issues ADD COLUMN source text
    GENERATED ALWAYS AS (attributes ->> 'source') STORED;
CREATE INDEX issues_source_idx ON issues (source) WHERE source IS NOT NULL;
ANALYZE issues;

EXPLAIN (ANALYZE, BUFFERS, COSTS OFF, TIMING OFF, SUMMARY OFF)
SELECT count(*) FROM issues WHERE source = 'api';
