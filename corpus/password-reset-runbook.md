# Runbook: password reset flow

## User-initiated reset

Users reset their own passwords from **Settings → Security → Reset
password**. The flow emails a single-use token valid for 30 minutes;
tokens are invalidated the moment a new one is issued, so "I requested
it twice and the first link failed" is expected behavior, not a bug.

## Admin-forced reset

Support staff can force a reset from the admin panel (**Users → ⋯ →
Force password reset**). This immediately revokes all of the user's
active sessions and API tokens. Use it for suspected account
compromise; do not use it for routine "I forgot my password" tickets —
point those users at the self-service flow instead.

## Lockout policy

Five failed attempts within ten minutes locks the account for one hour.
The lock clears automatically; support can clear it early from the
admin panel. Lockout events are logged to the `auth_events` table with
reason `lockout`.

## Things that look like bugs but aren't

- Reset emails land in spam for domains with strict DMARC. Resending
  rarely helps; have the user allowlist `noreply@skyhook.example`.
- The reset link 404s if the user is already logged in on the device —
  this is deliberate session-fixation protection.
