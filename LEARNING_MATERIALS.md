# DALT Learning Materials

## Purpose of this document

This is a current overview of the two learning paths in the DALT repository. It is
written as a planning handoff for deciding what to study next and how the material
can be arranged over the coming year.

There are two separate paths:

1. **DALT Core** — backend and framework fundamentals.
2. **DALT Fullstack** — one complete application built across React, TypeScript,
   DALT/PHP, PostgreSQL, testing and Docker.

They use the same learning platform, but they are not one long prerequisite chain.
Fullstack can be started independently. Core is optional reference material when a
backend concept needs a deeper, framework-focused treatment.

## The two paths at a glance

| Path | Main purpose | Current size | Main result |
|---|---|---:|---|
| **DALT Core** | Understand how a backend framework, HTTP requests, databases, authentication, Docker and operations work | 19 lessons, 22 challenges | Strong backend and framework reasoning |
| **DALT Fullstack** | Build and explain a complete issue-tracker application from browser to database | 34 lessons, 12 build milestones, 7 capstone requirements | A tested, authenticated, containerized full-stack system |

## 1. DALT Core — backend and framework fundamentals

### What it teaches

DALT Core focuses on understanding the machinery underneath a web application rather
than hiding it behind a large framework. The learner studies how a request enters the
application, how it is routed and processed, how state and authentication work, and
how the application communicates with databases and infrastructure.

The Core material is organized into four areas:

| Area | Lessons | Topics |
|---|---:|---|
| **Foundation** | 8 | Request lifecycle, routing, middleware, authentication, database access, the DALT database layer, debugging, and framework contract testing |
| **Docker** | 5 | Docker basics, Dockerfiles, Compose, intermediate image/runtime patterns, and production patterns |
| **PostgreSQL** | 5 | First contact, core SQL/database work, advanced features, reliability, and advanced patterns |
| **Operations** | 1 | Observability and operating a running backend system |

### Core lesson sequence

The current 19 lessons are:

1. Request Lifecycle
2. Routing
3. Middleware
4. Authentication
5. Database
6. Docker Basics
7. Writing Dockerfiles
8. Docker Compose
9. PostgreSQL First Contact
10. PostgreSQL Core
11. DALT Database Layer
12. Docker Intermediate
13. PostgreSQL Advanced
14. Docker Production Patterns
15. PostgreSQL Reliability
16. Advanced PostgreSQL
17. Observability
18. Errors, Exceptions, and Debugging
19. Testing a Framework Contract

The roadmap is competency-based rather than calendar-based. The recommended early
foundation is:

```text
request lifecycle
  → request/response messages
  → routing
  → middleware
  → state and sessions
  → authentication and authorization
  → database boundary
  → migrations and database reliability
```

Docker and PostgreSQL then form deeper branches. The operations material follows once
there is a running system worth observing.

### How Core is studied

Each Core unit combines:

- reading the lesson and the relevant framework source;
- tracing a real request or database operation;
- predicting the result before running it;
- repairing a deliberately broken challenge;
- building a small transfer project;
- writing or interpreting a focused test; and
- completing a closed-book checkpoint.

The 22 challenges are intended to test diagnosis and behavior, not merely whether a
particular line of source code was copied. The current course records 20 challenges
with executable verification; two Dockerfile challenges retain documented
shape/source-based verification because a reliable image build is not available in
every learning environment.

Core also includes Laravel comparison material. The point is to understand the small
DALT mechanism first, then recognize the corresponding abstraction in Laravel.

## 2. DALT Fullstack — the complete application path

### What it teaches

Fullstack is a project-based track. The learner builds one growing team issue tracker
through the entire sequence:

```text
browser
  → React + TypeScript + Tailwind
  → HTTP / JSON
  → DALT / PHP
  → PostgreSQL
  → Docker Compose
```

The goal is not just to know individual tools. It is to reason about the complete path
of data and behavior across the browser, frontend, HTTP boundary, backend, database and
runtime environment.

### Fullstack progression

| Part | Theme | Lessons | What exists after the part |
|---:|---|---:|---|
| 00 | Web fundamentals | 2 | Understand the browser/server system and inspect HTTP behavior |
| 01 | Modern JavaScript | 2 | JavaScript readiness for application work |
| 02 | TypeScript foundations | 5 | Explicitly modeled application data and runtime boundaries |
| 03 | React foundations | 4 | The first local issue-tracker interface |
| 04 | React and server | 3 | React communicates with DALT through HTTP/JSON |
| 05 | DALT API and PostgreSQL | 3 | A persistent issue tracker with relational data |
| 06 | Testing, users and authentication | 3 | A multi-user protected application |
| 07 | React structure, routing and testing | 3 | A routed and tested authenticated frontend |
| 08 | Server and application state | 3 | Deliberate client-state/server-state boundaries |
| 09 | Advanced React and tooling | 2 | A maintainable, production-shaped frontend |
| 10 | Docker | 2 | The full system running under Docker Compose |
| 11 | PostgreSQL deeper | 2 | Search, performance, concurrency and isolation decisions |
| 12 | Capstone | 0 new theory | An audited, explained and hardened final system |

### Fullstack lesson themes

The 34 lessons cover:

- browser requests, forms, JSON, the network panel and single-page application
  behavior;
- JavaScript data transformations, modules, asynchronous work and failure handling;
- TypeScript's mental model, domain modeling, unions, narrowing, generics and runtime
  validation boundaries;
- React components, JSX, typed props, state, events, forms, Tailwind and accessible
  UI;
- fetching and mutating server data, effects, transport boundaries and API design;
- relational modeling, migrations, CRUD queries and transaction boundaries in DALT and
  PostgreSQL;
- backend behavior tests, users, password storage, sessions, cookies, CSRF,
  authorization and ownership;
- React Router, frontend authentication, frontend behavior testing and navigation;
- client state versus server state, invalidation, optimistic UI, reducers, context and
  a bounded comparison with Zustand;
- custom hooks, feature boundaries, build-pipeline configuration and failure
  boundaries;
- containers, image builds, health checks and runtime debugging; and
- PostgreSQL query performance, indexes, database capabilities, transactions,
  concurrency and row-level isolation.

### The build milestones

Fullstack is not only a sequence of readings. After each part, the learner applies the
ideas to the same project:

| Milestone | Result |
|---|---|
| B00 | Trace the browser/server system |
| B01 | JavaScript readiness |
| B02 | Type the future application |
| B03 | Build the local issue tracker |
| B04 | Complete the first full-stack loop |
| B05 | Add persistence and a real application API |
| B06 | Protect the system for multiple users |
| B07 | Make the frontend navigable and tested |
| B08 | Establish intentional state architecture |
| B09 | Make the frontend maintainable |
| B10 | Containerize the full stack |
| B11 | Make database-aware application decisions |

Part 12 is the capstone rather than another theory section. It asks the learner to:

1. audit the system;
2. complete an end-to-end issue workflow;
3. harden failure paths;
4. harden the tests;
5. review performance and database behavior;
6. compare the learned mechanisms with Laravel; and
7. explain and freeze the learner-built result.

## How the two paths relate

The clearest way to explain the relationship is:

| Question | DALT Core | DALT Fullstack |
|---|---|---|
| Primary focus | How backend/framework mechanisms work | How a complete product works across all layers |
| Learning shape | Competency nodes, source tracing and repair challenges | Sequential parts, cumulative builds and capstone review |
| Main application | Small focused exercises and transfer projects | One growing issue tracker |
| Backend depth | DALT internals, HTTP, sessions, auth, database, Docker, PostgreSQL, operations | Enough DALT, database, testing and infrastructure knowledge to support the full application |
| Frontend depth | Not the focus | React, TypeScript, Tailwind, routing, state and frontend testing |
| Prerequisite relationship | Optional reference | Standalone path; Core is not required first |

Fullstack intentionally teaches its own required backend, Docker and PostgreSQL
material. A learner should not be blocked because Core has not been completed.
Conversely, Core remains valuable when the goal is to understand DALT itself in more
detail, or to study a backend concept away from the larger project.

## Suggested planning conversation for the next year

The mentor can choose between three reasonable approaches:

### Option A — Fullstack as the main spine

Use Fullstack Parts 00–12 as the year-long sequence. Pull in a Core lesson only when a
backend concept needs extra depth or when the learner wants a separate framework
exercise.

This is the most direct route toward a portfolio-quality full-stack system.

### Option B — Core first, then Fullstack

Start with the Core foundation and selected Docker/PostgreSQL lessons, then begin
Fullstack once the backend mental model is comfortable.

This gives stronger framework fundamentals before the React and application-building
load, but it is a longer route and is not required by the curriculum.

### Option C — Parallel study

Follow Fullstack as the main project path while using Core as a deliberate side track:

```text
Fullstack project progression
        +
Core source-tracing / backend depth
        +
regular testing, debugging and explanation checkpoints
```

This makes sense if the year needs both visible project progress and deep backend
understanding.

## Current repository status

As of **17 August 2026**:

- **DALT Core:** the 19-lesson course and its 22 challenges are present, with the
  hardening/freeze work recorded in the repository.
- **DALT Fullstack:** all declared Parts 00–12 are authored: 34 of 34 lessons and 19
  build specifications are present.
- The Fullstack build specifications cover B00–B11 and C01–C07.
- The next repository-level concern is audit/freeze work, not curriculum design.

## Where the material lives

- [DALT Core competency roadmap](documentation/competency-roadmap.md)
- [Core lesson catalog](.dalt/course/lessons/README.md)
- [Fullstack track manifest](.dalt/course/fullstack.php)
- [Fullstack curriculum](docs/dalt-fullstack/CURRICULUM.md)
- [Fullstack project blueprint](docs/dalt-fullstack/PROJECT_BLUEPRINT.md)
- [Fullstack implementation status](docs/dalt-fullstack/START_HERE.md)
- [Core hardening status](docs/hardening/START_HERE.md)
