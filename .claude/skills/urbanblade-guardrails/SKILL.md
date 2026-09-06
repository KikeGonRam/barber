---
name: urbanblade-guardrails
description: >
  Safety rules and known incident history for the UrbanBlade "barber" repo (Laravel 13 +
  MongoDB Atlas, shared with the sibling `spark/` analytics project). Consult this BEFORE
  running tests, migrations, seeders, `make` targets, `composer` setup scripts, or any
  `docker compose` command with `down`/volumes in this repo — even if the request looks
  routine ("run the tests", "seed the database", "reset my local env", "reinstall
  dependencies", "clean up docker"). Also consult before editing `setup.ps1`, `.env*`, or
  anything touching `PaymentService`/`InventoryService`/`OrderService` (payment amounts,
  product prices, transactions), and BEFORE any `git push` — pushing without a clean,
  passing `.\test.ps1` run first is against the project owner's explicit rule. Also
  consult before changing `routes/api.php` or `app/Http/Controllers/Api/**` — the API is
  a real external contract for a native Android app being built separately, not just
  internal code. ALSO consult before editing or creating ANY `.md` file in this repo
  (README, docs/*, credentials, setup guides) — this repo has already had real
  documentation drift (duplicate files, three copies of the same credentials table going
  stale independently) from an AI editing one file at a time instead of sweeping all
  related docs first. This repo shares its production-like MongoDB Atlas database with
  another AI-editable project, and there are TWO documented real incidents of
  automation/AI wiping or bloating real data this way — treat every write-capable command
  here as higher-risk than it looks, whether it's code, data, or documentation.
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

As of 2026-09-02, active work is scoped to this repo (`barber`) only. `spark` is
paused/no longer being worked on — don't propose or start work there unless the user
explicitly asks. The data-sharing risk in rule 6 above still applies even while `spark`
is paused: `barber_db` still exists on Atlas and `spark`'s read-only scripts still point
at it, so schema changes here can still make `spark` stale for whenever the user returns
to it.

`mobil` (the Expo app) is **fully discontinued** — the user is rebuilding the mobile
client from scratch as a native Android Studio app. Don't propose or start work in
`mobil` at all, even small fixes.

## 11. `routes/api.php` is now a real external contract, not internal-only code

Because the future native Android app will consume this same Laravel API, treat
`routes/api.php` and its controllers (`app/Http/Controllers/Api/**`) as a stable public
contract from now on, not code that's free to reshape casually:
- Don't rename, remove, or change the shape of existing API routes/response fields
  without calling it out explicitly — a silent breaking change here has no compiler or
  test to catch it from the consuming app's side, since that app doesn't live in this
  repo.
- Prefer additive changes (new fields, new endpoints) over changing what already exists.
  If a breaking change is genuinely needed, say so and consider versioning
  (`/api/v2/...`) rather than mutating `/api/v1/...` in place.
- This repo already has `knuckleswtf/scribe` installed (`config/scribe.php`) for API
  documentation. When adding or changing an API endpoint, keep its Scribe annotations
  (`@group`, `@bodyParam`, `@response`, etc.) accurate and regenerate the docs
  (`php artisan scribe:generate`) rather than letting them drift — the whole point of
  documenting the API now is that an external client (not yet written) will rely on it
  being correct.

## 12. `barber_db` was fully wiped and reseeded on 2026-09-04 — do not reintroduce mass synthetic data

The shared Atlas database had accumulated **~214,623 synthetic appointments**, **~323,095
synthetic loyalty transactions**, and **~4,767 extra users** from repeated full-seeder
runs over time (the exact same failure mode as the incident in guardrail #2 above,
just discovered later and at even larger scale). The project owner explicitly confirmed
wiping every collection and starting over. Current real state: only 4 accounts exist
(one per role: administrador, recepcionista, barbero, cliente — see
`docs/ACCESOS.md`), zero appointments/payments/products/services/orders. The team is
now deliberately loading only real business data going forward.

**Never run `php artisan migrate --seed` or the full `DatabaseSeeder`** in this repo
again without explicit confirmation — it chains `BarberSeeder` (50 fake barbers) and
`ClientSeeder` (1500 fake clients), plus appointment/payment/loyalty seeders on top,
which is exactly how the database got this bloated in the first place. If the app needs
to boot from scratch, seed only `RolePermissionSeeder` + `AdminUserSeeder` (see
README.md's install steps) and create any additional accounts individually
(`User::create()` + role via `assignRole()`), never through the mass seeders. A backup
of what existed before the wipe (30 clients, 8 barbers, 184 payments, 41 products, 330
real appointments, etc., as JSON) exists outside this repo — point the user to ask for
it explicitly if it's ever needed, don't assume it's gone forever, but also don't assume
you can find it in the repo — it isn't there.

## 13. Never trust a client-supplied price/amount for real money or inventory — always reread the source of truth server-side

Found and fixed twice in one session (see commit `be0db3b`): `OrderService::place()`
used to trust `precio` from its `$items` array as-is (an authenticated client could POST
`precio: 0.01` and get real products at an arbitrary price, with real stock deducted),
and `PaymentService::create()` used to trust `payload['monto']` from the reception charge
form as-is (staff could type any amount, never validated against the appointment's real
service price). Both are now fixed by always rereading `Product::precio_venta` /
`Appointment->service->precio` (or `precio_cobrado`) inside the service layer itself,
never from request input. **When adding any new code path that creates an `Order` or a
`Payment`, or that otherwise moves money or decrements stock, follow this same pattern**:
compute the authoritative amount from the database record the request is *about*, and
treat any amount/price field in the request body as display-only, never as the value
actually charged or decremented. This applies to the Stripe intent creation too
(`Api/PaymentController::stripeIntent()`) — the amount sent to Stripe is computed
server-side from the appointment + client's loyalty level + points, never from a
client-supplied number.

## 14. Loyalty points redemption + level discount are live, wired into every charge path

`LoyaltyService::applyDiscount()` (nivel %) and `redeemPoints()` / `maxRedeemablePoints()`
(1 point = $1 MXN, capped at 50% of the post-discount total) are called automatically
by `PaymentService::create()` (reception charge — efectivo/tarjeta), `uploadTransferReceipt()`
(client-initiated transfer), and the Stripe flow (`stripeIntent()` +
`StripeWebhookController::onSucceeded()`, which now also routes through
`PaymentService::create()` instead of a raw `Payment::create()` so it gets the same
points/PDF/notification treatment). If you touch any of these paths, keep the discount →
points-redemption order (discount first, then points on the reduced total) and the 50%
cap consistent across all of them — don't let one charge path drift from the others
again. The Alpine.js math that previews this to staff before charging
(`resources/js/loyalty-charge.js`, `window.UrbanBladeLoyalty.computeCharge`) is shared by
both `payments/create.blade.php` and the quick "Cobrar" modal in
`appointments/index.blade.php` — if the business rule changes, update that one file, not
the Blade templates directly (they were duplicated before and drifted; that's fixed now,
don't reintroduce the duplication).

## 15. Payment methods are exactly three: efectivo, transferencia, tarjeta (beta)

QR was removed as a payment method entirely (kept only in read-only historical displays
like the payments index filter, so old records with `metodo_pago: 'qr'` still render).
"Tarjeta" and the old separate "Stripe" button were merged into one real Stripe-backed
flow marked BETA in the UI — there is no more "manual card entry with no real charge"
option. The quick "Cobrar" modal (appointments list) intentionally has no card option at
all (no card-capture UI there); card payments must go through the full
`/payments/create` form. Don't reintroduce a QR button or a non-Stripe "tarjeta" option
without discussing it first.

## 16. Documentation lives in specific places — check before creating a new file

Credentials/access info: **`docs/ACCESOS.md`** is the single source of truth (not the
repo root, not `README.md`, not `docs/DEMO_DEMOSTRACION.md` — those link to it instead of
repeating the table; three independent copies is exactly how this drifted the first
time). Other docs: `docs/DOCUMENTACION_TECNICA.md` (architecture/dataset), `docs/MANUAL_USUARIO.md`
(end-user guide), `docs/DEMO_DEMOSTRACION.md` (presentation script). **Before creating or
substantially editing any `.md` file, grep the whole repo for related content first**
(`grep -rln "<the thing you're about to document>" --include="*.md" .`) — fixing one file
at a time as a human points out staleness, instead of sweeping everything related in one
pass, is exactly the mistake that caused this rule to be written. This repo (`KikeGonRam/barber`)
is **public on GitHub** — `docs/ACCESOS.md` contains real (if low-stakes, rotatable)
passwords by the project owner's explicit, informed choice, not an oversight; don't
"fix" that by removing it without asking, but also don't casually add more real secrets
to any public-repo file without the same explicit confirmation.

## 18. New sibling repo `frontend-urban` (Nuxt) is decoupling the frontend from this one

As of 2026-09-04/05, the team decided to migrate off Blade+Inertia to a separate Nuxt 4
app: `C:\Users\luis1\Documents\UrbanBlade\frontend-urban`
(`https://github.com/KikeGonRam/frontend_Urbanblade.git`). It consumes this repo's
existing JSON API (`routes/api.php`) over the same custom Bearer-token system described
in guardrail #11 (`mobile_api_tokens` / `AuthenticateMobileApiToken`, **not** Sanctum —
that repo's own plan doc had the same "Sanctum" mislabel initially and was corrected).
Full plan/architecture: `frontend-urban/.claude/skills/nuxt-migration-plan/SKILL.md`.

Concretely, this means work in **this** repo should expect, as the Nuxt migration
proceeds:
- `config/cors.php` needs to be published and configured (it does not exist yet) to
  allow the Nuxt origin on `api/*` — don't be surprised if a task asks for this.
- `Api/Dashboard/DashboardController` currently returns raw `DashboardService` output
  (no computed/formatted fields like the Inertia dashboards have) — expect requests to
  enrich it to match, per guardrail #11's "keep the API contract additive/stable" rule.
- The Inertia+Vue pages from the migration in `.claude/skills/inertia-vue-migration/`
  are **not being removed yet** — they stay live as a fallback until Nuxt reaches
  feature parity. Don't delete Inertia pages/routes without the user explicitly
  confirming Nuxt has replaced them.
- This does NOT reopen `spark`/`mobil` scope (guardrail #10 still applies) — it's a new,
  separate, currently-active third repo alongside `barber`.

## 20. Some models bind routes by a pretty key, not `id` — `Client`/`Barber`/`Service` use `slug`, `Appointment` uses `code`

`grep -rl getRouteKeyName app/Models app/Traits` finds exactly two traits:
`HasSlug` (used by `Client`, `Barber`, `Service` — `getRouteKeyName()` returns `'slug'`)
and `HasPublicCode` (used only by `Appointment` — returns `'code'`). Every other model
route-binds by plain `id` as usual. This matters because it's easy to miss: `Model::find($id)`
and `Model::where('_id', $id)->first()` both succeed for these models regardless of the
override (confirmed directly — this isn't a "record doesn't exist" bug), but Laravel's
*implicit route-model binding* on a `PUT`/`PATCH`/`DELETE {model}` route segment uses
`getRouteKeyName()`, so a URL built with the wrong field 404s with "No query results for
model" even though the record is real and findable. The JSON payloads for these models
already include both fields (`id` and `slug`/`code`) — the bug is consuming code picking
`id` because it looks like the obvious identifier, not because the API is missing anything.

This already caused two real, live-tested bugs while building `frontend-urban`'s Fase 9
(see that repo's `.claude/skills/nuxt-migration-plan/SKILL.md`): the Clientes CRUD page
initially used `client.id` for edit/delete URLs (fixed to `client.slug`), and — more
subtly — `Api/Dashboard/DashboardController::barberPayload()`'s `barberToday`/
`barberPending` arrays never included `code` at all, so the barbero dashboard's
Aprobar/Rechazar buttons (built in an earlier phase, before this pattern was known) were
silently broken end-to-end from the day they shipped — never caught earlier only because
no real pending appointment existed yet to click-test against. Fixed by adding `'code' =>
$appt->code` to both arrays. **Before exposing any `Client`/`Barber`/`Service`/
`Appointment` record's identifier for a consumer to build an edit/delete/status-change
URL with, use the model's actual route key (`slug`/`code`), not `id`** — and if a payload
for one of these models doesn't include that field yet, that's the bug to fix, not a
reason to fall back to `id`.

## 21. Larastan's result cache goes stale inside the persistent `barber-app` container — clear it before trusting a local "no errors"

`vendor/bin/phpstan analyse` caches per-file results at `/tmp/phpstan` *inside* the
container. Because `barber-app` is long-running across an entire work session (unlike
CI, which always starts clean), that cache can report "no errors" for a test file
that was just written or edited, even though a truly cold analysis of the exact same
file finds real errors — confirmed directly three times in one session (Fase 9.3, 9.4,
9.5 of `frontend-urban`'s migration): each time, `docker exec barber-app vendor/bin/phpstan
analyse` passed locally right after writing a new `tests/Feature/*ApiTest.php` file, the
push then failed Larastan in CI, and re-running locally with a cleared cache
(`vendor/bin/phpstan clear-result-cache --configuration=phpstan.neon.dist`) reproduced
CI's exact errors on the first try. This is a distinct failure mode from the local-vs-CI
divergence already documented elsewhere in this project's memory (local Docker having
*extra* false positives CI doesn't) — this one is local *missing* real errors CI catches,
in the opposite direction, and it is fully reproducible and fixable with one command.

**Before treating a local "[OK] No errors" as trustworthy after writing or editing any
file phpstan analyses (`app/`, `routes/`, `tests/`), run `vendor/bin/phpstan
clear-result-cache --configuration=phpstan.neon.dist` first, then `analyse`.** Skipping
this is exactly how three avoidable CI failures happened back-to-back in one session —
each one a real error that a cold run would have caught before ever pushing.

## 19. This rule set can go stale within hours — don't treat it as complete

Every guardrail above except #1 was added or corrected on 2026-09-02/03/04/05, several of
them because something changed *during the same working session* that wrote them (the
docs drift in #16, the database wipe in #12, the payment method changes in #15 all
happened after this file already existed). Treat this skill as a snapshot, not a
guarantee: if something you observe in the actual code/data/docs contradicts what's
written here, trust what you observe and say so — don't assume the skill is more
up-to-date than reality just because it's more recently-sounding or more authoritative-looking than a quick check would be.
