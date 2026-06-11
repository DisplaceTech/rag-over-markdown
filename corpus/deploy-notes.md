# Skyhook deployment notes

## Topology

Skyhook runs on three Hetzner boxes behind a single HAProxy node:
`app-1` and `app-2` serve PHP-FPM, `db-1` runs PostgreSQL 16 and Redis.
Deploys are blue-green at the FPM-pool level — the new release warms in
the idle pool, HAProxy flips when health checks pass, and the old pool
stays available for instant rollback for 24 hours.

## Deploy procedure

1. Tag the release: `git tag -a vX.Y.Z && git push --tags`.
2. CI builds the artifact and pushes it to the registry.
3. `./deploy.sh vX.Y.Z` — runs migrations with `--dry-run` first, then
   for real if the dry run produced no destructive statements.
4. Verify `/healthz` on both app boxes, then flip HAProxy.

Destructive migrations (column drops, type changes) require a
maintenance window and a manual `--allow-destructive` flag. The deploy
script refuses them otherwise.

## Rollback

`./deploy.sh --rollback` flips HAProxy back to the previous pool.
Database migrations are *not* rolled back automatically — that's why
destructive changes are gated. If a migration must be reverted, restore
from the pre-deploy snapshot (`db-1` keeps 48 hours of WAL).

## Decision log: Q3 schema migration

We decided to defer the user-table schema change to Q3 in favor of
shipping the redirect layer first. The redirect layer is lower-risk and
will surface the migration's actual hot paths before we commit to the
column rename.
