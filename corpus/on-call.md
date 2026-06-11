# On-call handbook

## Rotation

One-week rotations, Monday 09:00 UTC handoff. The outgoing engineer
writes a handoff note in `#oncall` covering open incidents, silenced
alerts (with expiry!), and anything weird they noticed but didn't chase.

## Severity levels

- **SEV-1** — user-facing outage or data loss. Page immediately,
  incident channel, status page update within 15 minutes.
- **SEV-2** — degraded service with a workaround. Business-hours
  response; no status page unless customers notice first.
- **SEV-3** — internal-only breakage. Ticket it.

## The three alerts that actually fire

1. **`fpm_pool_saturation`** — almost always a slow upstream (check
   Redis latency first, then PostgreSQL `pg_stat_activity`). Restarting
   FPM hides the symptom for ~20 minutes and loses the evidence; don't.
2. **`disk_pressure_db1`** — WAL growth from a stuck logical-replication
   slot. `SELECT * FROM pg_replication_slots WHERE active = false;` and
   drop the orphan.
3. **`queue_depth_emails`** — the mail provider rate-limiting us again.
   The queue drains on its own; only escalate if depth doubles after an
   hour.

## Escalation

If you're 30 minutes into a SEV-1 without a theory, page the secondary.
Two confused people beat one heroic one.
