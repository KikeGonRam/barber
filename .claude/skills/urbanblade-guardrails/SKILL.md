---
name: urbanblade-guardrails
description: >
  Safety rules and known incident history for the UrbanBlade "barber" repo (Laravel 12 +
  MongoDB Atlas, shared with the sibling `spark/` analytics project). Consult this BEFORE
  running tests, migrations, seeders, `make` targets, `composer` setup scripts, or any
  `docker compose` command with `down`/volumes in this repo — even if the request looks
  routine ("run the tests", "seed the database", "reset my local env", "reinstall
  dependencies", "clean up docker"). Also consult before editing `setup.ps1`, `.env*`, or
  anything touching `PaymentService`/`InventoryService` transactions, and BEFORE any
  `git push` — pushing without a clean, passing `.\test.ps1` run first is against the
  project owner's explicit rule. This repo shares its production-like MongoDB Atlas
  database with another AI-editable project, and there is a documented real incident of
  an AI/automation wiping real data this way — treat every write-capable command here as
  higher-risk than it looks.
---

# UrbanBlade `barber` — guardrails

This repo's `AGENTS.md` / `CLAUDE.md` already cover stack, structure, and normal dev
commands — read those for context. This skill exists for the things that aren't obvious
from the code and that have already caused real damage or confusion. If a task in this
repo touches any of the areas below, apply the rule before acting, not after.

## 1. Never run tests directly — always `.\test.ps1`

`docker exec barber-app php artisan test` or plain `php artisan test` look harmless, but
they are not: `docker-compose.yml` uses `env_file: .env` for the `app` container, which
bakes the **real Atlas credentials** in as OS environment variables at container-creation
time. Laravel's Dotenv never overrides an already-set env var, so `.env.testing`
(pointing at the local `mongo-test` replica set) is silently ignored — the suite runs
against the shared `barber_db` on Atlas instead. Feature test `tearDown()` methods call
things like `Client::query()->delete()` and `Appointment::query()->delete()`.

**This already happened**: see the comment block at the top of `test.ps1` for the
2026-08-28 incident where exactly this ran the full suite against Atlas with no visible
error, deleting real data. Also note `.docker/entrypoint.sh` runs `php artisan optimize`
on every container start, which caches config — once cached, Laravel stops reading env
vars at all, so `config:clear` must run before any test attempt too (`test.ps1` already
does this).

Rule: run tests only via `.\test.ps1`. If asked to run tests any other way, explain why
and use `test.ps1` instead, or ask first if the user insists otherwise.

## 2. Migrations, seeders, and `make`/`composer` setup targets can hit Atlas

`make setup`, `make seed`, `make migrate`, and `composer run setup` /
`post-create-project-cmd` all run `php artisan migrate` and/or `db:seed` against the
`app` container — which, like above, is wired to the real `.env` (Atlas) unless you have
independently verified otherwise. Before running any of these:
- Confirm with the user what environment `.env` currently points to.
- Prefer scoping to a single seeder class over broad `db:seed` when possible.
- Never chain this with a "reinstall/reset the project" request without flagging that it
  can write to the shared production-like database.

Related sibling-project incident (documented in `spark/unidades/unidad_6_caso_aplicado_laravel/01_diagnostico_incidente.md`):
a full environment "reset" wiped real `users`/`barbers`/`clients` in Atlas with no
recoverable backup, then `MassiveDataSeeder` inflated `appointments` to 100k synthetic
rows on top of the corrupted remainder. That is the failure mode these rules exist to
prevent.

## 3. `make clean` / `docker compose down --volumes` destroys local volumes

`Makefile:clean` runs `docker compose down --remove-orphans --volumes`. Treat this the
same as any destructive/irreversible local-data command — confirm with the user first,
same as `rm -rf` or `git clean`.

## 4. `PaymentService` / `InventoryService` transactions need a replica set

Both wrap writes in `DB::transaction()` (`app/Services/Payment/PaymentService.php`,
`app/Services/Inventory/InventoryService.php`). MongoDB only supports multi-document
transactions inside a replica set. If you "simplify" the test or dev Mongo setup to a
standalone instance, these two services will fail with confusing transaction errors, not
connection errors. Keep `mongo-test` and any dev Mongo running with `--replSet` and an
initialized replica set.

## 5. `setup.ps1` currently defaults to the wrong branch

`setup.ps1` still has `param([string]$Branch = "feature/mongodb-migration", ...)`
(line ~35) even though `main` has been the only branch for a while and that branch's
history was already merged forward. Don't rely on the script's default — pass
`-Branch main` explicitly, or fix the default (low-risk, ask the user first since it's a
committed script, not a local file).

## 6. Shared database with `spark/`

`barber_db` on Atlas is read by the sibling `spark/` project (PySpark analytics, a
separate AI-editable repo). Schema changes here (renaming/removing fields or
collections used by appointments, payments, customers, barbers, services) can silently
break `spark/`'s scripts, which expect exact field names. If a schema change is needed,
say so explicitly rather than doing it quietly.

## 7. Secrets

Never commit or print the contents of `.env`, `.env.backup`, or `.env.production`
(already gitignored — keep it that way). Demo/test credentials for manual QA live in
`../ACCESOS.md` (outside this repo) — fine to point the user there, but don't copy those
credentials into commits, logs, or shared output.

## 8. CI is safe to trust

`.github/workflows/ci.yml` only ever talks to an ephemeral `mongo:7` container it starts
itself (`--replSet rs0`), never Atlas, and has no deploy/publish step. It's fine to let
CI run freely — the risk in this repo is entirely in local/Docker commands that reuse the
real `.env`, not in CI.

## 9. Never push without a clean, passing test run first

Before running `git push` on this repo, run `.\test.ps1` and confirm the suite passes
with no errors. If anything fails, fix it (or ask the user how to proceed) before
pushing — don't push on the assumption that a failure is unrelated or pre-existing.
Same goes for `pint --test` and `eslint`/`npm run build` if the change touches PHP or
frontend code, since those are exactly what CI checks on every push. This is a standing
rule from the project owner, not just good practice — treat it as a hard gate, not a
suggestion.

## 10. Active scope: this repo only

As of 2026-09-02, active work is scoped to this repo (`barber`) only. `mobil` and
`spark` already received their own copy of this guardrails skill and are stable, but
are not being actively developed alongside `barber` right now — don't propose or start
work in those repos unless the user explicitly asks. The data-sharing risk in rule 6
above still applies even while `spark` is paused: `barber_db` still exists on Atlas and
`spark`'s read-only scripts still point at it, so schema changes here can still make
`spark` stale for whenever the user returns to it.
