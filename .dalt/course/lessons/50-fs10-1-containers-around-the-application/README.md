# FS10.1 — Processes, images, containers, and ports

Lesson ID: FS10.1
Lesson format: Concise theory
Part: 10 — Docker
Status: Published
Estimated effort: 30–40 minutes
Difficulty: Applied
Prerequisites: FS09.3
Last reviewed: 2026-08-23

We will run our application inside a container without writing a Dockerfile, so that image, container, process, and port each mean something concrete before any of them is automated.

> **Helpful background:** [Configuration, production builds, and error boundaries](/learn/lessons/49-fs09-2-build-pipeline-configuration-and-failure-boundaries)

## What we will learn

- separate the three words that get used interchangeably: image, container, process;
- publish a port and know which side of the colon is which;
- see that a container leaves nothing behind unless we ask it to.

## A container is a process, not a small computer

The mental model that causes the least trouble later:

```text
image      a read-only filesystem template, plus a default command
container  one process started from that template, with its own view of the system
volume     storage that outlives the container that used it
```

There is no operating system booting inside a container. It is an ordinary process on the host, started with its own view of the filesystem, the network, and the process table. That is why a container exits the moment its main process exits, and why "the container is running but nothing happens" almost always means the main process ended.

## Run something without building anything

We do not need a Dockerfile to run our code. The official PHP image already contains PHP; we can mount our source into it and start the built-in server:

```bash
docker run --rm -d --name dalt-lab \
  -p 127.0.0.1:58001:8000 \
  -v "$PWD":/app -w /app \
  php@sha256:f78661b492226388a7057679cc731c3e43bc92ba66cd49a8cfe12374a56bee9f \
  php -S 0.0.0.0:8000 -t public
```

Four flags carry the whole idea:

```text
--rm                  delete the container when it stops
-p 127.0.0.1:58001:8000   host side : container side
-v "$PWD":/app        the host directory appears inside the container
-w /app               start the process there
```

The port mapping reads host-first. The container listens on `8000`; we reach it on `58001`. Binding the host side to `127.0.0.1` keeps the lab off the network, which is a habit worth having before anything real is running.

`0.0.0.0:8000` inside the container is not optional. A server bound to `127.0.0.1` inside the container is reachable only from inside it, and the published port will connect to nothing.

## Pin the image, always

`php:8.4-cli` is a moving name. A digest is not:

```text
php@sha256:f78661b492226388a7057679cc731c3e43bc92ba66cd49a8cfe12374a56bee9f
```

A tag can point at a different image tomorrow, which is how a build that worked last week stops working with no change in your repository. Every image in this lab is pinned by digest for the same reason FS07.1 pins npm versions.

## Try it

**Workspace:** copy the Part 10 lab. Docker is required.

```bash
mkdir -p .dalt/workspace
cp -R .dalt/course/fullstack/docker-lab/starter .dalt/workspace/fs10-docker
cd .dalt/workspace/fs10-docker
```

**Starting state:** a small PHP service in `public/` and `src/`. No Dockerfile is used in this lesson.

```bash
docker run --rm -d --name dalt-lab -p 127.0.0.1:58001:8000 \
  -v "$PWD":/app -w /app \
  php@sha256:f78661b492226388a7057679cc731c3e43bc92ba66cd49a8cfe12374a56bee9f \
  php -S 0.0.0.0:8000 -t public

curl http://127.0.0.1:58001/health
docker exec dalt-lab sh -c "tr '\0' ' ' < /proc/1/cmdline"
docker exec dalt-lab hostname
docker top dalt-lab -o pid,args
docker rm -f dalt-lab
```

**Expected result:**

```text
{"status":"ok"}
php -S 0.0.0.0:8000 -t public
1814805567ec
PID       COMMAND
264408    php -S 0.0.0.0:8000 -t public
```

The same process is PID 1 inside the container and an ordinary host PID outside it. The hostname is the container id.

Now try `curl http://127.0.0.1:8000/health` instead. It fails: nothing is listening there, because `8000` is the container's port, not the host's.

**Reset:** `docker rm -f dalt-lab` already removed it, and `--rm` removed its filesystem. Delete the workspace copy when finished.

## What to notice

`docker top` and `/proc/1/cmdline` show one process from two sides. Nothing was virtualised; the kernel simply gave that process a different view of the machine.

Notice also what did *not* happen: no image was built, and nothing was written into the image. Our source is visible inside the container only because we mounted it, and the moment the container stopped, everything it had written was gone.

## Common mistakes

- Binding the server to `127.0.0.1` inside the container, then wondering why the published port refuses connections.
- Reading `-p 58001:8000` backwards.
- Using a floating tag, so a build reproduces only until the tag moves.
- Expecting files written inside a container to still be there next time.

## Check your understanding

1. What is the difference between an image and a container?
2. In `-p 127.0.0.1:58001:8000`, which number does `curl` on the host use?
3. Why must the server bind to `0.0.0.0` inside the container?
4. Why does the container stop when its main process exits?

<details><summary>Check your answers</summary>

1. The image is a read-only filesystem template plus a default command; the container is one running process started from it.
2. `58001`. The host side comes first.
3. `127.0.0.1` inside the container is that container's own loopback, so the published port would have nothing to forward to.
4. A container *is* that process. There is no init system underneath it to keep going.
</details>

## Next

Next we will write the recipe down, so the image can be rebuilt without remembering a long command.

<details><summary>Maintainer source record</summary>

- Source dossier: Docker documentation research notes and Full Stack Open Containers research notes, sections 1–12.
- Public source index: `documentation/sources-and-attribution.md` in the public repository
- Official sources: Docker `run` reference, published-port and bind-mount documentation, image pull-by-digest guidance, and the "containers are processes" overview.
- Versions: Docker 29.7.2; Docker Compose 5.4.0; `php@sha256:f78661…` (PHP 8.4.24).
- Consulted: 2026-08-23.
- Curriculum authority: DALT Fullstack theory curriculum, Batch 11, FS10.1.
- DALT files inspected: the new `docker-lab`, the Part 10 track manifest, and the former FS10.1 page.
- Extracted material: "start with a process, not a box", the published-port explanation, and the container-lifecycle section from the former FS10.1. Compose, volumes, and build context move to FS10.2–FS10.4.
- Verified in the lab: the transcript above is real output; the host PID differs from the in-container PID 1, and the hostname is the container id.
</details>
