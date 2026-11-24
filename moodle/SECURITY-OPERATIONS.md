# Production security operations

Moodle is the primary deployed application. The TypeScript reference apps have a separate dependency and security lifecycle.

## Production baseline

- Use `docker-compose.https.yml` as the host's `docker-compose.override.yml`. It preserves Caddy HTTPS, a loopback-only backend, and `docker/php/production.ini` loaded after the development INI.
- PHP errors are logged to the container error stream, not displayed in responses. PHP version advertising is disabled. Restrict access to these logs because errors can contain personal data.
- Mailpit is development-only. The production overlay puts it behind the `development-mail` profile and restricts its ports to loopback. When upgrading an existing stack, stop the old Mailpit container explicitly. Do not delete it without considering any captured mail.
- After a verified backup, `php /opt/web3talents/tooling/harden-production.php --apply` disables Moodle debug output, disables web-based plugin installation/updates, and sets a temporary account lockout after 10 failures in a 15-minute window, lasting 15 minutes.
- Keep an SSH/CLI recovery path and a verified named administrator account. A locked-out user can wait for expiry; administrators can adjust the lockout settings with Moodle's `admin/cli/cfg.php`. Do not test repeated failed logins against a real account.
- Passwords remain managed by Moodle's native hashing API. Never write plaintext passwords or replace hashes manually in SQL.
- Password setup and password-reset links share `pwresettime`, currently 604800 seconds (7 days). Email copy derives its expiry from that setting. Before increasing this lifetime, invalidate records already expired under the old policy so they cannot become usable again. Moodle 5.2.5 invalidates older links when issuing a new reset token. Failed delivery does not restore superseded links; request a fresh link after correcting delivery.

## Safe upgrade procedure

1. Read the official release notes and verify the downloaded archive against its official SHA-256 checksum. `fetch-moodle.sh` pins 5.2.5 and its checksum for new installations; it deliberately does not overwrite an existing source tree. Supply a verified `MOODLE_SHA256` when choosing another version.
2. Back up PostgreSQL with `pg_dump -Fc`, plus Moodle source/configuration, moodledata, custom plugins/themes/blocks, and Compose/environment files. Backups contain secrets and personal data: use private directories and files, and do not commit or publish them.
3. Restore into separate containers on an **internal-only** Docker network, with no published ports or production environment file. Replace configuration before starting the test runtime. Set `noemailever=true`, `nosmsever=true`, and disable cron. Never point the test runtime at the production database or data directory.
4. Test the upgrade on that copy. Run the standalone regression scripts using the repository layout (their relative imports require sibling `plugins`, `themes`, and `blocks`). `validate-security-runtime.php` additionally requires a restored DB named `reviewtest`, `W3T_SECURITY_TEST=1`, and `noemailever`. Its synthetic account/token changes roll back.
5. Compare accounts, enrolments, applications/reviews/comments, groups, grades and course content before/after. Course `cacherev` is expected to change when caches rebuild. Confirm custom code is preserved and check the login form, HTTPS/cookies, and anonymous access restrictions.
6. Enter maintenance, stop web writes, and take a final consistent database/file backup. Install a clean core tree, preserving the runtime config and existing custom bind mounts. Run the CLI upgrade and hardening as `www-data`, then recreate only the web service. Verify data and disable maintenance.
7. If rollback is needed after database migration, restore **both** the matching database and files while writes are stopped; do not just downgrade the code. Preserve the failed version and capture diagnostics. Keep the old source and backups until acceptance.
8. Stop isolated test containers and retain their sensitive data only as long as needed. An on-host backup is not disaster recovery: establish encrypted off-host backups and periodic restore drills separately.

## Deliberate follow-ups, not automatic changes

- Keep applicant activation mail paused until explicitly approved. Neither the security hardening script nor enabling that flag should bulk-send held invitations.
- Do not enable an overdue general cron queue without inspecting pending mail, retention and topic-round jobs. Schedule cron only after verifying those effects with the course administrators.
- Configure administrator MFA with tested enrollment and recovery before enforcing it. Rotate shared credentials previously disclosed in chat using a secure handoff; avoid locking out the team.
- Review least-privilege roles and the custom dashboard block's risk declaration. Its `myaddinstance` grant is flagged by Moodle's default-role check; this warning is not evidence that ordinary users have administrator access.
- Complete privacy export/deletion support for custom applicant records. Soft removal is not erasure.

## References

- [Moodle 5.2.5 release notes](https://moodledev.io/general/releases/5.2/5.2.5)
- [Moodle upgrade guidance](https://docs.moodle.org/502/en/Upgrading)
- [Moodle password hashing](https://docs.moodle.org/502/en/Password_hashing)
- [OWASP authentication guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP forgot-password guidance](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)
