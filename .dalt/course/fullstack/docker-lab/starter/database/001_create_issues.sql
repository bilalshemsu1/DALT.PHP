CREATE TABLE issues (
    id     integer GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title  text NOT NULL,
    status text NOT NULL DEFAULT 'open'
);

INSERT INTO issues (title, status) VALUES
    ('Trace a request', 'open'),
    ('Name the states', 'open'),
    ('Ship the container', 'closed');
