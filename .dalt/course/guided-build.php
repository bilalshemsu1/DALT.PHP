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
    ],
];
