# Marketplace PHP App

**Sync:** Only **public/** and **db/** are synced to LEMP. No scripts/, no src/, no data/.

- **Document root**: Point Nginx at **public/** (the synced PUBLIC folder).
- **DB (SQLite)**: **db/** folder at the same level as **public/**; file `db/store.sqlite`. `.env` uses `DB_DSN=sqlite:db/store.sqlite` (path relative to baseDir = parent of public/).
- **Schema**: **public/schema.php** — run via HTTP (GET/POST) or CLI: `php schema.php` from the public/ directory (with baseDir = parent of public). Creates tables, views, seeds config.
- **.env**: In the **app/** folder (same level as public/ and db/). Copy from `app/.env.example` to `app/.env`. PHP loads only DB_*, SITE_*, session/cookie/CSRF salts.
