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
    ],
];
