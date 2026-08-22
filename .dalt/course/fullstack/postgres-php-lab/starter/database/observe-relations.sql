TRUNCATE issues, projects, workspaces RESTART IDENTITY CASCADE;

INSERT INTO workspaces (name, slug)
VALUES ('DALT Course', 'dalt-course');

INSERT INTO projects (workspace_id, name, slug)
VALUES (1, 'Web application', 'web-app');

INSERT INTO issues (project_id, title)
VALUES (1, 'Trace a request');

SELECT w.name AS workspace, p.slug AS project, i.title AS issue
FROM issues AS i
JOIN projects AS p ON p.id = i.project_id
JOIN workspaces AS w ON w.id = p.workspace_id;
