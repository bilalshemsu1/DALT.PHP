# Stage 1 — build the extension. Compilers and headers live here and are never shipped.
FROM php@sha256:f78661b492226388a7057679cc731c3e43bc92ba66cd49a8cfe12374a56bee9f AS ext-build

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql

# Stage 2 — runtime. Only the shared library the extension loads against, plus the
# compiled extension itself.
FROM php@sha256:f78661b492226388a7057679cc731c3e43bc92ba66cd49a8cfe12374a56bee9f AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq5 \
    && rm -rf /var/lib/apt/lists/*

COPY --from=ext-build /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
RUN docker-php-ext-enable pdo_pgsql

RUN useradd --create-home --uid 10001 app

WORKDIR /app
# Copy what the application needs, not the directory it happens to live in.
COPY src ./src
COPY public ./public

USER app
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
