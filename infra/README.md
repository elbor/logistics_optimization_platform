# infra

Локальне середовище розробки в Docker. На Mac потрібен лише Docker Desktop.

- `compose.yaml` — сервіси: `app` (PHP 8.4 + Composer, Laravel) і `db` (PostgreSQL 17 + PostGIS).
- `php/Dockerfile` — образ PHP з розширеннями для Laravel і Filament.
- `postgres/Dockerfile` — офіційний `postgres:17` + PostGIS з пакетів. Працює нативно на Apple Silicon (образ `postgis/postgis` є лише для amd64).
- `postgres/init/` — SQL, що виконується при першому створенні бази (вмикає PostGIS).

Усі образи мають підтримувати arm64. Пізніше сюди додадуться VROOM і OSRM — перевірити для них те саме.

Команди — у CLAUDE.md, розділ «Команди».
