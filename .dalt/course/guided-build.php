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
        [
            'id' => '16',
            'slug' => 'change-issue-status-without-reloading',
            'title' => 'Change issue status without reloading',
            'description' => 'Send an explicit protected status transition from the routed issue screen and replace its state only with DALT\'s confirmed response.',
            'background' => [
                [
                    'label' => 'React state as a snapshot',
                    'href' => 'https://react.dev/learn/state-as-a-snapshot',
                ],
            ],
        ],
        [
            'id' => '17',
            'slug' => 'edit-issues-without-reloading',
            'title' => 'Edit issues without reloading',
            'description' => 'Move the dedicated edit URL into React, preserve attempted values through JSON validation, and return to the detail only after DALT confirms the update.',
            'background' => [
                [
                    'label' => 'React Router navigation',
                    'href' => 'https://reactrouter.com/api/hooks/useNavigate',
                ],
            ],
        ],
        [
            'id' => '18',
            'slug' => 'delete-issues-without-reloading',
            'title' => 'Delete issues without reloading',
            'description' => 'Review a routed destructive action, send a protected DELETE through DALT, and leave the removed issue URL only after the server confirms deletion.',
            'background' => [
                [
                    'label' => 'HTTP DELETE semantics',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Methods/DELETE',
                ],
                [
                    'label' => 'Replace browser history during navigation',
                    'href' => 'https://reactrouter.com/api/hooks/useNavigate',
                ],
            ],
        ],
        [
            'id' => '19',
            'slug' => 'test-the-react-issue-workflow',
            'title' => 'Test the React issue workflow',
            'description' => 'Drive the routed UI through accessible controls while Mock Service Worker supplies realistic loading, validation, mutation, and deletion responses.',
            'background' => [
                [
                    'label' => 'Testing Library guiding principle',
                    'href' => 'https://testing-library.com/docs/react-testing-library/intro/',
                ],
                [
                    'label' => 'Mock Service Worker',
                    'href' => 'https://mswjs.io/',
                ],
                [
                    'label' => 'React Router memory router',
                    'href' => 'https://reactrouter.com/api/data-routers/createMemoryRouter',
                ],
            ],
        ],
        [
            'id' => '20',
            'slug' => 'test-the-dalt-issue-api',
            'title' => 'Test the DALT issue API',
            'description' => 'Dispatch the real issue routes against an isolated SQLite database and prove JSON contracts, CSRF, validation, ownership, and every mutation.',
            'background' => [
                [
                    'label' => 'Pest hooks',
                    'href' => 'https://pestphp.com/docs/hooks',
                ],
                [
                    'label' => 'Pest expectations',
                    'href' => 'https://pestphp.com/docs/expectations/',
                ],
                [
                    'label' => 'PDO SQLite in-memory databases',
                    'href' => 'https://www.php.net/manual/en/ref.pdo-sqlite.connection.php',
                ],
            ],
        ],
        [
            'id' => '21',
            'slug' => 'move-the-workspace-list-to-react',
            'title' => 'Move the workspace list to React',
            'description' => 'Replace the PHP-rendered home collection with a runtime-checked workspace API and a responsive React screen with loading, empty, failure, retry, and ready states.',
            'background' => [
                [
                    'label' => 'Fetching data with React effects',
                    'href' => 'https://react.dev/reference/react/useEffect#fetching-data-with-effects',
                ],
                [
                    'label' => 'AbortController',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/API/AbortController',
                ],
                [
                    'label' => 'Tailwind responsive design',
                    'href' => 'https://tailwindcss.com/docs/responsive-design',
                ],
            ],
        ],
        [
            'id' => '22',
            'slug' => 'create-workspaces-without-reloading',
            'title' => 'Create workspaces without reloading',
            'description' => 'Send the protected workspace form through JSON, preserve rejected input, and add only a server-confirmed workspace to React state without navigating away.',
            'background' => [
                [
                    'label' => 'React updating arrays in state',
                    'href' => 'https://react.dev/learn/updating-arrays-in-state',
                ],
                [
                    'label' => 'HTTP 422',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/422',
                ],
                [
                    'label' => 'URLSearchParams',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/API/URLSearchParams',
                ],
            ],
        ],
        [
            'id' => '23',
            'slug' => 'move-the-project-list-to-react',
            'title' => 'Move the project list to React',
            'description' => 'Preserve the workspace deep-link 404 while React loads its scoped project collection, real issue counts, and complete request states from JSON.',
            'background' => [
                [
                    'label' => 'React synchronizing with effects',
                    'href' => 'https://react.dev/learn/synchronizing-with-effects',
                ],
                [
                    'label' => 'SQL COUNT',
                    'href' => 'https://www.postgresql.org/docs/current/functions-aggregate.html',
                ],
            ],
        ],
        [
            'id' => '24',
            'slug' => 'create-projects-without-reloading',
            'title' => 'Create projects without reloading',
            'description' => 'Create a project through a protected nested JSON endpoint, preserve rejected input, and prepend only the server-confirmed row without leaving the workspace.',
            'background' => [
                [
                    'label' => 'React updating arrays in state',
                    'href' => 'https://react.dev/learn/updating-arrays-in-state',
                ],
                [
                    'label' => 'HTTP 201',
                    'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/201',
                ],
            ],
        ],
        [
            'id' => '25',
            'slug' => 'edit-workspaces-without-reloading',
            'title' => 'Edit workspaces without reloading',
            'description' => 'Rename a workspace through a dedicated client route, preserve rejected input, and return with the server-confirmed name while refresh reads the persisted value.',
            'background' => [
                ['label' => 'React Router navigation', 'href' => 'https://reactrouter.com/api/hooks/useNavigate'],
                ['label' => 'React input state', 'href' => 'https://react.dev/reference/react-dom/components/input'],
            ],
        ],
        [
            'id' => '26',
            'slug' => 'delete-workspaces-after-review',
            'title' => 'Delete workspaces after review',
            'description' => 'Review the full consequence, then remove a workspace, its projects, and their issues in one protected transaction before returning home.',
            'background' => [
                ['label' => 'PDO transactions', 'href' => 'https://www.php.net/manual/en/pdo.transactions.php'],
                ['label' => 'HTTP DELETE', 'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Methods/DELETE'],
            ],
        ],
        [
            'id' => '27',
            'slug' => 'edit-projects-without-reloading',
            'title' => 'Edit projects without reloading',
            'description' => 'Rename a workspace-owned project on a dedicated client route, preserve rejected input, and return with the confirmed name without disturbing its issues.',
            'background' => [
                ['label' => 'React Router location state', 'href' => 'https://reactrouter.com/api/hooks/useLocation'],
                ['label' => 'Controlled React inputs', 'href' => 'https://react.dev/reference/react-dom/components/input'],
            ],
        ],
        [
            'id' => '28',
            'slug' => 'delete-projects-after-review',
            'title' => 'Delete projects after review',
            'description' => 'Review a project deletion, remove its issues in one protected transaction, preserve the workspace, and return only after server confirmation.',
            'background' => [
                ['label' => 'PDO transactions', 'href' => 'https://www.php.net/manual/en/pdo.transactions.php'],
                ['label' => 'HTTP DELETE', 'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Methods/DELETE'],
            ],
        ],
        [
            'id' => '29',
            'slug' => 'register-an-account',
            'title' => 'Register an account',
            'description' => 'Create the first React authentication screen, validate a new account, store only a password hash, and begin a rotated server session.',
            'background' => [
                ['label' => 'PHP password_hash', 'href' => 'https://www.php.net/manual/en/function.password-hash.php'],
                ['label' => 'OWASP session management', 'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html'],
            ],
        ],
        [
            'id' => '30',
            'slug' => 'log-in-and-out',
            'title' => 'Log in and out',
            'description' => 'Verify an existing account, return to an intended page, show session identity in the React shell, and destroy the server session on logout.',
            'background' => [
                ['label' => 'PHP password_verify', 'href' => 'https://www.php.net/manual/en/function.password-verify.php'],
                ['label' => 'OWASP authentication guidance', 'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html'],
            ],
        ],
        [
            'id' => '31',
            'slug' => 'protect-private-routes',
            'title' => 'Protect private routes',
            'description' => 'Redirect guest page navigation through login, return JSON 401 for guest API calls, and recover safely when a live session expires.',
            'background' => [
                ['label' => 'HTTP 401 Unauthorized', 'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/401'],
                ['label' => 'OWASP session lifecycle', 'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html'],
            ],
        ],
        [
            'id' => '32',
            'slug' => 'own-every-workspace',
            'title' => 'Own every workspace',
            'description' => 'Migrate existing work safely, assign new workspaces from session identity, and enforce ownership through every nested project and issue path.',
            'background' => [
                ['label' => 'OWASP IDOR prevention', 'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html'],
                ['label' => 'SQLite ALTER TABLE', 'href' => 'https://www.sqlite.org/lang_altertable.html'],
            ],
        ],
        [
            'id' => '33',
            'slug' => 'prove-account-isolation',
            'title' => 'Prove account isolation',
            'description' => 'Test reciprocal own-account success, cross-account refusal, unchanged durable state, and server-derived ownership across the full hierarchy.',
            'background' => [
                ['label' => 'OWASP authorization testing', 'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html'],
                ['label' => 'Pest data sets', 'href' => 'https://pestphp.com/docs/datasets'],
            ],
        ],
        [
            'id' => '34',
            'slug' => 'start-postgresql-with-compose',
            'title' => 'Start PostgreSQL with Docker Compose',
            'description' => 'Run a healthy PostgreSQL 18 development service, keep its data in a named volume, and prove persistence before DALT connects to it.',
            'background' => [
                ['label' => 'Docker Compose startup order', 'href' => 'https://docs.docker.com/compose/how-tos/startup-order/'],
                ['label' => 'Official PostgreSQL image', 'href' => 'https://hub.docker.com/_/postgres'],
            ],
        ],
        [
            'id' => '35',
            'slug' => 'connect-dalt-to-postgresql',
            'title' => 'Connect DALT to PostgreSQL',
            'description' => 'Configure DALT through environment variables, require the PDO PostgreSQL extension, and prove the real driver, database, user, and server version.',
            'background' => [
                ['label' => 'PHP PDO PostgreSQL driver', 'href' => 'https://www.php.net/manual/en/ref.pdo-pgsql.php'],
                ['label' => 'Docker Compose environment variables', 'href' => 'https://docs.docker.com/compose/how-tos/environment-variables/variable-interpolation/'],
            ],
        ],
        [
            'id' => '36',
            'slug' => 'port-the-schema-to-postgresql',
            'title' => 'Port the schema to PostgreSQL',
            'description' => 'Replace the SQLite dialect with explicit PostgreSQL identity columns and timezone-aware timestamps, then prove ordered migration history.',
            'background' => [
                ['label' => 'PostgreSQL identity columns', 'href' => 'https://www.postgresql.org/docs/current/ddl-identity-columns.html'],
                ['label' => 'PostgreSQL date and time types', 'href' => 'https://www.postgresql.org/docs/current/datatype-datetime.html'],
            ],
        ],
        [
            'id' => '37',
            'slug' => 'let-postgresql-defend-the-data',
            'title' => 'Let PostgreSQL defend the data',
            'description' => 'Add named foreign keys, checks, delete behavior, and query-shaped indexes, then bypass PHP to prove invalid rows cannot exist.',
            'background' => [
                ['label' => 'PostgreSQL constraints', 'href' => 'https://www.postgresql.org/docs/current/ddl-constraints.html'],
                ['label' => 'PostgreSQL multicolumn indexes', 'href' => 'https://www.postgresql.org/docs/current/indexes-multicolumn.html'],
            ],
        ],
        [
            'id' => '38',
            'slug' => 'import-the-sqlite-data',
            'title' => 'Import the SQLite data',
            'description' => 'Copy the existing accounts and issue hierarchy in one PostgreSQL transaction, verify counts, repair identity sequences, and prove failure rolls back.',
            'background' => [
                ['label' => 'PDO transactions', 'href' => 'https://www.php.net/manual/en/pdo.transactions.php'],
                ['label' => 'PostgreSQL identity sequence functions', 'href' => 'https://www.postgresql.org/docs/current/functions-sequence.html'],
            ],
        ],
        [
            'id' => '39',
            'slug' => 'test-the-application-on-postgresql',
            'title' => 'Test the application on PostgreSQL',
            'description' => 'Rebuild a guarded PostgreSQL test database from every migration and prove authentication, CRUD, CSRF, and reciprocal authorization on the real engine.',
            'background' => [
                ['label' => 'Pest beforeEach hooks', 'href' => 'https://pestphp.com/docs/hooks'],
                ['label' => 'PostgreSQL schemas', 'href' => 'https://www.postgresql.org/docs/current/ddl-schemas.html'],
            ],
        ],
        [
            'id' => '40',
            'slug' => 'make-multi-row-work-atomic',
            'title' => 'Make multi-row work atomic',
            'description' => 'Centralize the PDO transaction lifecycle without hiding business SQL, then force a late PostgreSQL failure and prove every earlier change rolls back.',
            'background' => [
                ['label' => 'PDO transactions', 'href' => 'https://www.php.net/manual/en/pdo.transactions.php'],
                ['label' => 'PostgreSQL transaction isolation', 'href' => 'https://www.postgresql.org/docs/current/transaction-iso.html'],
            ],
        ],
        [
            'id' => '41',
            'slug' => 'restore-the-react-session',
            'title' => 'Restore the React session',
            'description' => 'Load the signed-in user and CSRF token from DALT, model every request state in one React provider, and remove duplicated identity from page bootstrap data.',
            'background' => [
                ['label' => 'React context', 'href' => 'https://react.dev/learn/passing-data-deeply-with-context'],
                ['label' => 'Using effects for network synchronization', 'href' => 'https://react.dev/reference/react/useEffect#fetching-data-with-effects'],
            ],
        ],
        [
            'id' => '42',
            'slug' => 'replace-ownership-with-memberships',
            'title' => 'Replace ownership with memberships',
            'description' => 'Backfill every existing owner into a constrained join table, make workspace creation atomic, and use membership as the only source of workspace access.',
            'background' => [
                ['label' => 'PostgreSQL foreign keys', 'href' => 'https://www.postgresql.org/docs/current/ddl-constraints.html#DDL-CONSTRAINTS-FK'],
                ['label' => 'PostgreSQL multicolumn indexes', 'href' => 'https://www.postgresql.org/docs/current/indexes-multicolumn.html'],
            ],
        ],
        [
            'id' => '43',
            'slug' => 'authorize-owner-and-member-work',
            'title' => 'Authorize owner and member work',
            'description' => 'Turn membership roles into one server capability policy, let members collaborate on projects and issues, and reserve workspace administration for owners.',
            'background' => [
                ['label' => 'OWASP authorization guidance', 'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html'],
                ['label' => 'HTTP 403 Forbidden', 'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/403'],
            ],
        ],
        [
            'id' => '44',
            'slug' => 'show-the-member-directory',
            'title' => 'Show the member directory',
            'description' => 'Add a member-safe JSON collection and React screen with public fields, accessible role labels, complete request states, and shared workspace navigation.',
            'background' => [
                ['label' => 'React conditional rendering', 'href' => 'https://react.dev/learn/conditional-rendering'],
                ['label' => 'Accessible names and descriptions', 'href' => 'https://www.w3.org/WAI/ARIA/apg/practices/names-and-descriptions/'],
            ],
        ],
        [
            'id' => '45',
            'slug' => 'create-secure-invitation-links',
            'title' => 'Create secure invitation links',
            'description' => 'Let owners create expiring copyable invitations while storing only token hashes, preventing duplicate pending invites, and keeping invitation management owner-only.',
            'background' => [
                ['label' => 'PHP random_bytes', 'href' => 'https://www.php.net/manual/en/function.random-bytes.php'],
                ['label' => 'OWASP forgot-password token guidance', 'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html'],
            ],
        ],
        [
            'id' => '46',
            'slug' => 'accept-an-invitation-once',
            'title' => 'Accept an invitation once',
            'description' => 'Preserve invitation destinations through authentication, verify the token and matching email, then lock and accept membership safely under retries.',
            'background' => [
                ['label' => 'PostgreSQL explicit locking', 'href' => 'https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-ROWS'],
                ['label' => 'PHP hash', 'href' => 'https://www.php.net/manual/en/function.hash.php'],
            ],
        ],
        [
            'id' => '47',
            'slug' => 'manage-members-without-losing-an-owner',
            'title' => 'Manage members without losing an owner',
            'description' => 'Promote, demote, remove, leave, and revoke safely by locking membership state and rejecting every operation that would orphan a workspace.',
            'background' => [
                ['label' => 'PostgreSQL row-level locks', 'href' => 'https://www.postgresql.org/docs/current/explicit-locking.html#LOCKING-ROWS'],
                ['label' => 'OWASP authorization testing', 'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Testing_Automation_Cheat_Sheet.html'],
            ],
        ],
        [
            'id' => '48',
            'slug' => 'assign-issues-to-members',
            'title' => 'Assign issues to workspace members',
            'description' => 'Give an issue one responsible person, keep unassigned an honest state, and prove the chosen member belongs to the workspace instead of trusting a submitted ID.',
            'background' => [
                ['label' => 'PostgreSQL constraints', 'href' => 'https://www.postgresql.org/docs/18/ddl-constraints.html'],
            ],
        ],
        [
            'id' => '49',
            'slug' => 'add-priority-and-due-dates',
            'title' => 'Add priority and due dates',
            'description' => 'Sequence work with a constrained priority and a nullable calendar date, keeping a missing deadline different from an invented one.',
            'background' => [
                ['label' => 'PostgreSQL date/time types', 'href' => 'https://www.postgresql.org/docs/18/datatype-datetime.html'],
            ],
        ],
        [
            'id' => '50',
            'slug' => 'organize-issues-with-labels',
            'title' => 'Organize issues with labels',
            'description' => 'Give each workspace its own label vocabulary, attach many labels to an issue through a composite join table, and keep refreshed label URLs durable.',
            'background' => [
                ['label' => 'PostgreSQL constraints', 'href' => 'https://www.postgresql.org/docs/18/ddl-constraints.html'],
            ],
        ],
        [
            'id' => '51',
            'slug' => 'discuss-work-with-comments',
            'title' => 'Discuss work with comments',
            'description' => 'Add a chronological conversation whose author comes from the authenticated session rather than a browser-supplied user ID.',
            'background' => [
                ['label' => 'PostgreSQL foreign keys', 'href' => 'https://www.postgresql.org/docs/18/ddl-constraints.html#DDL-CONSTRAINTS-FK'],
            ],
        ],
        [
            'id' => '52',
            'slug' => 'record-meaningful-activity',
            'title' => 'Record meaningful activity history',
            'description' => 'Write one append-only timeline of structured events inside the same transaction as the change it describes, so history cannot drift from the data.',
            'background' => [
                ['label' => 'PostgreSQL transactions', 'href' => 'https://www.postgresql.org/docs/18/tutorial-transactions.html'],
            ],
        ],
        [
            'id' => '53',
            'slug' => 'search-filter-and-sort-issues',
            'title' => 'Search, filter, and sort issues',
            'description' => 'Make a growing issue list searchable and filterable, with every choice held in the URL so a refresh or copied link opens the same view.',
            'background' => [
                ['label' => 'PostgreSQL pattern matching', 'href' => 'https://www.postgresql.org/docs/18/functions-matching.html'],
            ],
        ],
        [
            'id' => '54',
            'slug' => 'paginate-a-deterministic-result',
            'title' => 'Paginate a deterministic issue result',
            'description' => 'Return bounded pages under a predictable ordering, with links that preserve the active search, filters, sorting, and page size.',
            'background' => [
                ['label' => 'PostgreSQL LIMIT and OFFSET', 'href' => 'https://www.postgresql.org/docs/18/queries-limit.html'],
            ],
        ],
        [
            'id' => '55',
            'slug' => 'cache-server-reads-with-tanstack-query',
            'title' => 'Cache server reads with TanStack Query',
            'description' => 'Give repeated server reads one cache identity each, while form values stay local and identity stays in the session provider.',
            'background' => [
                ['label' => 'TanStack Query keys', 'href' => 'https://tanstack.com/query/latest/docs/framework/react/guides/query-keys'],
            ],
        ],
        [
            'id' => '56',
            'slug' => 'invalidate-after-confirmed-mutations',
            'title' => 'Invalidate after confirmed mutations',
            'description' => 'Move the issue workflow to useMutation and refresh only the cached resources a confirmed server change can make stale.',
            'background' => [
                ['label' => 'TanStack Query invalidation', 'href' => 'https://tanstack.com/query/latest/docs/framework/react/guides/invalidations-from-mutations'],
            ],
        ],
        [
            'id' => '57',
            'slug' => 'build-membership-scoped-dashboards',
            'title' => 'Build membership-scoped dashboard views',
            'description' => 'Gather assigned, overdue, and recent work across every workspace the signed-in user may actually open, scoped by membership on the server.',
            'background' => [
                ['label' => 'PostgreSQL joins', 'href' => 'https://www.postgresql.org/docs/18/queries-table-expressions.html'],
            ],
        ],
        [
            'id' => '58',
            'slug' => 'make-empty-and-denied-states-intentional',
            'title' => 'Make empty and denied states intentional',
            'description' => 'Separate loading, first-use empty, filtered empty, recoverable failure, missing, and denied, and give API errors one machine-readable envelope.',
            'background' => [
                ['label' => 'WAI-ARIA status role', 'href' => 'https://www.w3.org/TR/wai-aria-1.2/#status'],
                ['label' => 'MDN HTTP response status codes', 'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Status'],
            ],
        ],
        [
            'id' => '59',
            'slug' => 'expand-postgresql-integration-coverage',
            'title' => 'Expand PostgreSQL integration coverage',
            'description' => 'Replace copied fixtures with schema-honest factories, describe every role in one policy matrix, and add the constraint, rollback, pagination, and query-count cases a status code cannot see.',
            'background' => [
                ['label' => 'PostgreSQL constraints', 'href' => 'https://www.postgresql.org/docs/18/ddl-constraints.html'],
                ['label' => 'OWASP authorization testing', 'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Testing_Automation_Cheat_Sheet.html'],
            ],
        ],
        [
            'id' => '60',
            'slug' => 'test-the-assembled-application-in-a-browser',
            'title' => 'Test the assembled application in a browser',
            'description' => 'Drive real journeys with Playwright against a real DALT server and its own test database: registration, invitation acceptance, member permissions, comments, filtering, pagination, deep links, and logout.',
            'background' => [
                ['label' => 'Playwright locators', 'href' => 'https://playwright.dev/docs/locators'],
                ['label' => 'Playwright test isolation', 'href' => 'https://playwright.dev/docs/browser-contexts'],
            ],
        ],
        [
            'id' => '61',
            'slug' => 'define-production-configuration-and-secrets',
            'title' => 'Define production configuration and secrets',
            'description' => 'Separate checked-in defaults from deployment-supplied secrets, refuse to serve a misconfigured production boot, keep the reason out of the response, and scan for secrets that reached somewhere public.',
            'background' => [
                ['label' => 'OWASP secrets management', 'href' => 'https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html'],
                ['label' => 'MDN Set-Cookie Secure', 'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#secure'],
            ],
        ],
        [
            'id' => '62',
            'slug' => 'produce-the-frontend-once-for-production',
            'title' => 'Produce the frontend once for production',
            'description' => 'Put the full gate in front of the artifact, then verify the build is complete, content-hashed, current, and free of source maps and dev-server references.',
            'background' => [
                ['label' => 'Vite build guide', 'href' => 'https://vite.dev/guide/build.html'],
                ['label' => 'MDN Cache-Control', 'href' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control'],
            ],
        ],
        [
            'id' => '63',
            'slug' => 'containerize-the-complete-web-application',
            'title' => 'Containerize the complete web application',
            'description' => 'Ship one image that carries the code, dependencies, and built assets and none of the tools that made them, run it as a non-root user, and prove every route and asset through the production entrypoint.',
            'background' => [
                ['label' => 'Docker multi-stage builds', 'href' => 'https://docs.docker.com/build/building/multi-stage/'],
                ['label' => 'Compose environment interpolation', 'href' => 'https://docs.docker.com/compose/how-tos/environment-variables/variable-interpolation/'],
            ],
        ],
        [
            'id' => '64',
            'slug' => 'distinguish-liveness-from-readiness',
            'title' => 'Distinguish liveness from readiness',
            'description' => 'Answer "restart me" and "give me a moment" with two different endpoints, keep the failure reason out of an unauthenticated response, and wire the right probe into the container.',
            'background' => [
                ['label' => 'Compose startup order', 'href' => 'https://docs.docker.com/compose/how-tos/startup-order/'],
                ['label' => 'Dockerfile HEALTHCHECK', 'href' => 'https://docs.docker.com/reference/dockerfile/#healthcheck'],
            ],
        ],
    ],
];
