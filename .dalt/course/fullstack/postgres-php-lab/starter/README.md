# PostgreSQL and PHP lab

This resettable starter grows across FS05.4–FS05.7. PostgreSQL is pinned to the
official PostgreSQL 18.4 image, pinned by digest, and listens only on local port
`55432`.

Start it with `docker compose up -d --wait`. Remove its container and data with
`docker compose down -v`.
