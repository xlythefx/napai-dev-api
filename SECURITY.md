# Security — QuanTab / napai-api

This file documents the operational security model for the tablet-saas
portion of napai-api: where secrets live, how they're rotated, and what to do
when something is compromised.

## Secrets inventory

| Secret | Where | What it protects |
|---|---|---|
| `TABLET_LICENSE_SIGNING_SECRET` | `config/tablet.php` (server) + `--dart-define=LICENSE_SIGNING_SECRET` (Flutter build) | Offline HMAC tokens that gate the QuanTab kiosk lockdown. Leak = anyone can forge a valid license for any (serial, androidId) pair. |
| `TABLET_SMTP_PASS` | `config/tablet.php` | Hostinger mailbox sending licence-key QRs. Leak = attacker can phish customers from `support@nap-ai.com`. |
| MySQL credentials | `config/db.php` | Direct DB access. |
| Admin login | `developers` table (bcrypt) | Web admin at `/admin/*`. |

`config/tablet.php` and the other `config/*_key.php` files are listed in
`.gitignore`. Verify periodically:

```bash
git check-ignore -v config/tablet.php
git log --all -p -- config/tablet.php   # must show no commits
```

## Rotation procedure — license signing secret

Until per-token key-versioning lands (medium-term roadmap item), the secret
is single-key. Rotating it invalidates every cached offline token: existing
tablets fail their next heartbeat token-check and prompt for re-activation.

1. Flip `/admin/system-settings` → **Maintenance mode ON**. New activations
   and new trial requests get 503'd; existing devices' heartbeats are
   intentionally NOT blocked so they don't die mid-shift.
2. Generate the new secret:
   ```bash
   openssl rand -base64 32 | tr -d '\n='
   ```
3. Update `config/tablet.php` on the production server (`TABLET_LICENSE_SIGNING_SECRET`).
4. Rebuild and publish a new APK:
   ```bash
   flutter build apk \
     --dart-define=LICENSE_SIGNING_SECRET="<paste new value>"
   ```
   Upload it under `/admin/tablet/apk-versions` so the public APK URL serves
   the new build.
5. Send a "please re-activate" notice to customers. Each tablet will need
   their original serial re-entered (the serial itself is not invalidated —
   only the token cached on the device).
6. Flip Maintenance mode **OFF**.

Cadence: rotate annually, or immediately on any of the incidents below.

## Incident response

### "The signing secret may have leaked"

Treat any of the following as a leak:
- The string was pasted into Slack/Discord/email.
- A laptop with the production `config/tablet.php` was lost or stolen.
- `git log --all -- config/tablet.php` shows a commit (even on a branch).
- An APK build with the real secret got published to a public store/repo.

Response:
1. Maintenance mode ON.
2. Mass-revoke (optional): `UPDATE license_keys SET revoked=1 WHERE revoked=0`
   if you suspect tokens are being forged in the wild. Customers will need
   admin help to unrevoke. Skip this step if the leak is theoretical.
3. Run the rotation procedure above.
4. Audit `tablet_admin_audit` (after migration 032) for unexpected
   `keys.release` / `keys.revoke` rows in the leak window.

### "Hostinger mailbox compromised"

1. Change the mailbox password in the Hostinger panel.
2. Update `TABLET_SMTP_PASS` in `config/tablet.php`.
3. Flip "Enable emailing of license keys" OFF in System Settings if you
   need a hard pause while you triage.
4. Audit `email_log` for sends you didn't initiate.

### "Admin account compromised"

1. Update the `developers.password_hash` row directly with a fresh bcrypt.
2. Audit `tablet_admin_audit` for actions taken by the affected actor email.
3. If keys were generated/emailed maliciously, revoke them through the admin
   UI and re-issue legitimate ones to affected customers.

## Who has access

- Production server SSH: <names go here>
- Hostinger mailbox: <names go here>
- DB direct access: <names go here>
- Admin web login (developers.role='admin'): see `developers` table

Keep this list current. Remove anyone who leaves the team within 24h.
