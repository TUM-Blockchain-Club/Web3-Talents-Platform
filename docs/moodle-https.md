# Oracle Moodle HTTPS

Public URL: `https://moodle.web3-talents.com`

The United Domains A record for `moodle` points to `130.61.104.92`.
The apex website, wildcard CNAME, and mail DNS records are not changed.

## Runtime layout

- Caddy terminates HTTPS on TCP 443 and redirects HTTP on TCP 80.
- Its official Docker image is pinned by digest in `moodle/docker-compose.https.yml`.
- `moodle/docker/caddy/Caddyfile` proxies only this hostname to `web:80`.
- Caddy's `/data` and `/config` use persistent Docker volumes. Certificates renew
  automatically; do not remove these volumes or run `docker compose down -v`.
- Apache's diagnostic port is bound to `127.0.0.1:8080`, not the public interface.
- Web, database and proxy restart automatically unless explicitly stopped.
- The old `porttest` nginx container is stopped and retained for recovery.

On Oracle, the HTTPS overlay is installed as
`/opt/web3talents/moodle/docker-compose.override.yml`. Compose loads this
automatically alongside the base file, so ordinary restarts retain HTTPS.
This runtime override is intentionally ignored by Git. When updating its template,
copy `docker-compose.https.yml` to `docker-compose.override.yml` on the host after
review, then validate with `docker compose config --quiet`. Requires Compose 2.24.4+.
Do not print fully expanded Compose configuration: it includes environment secrets.

The ignored runtime `moodle/src/config.php` has, before its setup include:

```php
$CFG->wwwroot = 'https://moodle.web3-talents.com';
$CFG->sslproxy = true;
$CFG->reverseproxy = false;
$CFG->cookiesecure = true;
$CFG->cookiehttponly = true;
```

`sslproxy` is safe here only because the backend is private and Caddy supplies TLS.
`reverseproxy` remains false because Caddy preserves the public Host header.
The runtime `.env` also sets `MOODLE_URL=https://moodle.web3-talents.com`.
Neither runtime file belongs in Git.

## Operations

From `/opt/web3talents/moodle`:

```sh
docker compose config --quiet
docker compose up -d
docker compose exec -T caddy caddy validate --config /etc/caddy/Caddyfile
docker compose exec -T caddy caddy reload --config /etc/caddy/Caddyfile
```

Keep Oracle's network rules and host firewall open for TCP 80 and 443.
Do not reopen public port 8080 or publish the Caddy admin API.

## Migration verification (2026-11-11)

- A fresh database and runtime/file backup was verified before migration at
  `/home/ubuntu/w3t-backups/https-migration-NsHG5lGr` (private directory).
- A publicly trusted Let's Encrypt certificate was obtained and verified without
  bypassing TLS checks.
- HTTPS login returns 200; HTTP redirects with 308; public port 8080 is unreachable.
- Login cookies have Secure and HttpOnly flags; the form action uses HTTPS.
- Three course URL activities and the legacy Shibboleth instruction link were
  updated from the old IP URL. No accounts or passwords were changed.
- The eight internal admins and built-in guest account remain active.

For recovery, prefer restoring only the affected runtime/configuration files from
the private archive; a full database restore would also undo subsequent user work.
Retain the private backup and do not publish it. Review any rollback that would
re-enable HTTP rather than silently exposing credentials again.

SMTP delivery and the optional Zoom host launcher are separate configurations;
enabling HTTPS does not enable either integration.
