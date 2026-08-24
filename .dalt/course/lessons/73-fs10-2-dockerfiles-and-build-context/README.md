# FS10.2 — Dockerfiles, images, and build context

Lesson ID: FS10.2
Lesson format: Concise theory
Part: 10 — Docker
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Applied
Prerequisites: FS10.1
Last reviewed: 2026-08-23

We will write the recipe for our image down in a Dockerfile, and take deliberate control of which files are even allowed to be considered for it.

> **Helpful background:** [Processes, images, containers, and ports](/learn/lessons/50-fs10-1-containers-around-the-application)

## What we will learn

- read a Dockerfile as an ordered recipe with one default command;
- understand build context as an input boundary, not a convenience;
- use `.dockerignore` to keep local-only files out of an image for good.

## The smallest Dockerfile that works

FS10.1 ran a container by mounting our source into a stock image. That is fine for a look, and useless for shipping: the image contains no application. A Dockerfile records how to build one that does.

```dockerfile
FROM php@sha256:f78661b492226388a7057679cc731c3e43bc92ba66cd49a8cfe12374a56bee9f

WORKDIR /app
COPY . .

EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
```

Five instructions, and each says one thing:

```text
FROM     which image we start from — pinned by digest
WORKDIR  the directory instructions run in, and the container starts in
COPY     bring files from the build context into the image
EXPOSE   documentation: this is the port the process will listen on
CMD      the default command, which becomes the container's main process
```

`EXPOSE` publishes nothing. Publishing is `-p` at run time, which is why FS10.1 could publish a port for an image that never declared one.

Build it and run it:

```bash
docker build -f Dockerfile.first -t dalt-docker-lab:first .
docker run --rm -d --name dalt-lab -p 127.0.0.1:58001:8000 dalt-docker-lab:first
```

No bind mount this time. The code is *inside* the image, which is what makes it a thing you can send somewhere.

## The base image is smaller than you think

Ask this container for data and it fails in a very specific way:

```bash
curl http://127.0.0.1:58001/issues
{"error":"could not find driver"}
```

`php:*-cli` is a deliberately minimal PHP: no Composer, and no `pdo_pgsql`. Nothing in our Dockerfile added it, so nothing is there. FS10.3 fixes it.

This failure shape is worth remembering, because its cousin is much worse. If the application had quietly fallen back to SQLite, the same container would have printed a successful migration against a database it never touched. A missing driver that says so is a good day.

## Build context is an input boundary

The `.` at the end of `docker build` is not decoration. It is the **build context**: the set of files sent to the builder, and the only files `COPY` can reach. Without a filter, that is everything in the directory:

```text
Dockerfile  Dockerfile.first  Dockerfile.single  compose.yaml
config.local.php  database  notes  public  src
```

`COPY . .` then puts all of it into the image, including the local-only file that was never meant to leave the machine.

`.dockerignore` decides that at the boundary, before the builder ever sees a file:

```text
.git
vendor
notes
config.local.php
compose.yaml
Dockerfile*
```

This is not tidiness. An image is a stack of layers, and layers are readable. Copying a secret in and deleting it in a later instruction leaves it in the layer it arrived in, where `docker image history` and anyone with the image can find it. The only reliable answer is that it never enters the context.

## Try it

**Workspace:** copy the Part 10 lab. Docker is required.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/docker-lab/starter .dalt/workspace/fs10-docker
cd .dalt/workspace/fs10-docker
```

**Starting state:** `Dockerfile.first` is the five-instruction file above. `.dockerignore` excludes the local-only file, the notes folder, and the Compose and Dockerfile files themselves.

```bash
docker build -f Dockerfile.first -t dalt-docker-lab:first .
docker run --rm dalt-docker-lab:first ls -a /app
```

**Expected result:**

```text
.  ..  .dockerignore  database  public  src
```

`config.local.php` and `notes/` are absent. Now remove the filter and rebuild:

```bash
mv .dockerignore dockerignore.disabled
docker build --no-cache -f Dockerfile.first -t dalt-docker-lab:noignore .
docker run --rm dalt-docker-lab:noignore ls -a /app
mv dockerignore.disabled .dockerignore
```

The second listing contains `config.local.php`, `notes`, `compose.yaml`, and all three Dockerfiles. The local-only credential is now in a published layer.

**Reset:** `docker image rm dalt-docker-lab:noignore`, and delete the workspace copy when finished with Part 10.

## What to notice

Nothing about the second build failed. It succeeded, faster than you would like, and shipped a file that should never have left the machine. That is the argument for treating `.dockerignore` as part of the Dockerfile rather than an optimisation.

Notice too that `.dockerignore` is itself in the image. It is not a security tool; it is an input filter, and it filters the inputs it was told about.

## Common mistakes

- Assuming `EXPOSE` makes a port reachable.
- Writing `COPY . .` with no `.dockerignore` and no idea what "everything" currently is.
- Copying a credential and deleting it in a later instruction.
- Building from a floating tag so the base image changes underneath a working Dockerfile.

## Check your understanding

1. What is the build context, and what decides its contents?
2. What does `EXPOSE 8000` actually do?
3. Why does deleting a copied secret in a later instruction not remove it from the image?
4. Why did `/issues` fail on this image while `/health` worked?

<details><summary>Check your answers</summary>

1. The files sent to the builder — the directory given as the last `docker build` argument, minus everything `.dockerignore` excludes.
2. It documents the port the process listens on. Reaching it still requires publishing with `-p` or Compose.
3. Each instruction creates a layer; the earlier layer still contains the file, and image history exposes it.
4. `/health` only needs the PHP process; `/issues` needs the `pdo_pgsql` extension, which the base image does not include.
</details>

## Next

Next we will look at what those layers cost, and how to stop paying for them on every build.

<details><summary>Maintainer source record</summary>

- Source dossier: Docker documentation research notes and Full Stack Open Containers research notes, sections 13–24.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: Dockerfile reference (`FROM`, `WORKDIR`, `COPY`, `EXPOSE`, `CMD`), the build-context and `.dockerignore` documentation, and the official PHP image documentation on bundled extensions.
- Versions: Docker 29.7.2; `php@sha256:f78661…` (PHP 8.4.24).
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 11, FS10.2.
- DALT files inspected: `docker-lab`, the Part 10 track manifest, and the former FS10.1 and FS10.2 pages.
- Extracted material: "build context is an input boundary" from the former FS10.1, and the `php:*-cli` minimality warning recorded as finding F22/F23 in the internal Fullstack verification log.
- Verified in the lab: both directory listings above are real output; the unfiltered build ships `config.local.php`.
</details>
