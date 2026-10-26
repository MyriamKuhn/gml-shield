# GML-SHIELD - K8 production scaffolding

DO NOT DEPLOY YET. The React localhost URLs, Laravel proxy settings, cookies,
Caddy routes, secrets and backup / removal procedure must be verified first.

This configuration has no published host ports. `db` and workers are only on
`private`; `api`, `reverb` additionally join the existing external `proxy`
network so Caddy can reach them, while `front` is only on `proxy`.

Expected non-versioned files on K8:
- `.env`: `DB_PASSWORD` and `VITE_REVERB_APP_KEY` for Compose
- `deploy/backend.env`: Laravel APP_KEY, DB_PASSWORD, REVERB_APP_* and other
  runtime settings. Must be created on K8, never committed.

Before launching: verify the Compose file, build paths, configurations, Caddy
reverse-proxy path routing, HTTPS/WSS and database backup behavior. Do not use
`docker compose down -v` to remove it without an explicit data-retention decision.
