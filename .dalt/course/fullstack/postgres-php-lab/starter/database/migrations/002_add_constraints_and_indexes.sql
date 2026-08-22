ALTER TABLE workspaces
    ADD CONSTRAINT workspaces_slug_unique UNIQUE (slug);

ALTER TABLE projects
    ADD CONSTRAINT projects_workspace_slug_unique UNIQUE (workspace_id, slug);

ALTER TABLE issues
    ADD CONSTRAINT issues_title_not_blank CHECK (length(btrim(title)) > 0),
    ADD CONSTRAINT issues_status_allowed CHECK (status IN ('todo', 'in_progress', 'done')),
    ADD CONSTRAINT issues_priority_allowed CHECK (priority IN ('low', 'medium', 'high'));

CREATE INDEX issues_project_id_idx ON issues (project_id);
