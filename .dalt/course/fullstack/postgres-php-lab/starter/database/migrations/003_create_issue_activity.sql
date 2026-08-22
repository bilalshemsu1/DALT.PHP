CREATE TABLE issue_activity (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    issue_id BIGINT NOT NULL,
    action VARCHAR(40) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT issue_activity_issue_fk
        FOREIGN KEY (issue_id) REFERENCES issues (id) ON DELETE CASCADE,
    CONSTRAINT issue_activity_action_allowed
        CHECK (action IN ('created', 'status_changed', 'deleted'))
);

CREATE INDEX issue_activity_issue_id_idx ON issue_activity (issue_id);
