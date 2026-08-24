# FS10.3 — Layers, build cache, and multi-stage images

Lesson ID: FS10.3
Lesson format: Concise theory
Part: 10 — Docker
Status: Published
Estimated effort: 35–45 minutes
Difficulty: Applied
Prerequisites: FS10.2
Last reviewed: 2026-08-23

We will order a Dockerfile around what actually changes, and separate the tools that build an image from the image we ship.

> **Helpful background:** [Dockerfiles, images, and build context](/learn/lessons/73-fs10-2-dockerfiles-and-build-context)

## What we will learn

- read a build as a stack of layers with a cache keyed on inputs;
- put slow, rarely-changing work above fast, frequently-changing work;
- use a second stage to keep build-only tools out of the shipped image.

## Every instruction is a layer

`RUN`, `COPY`, and `ADD` each add a layer. The builder caches a layer and reuses it when its inputs are unchanged — and once one layer misses, every layer after it rebuilds, because each is defined on top of the one before.

That single rule decides the whole shape of a good Dockerfile: **slow and stable first, fast and volatile last.**

Our application needs `pdo_pgsql`, which FS10.2's image did not have. Installing it takes tens of seconds. Copying our source takes milliseconds. So the install goes above the copy:

```dockerfile
FROM php@sha256:f78661b492226388a7057679cc731c3e43bc92ba66cd49a8cfe12374a56bee9f

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql

WORKDIR /app
COPY . .

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
```

Swap those two blocks and every edit to a PHP file reinstalls the extension. The image is identical; the working day is not.

Note also `rm -rf /var/lib/apt/lists/*` in the same `RUN` as `apt-get update` — same instruction, so the cache never becomes a layer. A `RUN rm` in a *later* instruction removes nothing from the image, for the same reason FS10.2's deleted secret stayed readable.

## A second stage keeps the tools out

`libpq-dev` is a build-time need: it supplies the headers `pdo_pgsql` is compiled against. The running container needs only `libpq5`, the shared library the extension loads. A single stage cannot express that difference; two stages can.

```dockerfile
FROM php@sha256:f78661… AS ext-build
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql

FROM php@sha256:f78661… AS runtime
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq5 \
    && rm -rf /var/lib/apt/lists/*
COPY --from=ext-build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
RUN docker-php-ext-enable pdo_pgsql
RUN useradd --create-home --uid 10001 app
WORKDIR /app
COPY src ./src
COPY public ./public
USER app
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
```

`COPY --from=ext-build` takes the compiled artifact and leaves the compiler, the headers, and the package index behind. Only the final stage becomes the image.

Two smaller decisions travel with it. `COPY src ./src` and `COPY public ./public` name what the application needs, instead of copying the directory it happens to live in. And `USER app` drops root, which FS10.5 returns to.

## Measure it, do not assume it

The two files produce working, nearly identical containers, and this is what they cost:

```text
dalt-docker-lab:single   579MB
dalt-docker-lab:multi    541MB
```

Thirty-eight megabytes. Real, and not dramatic — which is the honest lesson. A multi-stage build is worth it when it buys something you can name: a smaller attack surface, no compiler in production, a clear boundary between what builds and what runs. Adding a second stage to satisfy a checklist buys nothing.

## Try it

**Workspace:** copy the Part 10 lab. Docker is required.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/docker-lab/starter .dalt/workspace/fs10-docker
cd .dalt/workspace/fs10-docker
```

**Starting state:** `Dockerfile.single` installs the extension in one stage; `Dockerfile` splits build from runtime.

```bash
docker build -f Dockerfile.single -t dalt-docker-lab:single .
docker build -t dalt-docker-lab:multi .
docker images | grep dalt-docker-lab

printf '\n' >> src/IssueStore.php
docker build --progress=plain -t dalt-docker-lab:multi .
```

**Expected result:** the single-stage image is about 579MB and the multi-stage one about 541MB. After touching a source file, the rebuild transcript shows every expensive step `CACHED` and only the last two rerunning:

```text
#6  [ext-build 2/2] RUN apt-get … docker-php-ext-install pdo_pgsql   CACHED
#8  [runtime 2/8]   RUN apt-get … libpq5                             CACHED
#9  [runtime 3/8]   COPY --from=ext-build …                          CACHED
#10 [runtime 4/8]   RUN docker-php-ext-enable pdo_pgsql              CACHED
#12 [runtime 7/8]   COPY src ./src
#13 [runtime 8/8]   COPY public ./public
```

Now move `COPY . .` in `Dockerfile.single` above its `RUN apt-get` block, touch a source file, and rebuild with `-f Dockerfile.single`. The extension install runs again.

Finally, append a line to `notes/todo.txt` and rebuild. Nothing rebuilds at all: `.dockerignore` kept that file out of the context, so it is not an input.

**Reset:** `docker image rm dalt-docker-lab:single dalt-docker-lab:multi`, and delete the workspace copy when finished with Part 10.

## What to notice

The cache is keyed on inputs, not on time. A file the context excludes cannot invalidate anything, and a file the context includes invalidates every layer from its `COPY` down.

That is why `.dockerignore` belongs to FS10.2 *and* to this lesson: it decides both what ships and what a rebuild costs.

## Common mistakes

- Copying source before installing dependencies, so every edit reinstalls them.
- `RUN rm` in a later instruction, expecting the image to shrink.
- Multi-stage for its own sake, with no build-only tool actually left behind.
- Comparing image sizes by intuition instead of `docker images`.

## Check your understanding

1. Why does a cache miss on one layer rebuild all the layers after it?
2. Why is `rm -rf /var/lib/apt/lists/*` in the same `RUN` as `apt-get update`?
3. What does `COPY --from=ext-build` bring across, and what does it leave behind?
4. Why did appending to `notes/todo.txt` rebuild nothing?

<details><summary>Check your answers</summary>

1. Each layer is defined on top of the one before, so a changed layer changes the base of everything after it.
2. Removing the index in a later instruction would leave it in the earlier layer; only the same instruction keeps it out of the image entirely.
3. The compiled extension. The compiler, the `-dev` headers, and that stage's package index never become part of the final image.
4. `.dockerignore` excludes `notes/`, so the file is not part of the build context and cannot be an input to any layer.
</details>

## Next

Next we will run the image alongside a database, and let Compose describe the relationship.

<details><summary>Maintainer source record</summary>

- Source dossier: Docker documentation research notes and Full Stack Open Containers research notes, sections 25–40.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: Docker build-cache guide, multi-stage build documentation, Dockerfile `COPY --from` and `USER` references, and the official PHP image's `docker-php-ext-install` / `docker-php-ext-enable` helpers.
- Versions: Docker 29.7.2; `php@sha256:f78661…` (PHP 8.4.24).
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 11, FS10.3.
- DALT files inspected: `docker-lab`, the Part 10 track manifest, and the former FS10.1 and FS10.2 pages.
- Extracted material: "structure a build around changing inputs", the layer-secret warning, and "separate build and runtime when it buys something" from the former FS10.2.
- Verified in the lab: both sizes and the cache transcript above are measured output, and the reordered `Dockerfile.single` does rerun the extension install.
</details>
