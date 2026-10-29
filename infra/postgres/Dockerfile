# PostgreSQL + PostGIS для amd64 і arm64 (Apple Silicon).
# Образ postgis/postgis випускається лише для amd64, тому ставимо PostGIS
# з репозиторію PGDG, який уже підключений в офіційному образі postgres.
FROM postgres:17

RUN apt-get update \
    && apt-get install -y --no-install-recommends postgresql-17-postgis-3 \
    && rm -rf /var/lib/apt/lists/*

# Вмикає розширення PostGIS у базі під час першої ініціалізації
COPY init/ /docker-entrypoint-initdb.d/
