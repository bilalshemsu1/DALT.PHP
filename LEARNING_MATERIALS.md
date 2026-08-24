# DALT Learning Paths

DALT includes three separate learning surfaces. They share the same local learning
platform, but they are not one long prerequisite chain. Choose the path that matches
what you want to learn, and use the others as references when they become useful.

Open the learning platform at `http://localhost:8000/learn` after starting DALT.

## At a glance

| Path | What it teaches | Size | Best for |
|---|---|---:|---|
| **DALT Core** | HTTP, framework internals, authentication, databases, Docker, PostgreSQL, testing, and debugging | 19 lessons and 22 challenges | Understanding what a backend framework does |
| **Fullstack theory** | Web foundations, JavaScript, TypeScript, React, Tailwind CSS, APIs, authentication, testing, state, Docker, and PostgreSQL | 60 lessons across Parts 00–11, followed by a Part 12 capstone | Learning each layer through focused explanations and experiments |
| **DALT Build** | One issue-tracking application built from an empty DALT project into a tested, authenticated, containerized system | 71 guided lessons | Learning by building a continuous real application |

Fullstack theory also includes 12 build milestones and seven capstone specifications.
Those milestones summarize what to build after a theory part. They are different from
the independent, step-by-step DALT Build course at `/learn/build`.

## Recommended order

If React, TypeScript, Docker, and PostgreSQL are new to you:

1. Read the matching Fullstack theory lessons before you first meet a technology.
2. Follow DALT Build in order and create the issue tracker yourself.
3. Open a DALT Core lesson when you want to understand the backend mechanism more
   deeply.
4. Use the challenges after their related Core lessons to test your understanding.

You can also take DALT Core first if your main goal is backend and framework
understanding. An experienced full-stack developer can begin directly with DALT Build
and use theory or Core only when a concept is unfamiliar.

## DALT Core

DALT Core explains the small framework underneath the application. Its 19 lessons
cover:

1. request lifecycle;
2. routing;
3. middleware;
4. authentication;
5. database access;
6. Docker basics;
7. Dockerfiles;
8. Docker Compose;
9. PostgreSQL first contact;
10. PostgreSQL core concepts;
11. the DALT database layer;
12. intermediate Docker;
13. advanced PostgreSQL;
14. production container patterns;
15. PostgreSQL reliability;
16. deeper PostgreSQL patterns;
17. observability;
18. errors, exceptions, and debugging;
19. testing framework contracts.

The 22 challenges create deliberately broken states. A challenge is useful only when
you first observe the failure, form a hypothesis, make a genuine correction, and let
the verifier test behavior.

```bash
php artisan challenge:list
php artisan challenge:start broken-routing
php artisan challenge:verify
php artisan challenge:stop
```

## Fullstack theory

Fullstack theory teaches one coherent idea per lesson and uses small disposable
experiments rather than one continuous application.

| Part | Focus |
|---:|---|
| 00 | Web foundations: documents, forms, HTTP, JSON, and SPA behavior |
| 01 | Modern JavaScript: data, functions, modules, promises, and failure |
| 02 | TypeScript: everyday types, application models, narrowing, generics, and runtime boundaries |
| 03 | React and Tailwind CSS: components, props, lists, state, forms, responsive UI, and accessibility |
| 04 | React and the server: reads, mutations, loading, failure, races, and transport boundaries |
| 05 | Modern PHP, DALT APIs, relational modeling, migrations, SQL, and transactions |
| 06 | Backend tests, users, password hashing, sessions, CSRF, and authorization |
| 07 | React Router, frontend authentication, and behavior tests |
| 08 | Client state, server state, TanStack Query, context, reducers, and Zustand |
| 09 | Custom hooks, feature boundaries, builds, configuration, and error boundaries |
| 10 | Containers, Dockerfiles, caching, Compose, health, and runtime safety |
| 11 | PostgreSQL plans, indexes, search, JSONB, concurrency, roles, and row-level security |
| 12 | Capstone audit, hardening, explanation, and freeze specifications |

The milestone pages teach and specify a result; they do not collect answers or modify
the learner's project automatically.

## DALT Build

DALT Build is the continuous project course. It begins with a clean DALT application
and grows one issue tracker in the order real product development creates each need.
The sequence includes:

- local project setup and version control;
- workspaces, projects, and issues;
- persistence and relational schema design;
- React, TypeScript, Tailwind CSS, routing, and forms;
- editing, deletion, validation, and useful failure states;
- PostgreSQL through Docker Compose;
- registration, login, server-side sessions, memberships, roles, and invitations;
- assignees, priorities, due dates, labels, comments, and activity history;
- search, filtering, sorting, pagination, dashboards, and member management;
- tests, application containers, health checks, production builds, migrations,
  configuration, secrets, logging, accessibility, and performance.

The lesson shows the focused code for the current slice, explains why it belongs
there, and leaves you with a working application when followed from the beginning.

## How the paths work together

- Use **Fullstack theory** to understand a technology in a small experiment.
- Use **DALT Build** to apply it in a growing product.
- Use **DALT Core** to inspect the backend mechanism and framework boundary.
- Use **challenges** to prove that you can diagnose behavior rather than repeat prose.

Progress is stored separately for each surface. Completing one path never silently
marks another as complete.

## Where to go next

- Start DALT and visit `/learn` for the complete catalog.
- Visit `/learn/fullstack` for Fullstack theory and its milestones.
- Visit `/learn/build` for the independent guided project course.
- Read the [Competency Roadmap](documentation/competency-roadmap.md) for a deeper
  backend study graph.
- Read [Sources and Attribution](documentation/sources-and-attribution.md) for the
  curriculum research and technical-source policy.

All learning-platform material is optional. Run
`php artisan platform:remove --force` when you intentionally want to keep only the
framework and your application files.
