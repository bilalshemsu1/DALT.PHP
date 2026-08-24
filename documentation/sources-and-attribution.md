# Sources and Attribution

DALT's framework, lessons, examples, exercises, and guided issue-tracker project are
written for this repository. External material is used to verify technical behavior
and to learn from established teaching approaches; it is not copied in place of DALT
authorship.

## Full Stack Open

DALT Fullstack's curriculum progression and exercise philosophy are heavily inspired
by the University of Helsinki's [Full Stack Open](https://fullstackopen.com/en/)
course. DALT uses an independently written PHP, DALT, React, TypeScript, Tailwind CSS,
Docker, and PostgreSQL curriculum and a different project while learning from Full
Stack Open's sequencing and pedagogical structure.

Full Stack Open identifies its material as
[Creative Commons Attribution-NonCommercial-ShareAlike 3.0](https://creativecommons.org/licenses/by-nc-sa/3.0/).
DALT does not claim affiliation with or endorsement by the University of Helsinki or
the Full Stack Open authors. The curriculum research was performed from 13–14 August
2026 and technical details were rechecked while the concise lessons were written from
22–23 August 2026.

Before the v1 release, representative lessons on HTTP, React components,
authentication, and TanStack Query were compared with the corresponding Full Stack
Open pages. The longest identical runs were four to eight words and were ordinary API
or import syntax. No copied explanatory passage or exercise sequence was found in
that sample.

## Technical sources

Technical claims are checked against the project code and tests first, then the
official documentation for the technology involved:

- Web platform: [MDN Web Docs](https://developer.mozilla.org/en-US/docs/Web)
- HTTP semantics: [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110)
- JavaScript and browser APIs: [MDN JavaScript](https://developer.mozilla.org/en-US/docs/Web/JavaScript)
- TypeScript: [TypeScript Handbook](https://www.typescriptlang.org/docs/handbook/intro.html)
- React: [React documentation](https://react.dev/learn)
- React Router: [React Router documentation](https://reactrouter.com/)
- Tailwind CSS: [Tailwind CSS documentation](https://tailwindcss.com/docs)
- Vite: [Vite documentation](https://vite.dev/guide/)
- TanStack Query: [TanStack Query documentation](https://tanstack.com/query/latest/docs/framework/react/overview)
- PHP: [PHP manual](https://www.php.net/manual/en/)
- PostgreSQL: [PostgreSQL 18 documentation](https://www.postgresql.org/docs/18/)
- Docker and Compose: [Docker documentation](https://docs.docker.com/)
- Web security: [OWASP Cheat Sheet Series](https://cheatsheetseries.owasp.org/)
- PHP testing: [Pest documentation](https://pestphp.com/docs)
- React testing: [Testing Library documentation](https://testing-library.com/docs/)

Each Fullstack theory lesson ends with a collapsed **Maintainer source record**. It
records the exact documentation topics, versions, consultation date, DALT files
inspected, and earlier DALT material reused for that lesson. These records are
maintenance evidence; the visible lesson remains focused on learning.

## Licensing boundary

DALT.PHP is distributed under the repository's [MIT License](../LICENSE). Links and
attribution do not change the licenses of the external works they identify. If a
future contribution intentionally adapts externally licensed prose, exercises,
images, or other expressive material, the contributor must disclose that source and
license before the material is accepted. Do not assume that attribution alone makes
licenses compatible.
