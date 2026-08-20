<?php

declare(strict_types=1);

return [
    'title' => 'DALT Build',
    'description' => 'Build one serious issue tracker from a clean DALT project, one useful change at a time.',
    'lessons' => [
        [
            'id' => '01',
            'slug' => 'start-the-project',
            'title' => 'Start the project',
            'description' => 'Run DALT, make our first product screen, and give the project a safe starting point with Git when it is available.',
            'background' => [
                [
                    'label' => 'Install Git (optional)',
                    'href' => 'https://git-scm.com/book/en/v2/Getting-Started-Installing-Git',
                ],
                [
                    'label' => 'What git init creates',
                    'href' => 'https://git-scm.com/docs/git-init',
                ],
            ],
        ],
        [
            'id' => '02',
            'slug' => 'create-a-workspace',
            'title' => 'Create a workspace',
            'description' => 'Add our first form and carry one workspace through validation, a protected POST request, the session, and a redirect.',
            'background' => [
                [
                    'label' => 'How HTML forms submit',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/form',
                ],
                [
                    'label' => 'Why state-changing requests need CSRF protection',
                    'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html',
                ],
            ],
        ],
        [
            'id' => '03',
            'slug' => 'persist-workspaces',
            'title' => 'Persist workspaces',
            'description' => 'Replace browser-session storage with a SQLite table, then write and read workspaces through DALT\'s database connection.',
            'background' => [
                [
                    'label' => 'SQLite is a single-file database',
                    'href' => 'https://www.sqlite.org/onefile.html',
                ],
                [
                    'label' => 'SQLite CREATE TABLE reference',
                    'href' => 'https://sqlite.org/lang_createtable.html',
                ],
            ],
        ],
        [
            'id' => '04',
            'slug' => 'open-a-workspace',
            'title' => 'Open a workspace',
            'description' => 'Turn each workspace into its own URL, load the matching database row, and return 404 when that workspace does not exist.',
            'background' => [
                [
                    'label' => 'How URLs identify resources',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Learn_web_development/Howto/Web_mechanics/What_is_a_URL',
                ],
                [
                    'label' => 'What HTTP 404 means',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/404',
                ],
            ],
        ],
        [
            'id' => '05',
            'slug' => 'create-a-project',
            'title' => 'Create a project',
            'description' => 'Model projects inside workspaces, create them through a protected form, and render each workspace\'s real project list.',
            'background' => [
                [
                    'label' => 'How SQLite creates an index',
                    'href' => 'https://sqlite.org/lang_createindex.html',
                ],
                [
                    'label' => 'How PostgreSQL enforces relationships',
                    'href' => 'https://www.postgresql.org/docs/current/tutorial-fk.html',
                ],
            ],
        ],
        [
            'id' => '06',
            'slug' => 'open-a-project',
            'title' => 'Open a project',
            'description' => 'Give projects nested URLs, require both workspace and project IDs to match, and establish the project\'s Issues screen.',
            'background' => [
                [
                    'label' => 'How SELECT and WHERE filter rows',
                    'href' => 'https://sqlite.org/lang_select.html',
                ],
            ],
        ],
        [
            'id' => '07',
            'slug' => 'create-an-issue',
            'title' => 'Create an issue',
            'description' => 'Store issues inside projects, validate a title and optional description together, and render each project\'s real issue list.',
            'background' => [
                [
                    'label' => 'How SQLite stores text values',
                    'href' => 'https://sqlite.org/datatype3.html',
                ],
                [
                    'label' => 'HTML textarea reference',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/textarea',
                ],
            ],
        ],
        [
            'id' => '08',
            'slug' => 'open-an-issue',
            'title' => 'Open an issue',
            'description' => 'Turn issue rows into links, verify the complete workspace-project-issue path, and render each issue on its own page.',
        ],
        [
            'id' => '09',
            'slug' => 'change-issue-status',
            'title' => 'Change issue status',
            'description' => 'Close and reopen issues through an explicit protected update while keeping detail pages and project lists in sync.',
            'background' => [
                [
                    'label' => 'SQLite UPDATE reference',
                    'href' => 'https://sqlite.org/lang_update.html',
                ],
            ],
        ],
        [
            'id' => '10',
            'slug' => 'edit-an-issue',
            'title' => 'Edit an issue',
            'description' => 'Add a dedicated edit page, preserve attempted values through validation, and update an issue without changing its status or identity.',
        ],
        [
            'id' => '11',
            'slug' => 'delete-an-issue',
            'title' => 'Delete an issue',
            'description' => 'Review an irreversible action, send a protected DELETE request, and remove only an issue inside its verified project.',
            'background' => [
                [
                    'label' => 'What an HTTP DELETE request means',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Methods/DELETE',
                ],
                [
                    'label' => 'SQLite DELETE reference',
                    'href' => 'https://www.sqlite.org/lang_delete.html',
                ],
            ],
        ],
        [
            'id' => '12',
            'slug' => 'move-project-screen-to-react',
            'title' => 'Move the project screen to React',
            'description' => 'Replace one server-rendered screen with React, TypeScript, and Tailwind while keeping DALT responsible for routes, validation, sessions, and the database.',
            'background' => [
                [
                    'label' => 'Add React to an existing project',
                    'href' => 'https://react.dev/learn/add-react-to-an-existing-project',
                ],
                [
                    'label' => 'Mount a React root',
                    'href' => 'https://react.dev/reference/react-dom/client/createRoot',
                ],
                [
                    'label' => 'Use Vite with a backend',
                    'href' => 'https://vite.dev/guide/backend-integration.html',
                ],
                [
                    'label' => 'Install Tailwind with Vite',
                    'href' => 'https://tailwindcss.com/docs/installation/using-vite',
                ],
            ],
        ],
        [
            'id' => '13',
            'slug' => 'load-project-issues-from-json',
            'title' => 'Load project issues from JSON',
            'description' => 'Create a nested DALT JSON endpoint, validate its response in TypeScript, and give the React issue list honest loading, failure, empty, and ready states.',
            'background' => [
                [
                    'label' => 'Synchronize React with an external system',
                    'href' => 'https://react.dev/learn/synchronizing-with-effects',
                ],
                [
                    'label' => 'Use the Fetch API',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API/Using_Fetch',
                ],
            ],
        ],
        [
            'id' => '14',
            'slug' => 'create-issues-without-reloading',
            'title' => 'Create issues without reloading',
            'description' => 'Post the React form to a protected DALT JSON endpoint, render server validation beside controlled fields, and add only the confirmed issue to the list.',
            'background' => [
                [
                    'label' => 'React controlled inputs',
                    'href' => 'https://react.dev/reference/react-dom/components/input',
                ],
                [
                    'label' => 'HTTP 201 Created',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/201',
                ],
                [
                    'label' => 'HTTP 422 Unprocessable Content',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/422',
                ],
            ],
        ],
        [
            'id' => '15',
            'slug' => 'give-react-real-locations',
            'title' => 'Give React real locations',
            'description' => 'Add nested client routes for the project and issue screens, load one issue through JSON, and make refresh and pasted deep URLs return the React shell.',
            'background' => [
                [
                    'label' => 'Install React Router in data mode',
                    'href' => 'https://reactrouter.com/start/data/installation',
                ],
                [
                    'label' => 'Nested route configuration',
                    'href' => 'https://reactrouter.com/start/data/routing',
                ],
            ],
        ],
    ],
];
