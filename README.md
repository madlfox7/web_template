# Minimal web template — how to run

This repository contains a tiny PHP + MariaDB stack wired with Docker Compose.

Quick run (builds images, creates DB from `db/init.sql` and serves on port 80):

```bash
docker compose up --build -d

# watch logs
docker compose logs -f

# stop
docker compose down
```

Notes:
- Visit http://localhost in your browser. The site root is `app/public` served by nginx.
- The `php` service uses environment variables `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` (set in `docker-compose.yml`).
- `db/init.sql` creates the `users` table. The PHP code uses PDO (`pdo_mysql`).

If you run into build errors for the PHP image on Alpine, the `php/Dockerfile` already installs build deps required for `pdo_mysql`.
# web_template