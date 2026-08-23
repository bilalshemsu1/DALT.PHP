CREATE TABLE workspaces (
    id   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name text NOT NULL
);

CREATE TABLE projects (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    workspace_id bigint NOT NULL REFERENCES workspaces (id),
    name         text   NOT NULL
);

CREATE TABLE issues (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    workspace_id bigint      NOT NULL REFERENCES workspaces (id),
    project_id   bigint      NOT NULL REFERENCES projects (id),
    title        text        NOT NULL,
    body         text        NOT NULL,
    status       text        NOT NULL CHECK (status IN ('open', 'closed')),
    priority     smallint    NOT NULL,
    created_at   timestamptz NOT NULL DEFAULT now()
);
