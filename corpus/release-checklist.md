# Release checklist

Run top to bottom; nothing here is optional. The checklist exists
because every item on it has been skipped exactly once.

## Before tagging

- [ ] CI green on `main`, including the slow integration suite.
- [ ] `CHANGELOG.md` entry written — user-facing language, no commit
      hashes.
- [ ] Schema migrations reviewed by a second engineer; destructive ones
      scheduled into a maintenance window.
- [ ] Feature flags for half-finished work confirmed **off** in
      production config.

## Tagging

- [ ] Bump the version in `config/app.php` — the deploy script
      cross-checks it against the git tag and refuses on mismatch.
- [ ] Annotated tag, `v`-prefixed: `git tag -a v2.14.0`.

## After the flip

- [ ] Watch error rates for 30 minutes (the dashboard bookmark in
      `#deploys`). The flip itself is boring; minute 25 is when the
      cache-stampede class of bug shows up.
- [ ] Close the release milestone; move stragglers, don't carry them
      silently.
- [ ] If anything required a manual step not on this list, add it to
      this list in the same PR as the fix.
