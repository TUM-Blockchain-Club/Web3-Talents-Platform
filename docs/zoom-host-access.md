# Restricted Moodle → Zoom host access

The admin dashboard has a **Start Zoom as host** page. It uses one existing Zoom host and meeting, not a new Zoom account for every organiser. It does not create meetings, enrol people, sync attendance or configure alternative hosts. Zoom's `start_url` represents the licensed host's identity, including its sensitive ZAK credential; this implementation does not establish that shared-host use is permitted under your Zoom subscription. Confirm the account owner's approval and the applicable Zoom requirements before enabling.

## Disabled until provisioned

No real credential is retrieved on GET. A launch requires all of:

- Individual authenticated Moodle account with `local/web3talents:manage` in the configured fundamentals course.
- Explicit inclusion of that immutable Moodle user ID in the server-side organiser allowlist. Site administrators are **not** automatically approved; students cannot gain access through allowlisting alone. No registration by email-domain grants access.
- HTTPS `wwwroot`, secure session cookies, and HTTPS transport (or correctly configured trusted TLS reverse proxy).
- Explicit server-side enable flag, complete credentials, pinned meeting ID and expected Zoom host ID.
- POST and a valid Moodle session key. Per-user locking and a 30-second throttle protect against repeated issuance.

Set these variables in the private environment passed to the Moodle web container (never Git or frontend JavaScript):

```
WEB3T_ZOOM_HOST_ENABLED=0
WEB3T_ZOOM_ACCOUNT_ID=
WEB3T_ZOOM_CLIENT_ID=
WEB3T_ZOOM_CLIENT_SECRET=
WEB3T_ZOOM_HOST_ID=
WEB3T_ZOOM_MEETING_ID=
WEB3T_ZOOM_ORGANISER_IDS=
```

Organiser IDs are comma-separated numeric Moodle IDs, resolved from the approved people's existing accounts. Do not use a shared admin login. Recreate the web container after environment changes; restarting alone does not reload Compose `env_file`. Keep the enable flag at `0` until the prerequisites are verified.

Use an activated Zoom Server-to-Server OAuth app with the minimum permission for **Get a meeting** (`meeting:read:meeting:admin`, or its supported legacy equivalent). Confirm the exact scopes supported by the app in Zoom. If Zoom requires an additional scope to return host start credentials, do not silently widen the app's permissions. The existing app may currently have only attendance/report permissions. The configured host ID must equal the API's `host_id`; do not use a display name. The meeting ID can be updated server-side when the recurring meeting changes. Tokens are fetched on demand, held only in memory and never written to Moodle configuration, session data, logs or caches.

## HTTPS deployment checklist

1. Confirm a dedicated Moodle hostname, DNS ownership and the approved organiser identities.
2. Point that hostname to Oracle, configure a valid TLS certificate and redirect HTTP to HTTPS.
3. Put Moodle behind the trusted proxy; block direct public access to port 8080. Set Moodle's canonical `wwwroot` to the HTTPS hostname, `cookiesecure` on, and `sslproxy` only if TLS really terminates at that trusted proxy. Do not trust arbitrary forwarded headers.
4. Log out/in after migration so previously HTTP-exposed sessions cannot be reused. Verify certificate renewal and session/cookie configuration before enabling this feature.
5. Configure the secrets and allowlist, then enable and conduct one controlled host-launch test with an approved organiser. Do not start a live class as a deployment test.

The current HTTP Oracle site must remain disabled. The host URL is issued only through a no-store, no-referrer 303 response, not embedded in fallback HTML. It will necessarily be available to the authorised organiser's browser/Zoom client and may be visible in browser history or developer tools; it cannot be made impossible to copy or forward. Never log response `Location` headers in proxies or APM. A newly generated link is not guaranteed to be single-use. Choose **one** organiser to start each session; others join normally and receive co-host controls.

The existing operational log records the Moodle user, course and time under `zoom_host_link_requested`; that means a credential was requested, not that Zoom actually started. A user preference retains the last attempt timestamp for throttling. No student data is transmitted to Zoom by this feature. Existing plugin privacy export/erasure limitations still apply.

## Verification

Run `moodle/tooling/validate-zoom-host.php` only in the disposable `reviewtest` Moodle database with email disabled and `W3T_ZOOM_TEST=1`, after the application-review fixture has created `reviewerone` and `nonreviewer`. Its mocked transport never contacts Zoom. It checks URL validation, host/meeting pinning, permissions, secure transport, disabled configuration, throttling, safe audit records and redacted errors.

Sources: [Zoom API](https://developers.zoom.us/docs/api/meetings/), [Server-to-server OAuth](https://developers.zoom.us/docs/internal-apps/s2s-oauth/).
