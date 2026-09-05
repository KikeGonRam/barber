---
name: laravel-13-reference
description: >
  What's actually new/changed in Laravel 13 (this repo's current framework version, since
  2026-09-05) and what Laravel Boost is. Consult this BEFORE assuming a Laravel API doesn't
  exist yet, before another Laravel major-version upgrade, before suggesting/installing
  `laravel/boost`, and before reaching for phpstan-baseline.neon fixes after any
  Laravel/Larastan bump (message wording drift is expected, not a real regression — see
  below). Training-data knowledge cuts off before Laravel 13 existed, so verify new-API
  claims against `composer show laravel/framework` in this repo rather than assuming.
---

# Laravel 13 reference (this repo)

This repo (`barber`) runs **Laravel 13** (bumped from 12 on 2026-09-05, commit `be5a428`),
**PHP 8.3+** (container/CI actually run 8.4), **MongoDB via `mongodb/laravel-mongodb`
^5.0** (no SQL database at all — this matters a lot for which Laravel 13 features apply,
see below). Support: bug fixes until ~Q3 2027, security fixes until March 17, 2028.

## Confirmed ecosystem compatibility (checked 2026-09-05, not from memory)

Every direct dependency in `composer.json` already declares Laravel 13 support in its
current major version — `mongodb/laravel-mongodb` (the one most likely to lag) supports
`^12|^13.0` as of 5.10.0. If upgrading again to a future Laravel major, re-verify the same
way instead of assuming: `docker exec barber-app php -r '$j=json_decode(file_get_contents("https://repo.packagist.org/p2/mongodb/laravel-mongodb.json"),true); print_r($j["packages"]["mongodb/laravel-mongodb"][0]["require"]);'`

## What's actually new in Laravel 13 (vs. 12)

Laravel's own release notes call this "a relatively minor upgrade in terms of effort" —
most apps need no code changes. Full breaking-changes list:
https://laravel.com/docs/13.x/upgrade (fetch it fresh; don't rely on a cached memory of it,
this repo's own upgrade already found the guide's own summary was reliable).

**Usable in this MongoDB-based app** (worth reaching for on new work):
- `PreventRequestForgery` replaces `VerifyCsrfToken` (old class kept as a deprecated
  alias). Already the name used in this repo's `routes/api.php` for the Stripe webhook
  exclusion — use the new name in any new code, don't reintroduce the old one.
- New PHP attributes: `#[Middleware(...)]` / `#[Authorize(...)]` on controllers,
  `#[Tries]` / `#[Backoff]` / `#[Timeout]` / `#[FailOnTimeout]` on queued jobs. Purely
  additive, DB-agnostic — safe to use, but this repo doesn't use them anywhere yet, so
  don't mix styles inside one controller/job without a reason.
- `Cache::touch($key, $seconds)` — extend a cache entry's TTL without re-fetching/
  re-storing the value. Works with the `redis` store this app uses.
- `Queue::route(JobClass::class, connection: ..., queue: ...)` — central queue/connection
  routing by job class, instead of hardcoding `->onQueue()`/`->onConnection()` at every
  dispatch call site.
- JSON:API resource support (`Illuminate\Http\Resources\Json` additions) — could
  standardize the mobile API's response shape, but `routes/api.php` is treated as a real
  external contract in this repo (see the guardrails skill) — do NOT reshape existing
  endpoint responses to this format without the project owner's explicit sign-off.

**NOT applicable to this app — do not suggest these:**
- Semantic/vector search (`whereVectorSimilarTo`, embeddings-backed search) —
  **requires PostgreSQL + the `pgvector` extension**. This app has no SQL database at
  all (MongoDB only), so this entire feature area is a non-starter here.
- Laravel AI SDK (`laravel/ai`, provider-agnostic text/image/audio/embeddings) — not
  installed, and would need its own composer require + provider API keys/cost
  evaluation. Interesting for the existing chatbot feature (`app/Http/Controllers/
  Chatbot/ChatbotController.php`) as a *future* research topic, not something to add
  casually — flag it as an idea, don't install it unprompted.

## Cache `serializable_classes` hardening (opt-in, not automatic)

Laravel 13's skeleton ships `config/cache.php` with `'serializable_classes' => false` by
default for *new* apps. This repo's existing `config/cache.php` does **not** have that
key, and the framework only restricts `unserialize()` when the key is explicitly set
(confirmed by reading `vendor/laravel/framework/src/Illuminate/Cache/RedisStore.php`
during the upgrade — `$serializableClasses !== null` gate). So existing Redis-cached
Eloquent objects (`BarbershopSetting::first()`, landing-page `Barber`/`Service`
collections) kept working unchanged after the upgrade with zero config changes. Adding an
explicit allow-list is a legitimate follow-up security hardening, but it's a separate,
deliberate task (enumerate every `Cache::remember` that stores an object, not just
arrays/scalars) — don't bundle it into an unrelated change.

## After ANY Laravel/Larastan/PHPStan version bump: expect baseline wording drift

Bumping `laravel/framework` (or just `larastan/larastan`/`phpstan/phpstan` alone) commonly
changes error MESSAGE TEXT for already-known issues (e.g. `string` → `literal-string` in
type-mismatch messages) without changing which lines are actually flagged. When this
happens, `phpstan-baseline.neon`'s regex patterns stop matching and phpstan aborts with
"was not matched in reported errors" for dozens of entries at once — this is NOT 30 new
bugs, it's baseline staleness from the tool itself. Fix: regenerate wholesale rather than
hand-patching each pattern:

```bash
docker exec barber-app ./vendor/bin/phpstan analyse --generate-baseline --memory-limit=1G
```

Then sanity-check the entry COUNT is roughly the same before/after
(`grep -c "count:" phpstan-baseline.neon` on both versions via `git show HEAD:...`) to
confirm it's a wording refresh and not an actual loss of coverage, before committing.

## Laravel Boost — what it is, and why it's not installed here (yet)

`laravel/boost` is already present in `composer.json` (`require-dev`, `^2.0`) but **has
never been installed** in this repo — no `.mcp.json`, no `.ai/` directory exist. It's
dormant: just sitting in `composer.lock` because Laravel 13's own composer constraints
pulled in a compatible version during the framework upgrade.

**What it actually is** (official, first-party, laravel/boost on GitHub, ~3.6k stars):
an MCP server + AI guideline/skill generator specifically for Laravel apps. Once
installed via `php artisan boost:install`, it gives Claude Code (and Cursor/Codex/Gemini
CLI/Copilot) a `laravel-boost` MCP server exposing tools like **Database Query** (runs
arbitrary queries against your app's DB connection), Database Schema, Application Info,
Read Log Entries, Search Docs (17,000+ piece semantic-search Laravel knowledge base), and
a `record-rule` tool for teaching agents your app's own conventions
(`.ai/rules/*.md`, committed to the repo). It also generates per-package "Agent Skills"
(Livewire/Inertia/Pest/Tailwind/etc. — mostly N/A here since this stack doesn't use any
of them) and an `infer-conventions` skill that sweeps an EXISTING app to auto-document its
real conventions into `.ai/rules/`.

**Two concrete reasons NOT to run `php artisan boost:install` on this repo without asking
the project owner first, even though it's a legitimate official tool:**

1. Boost's docs explicitly describe `CLAUDE.md`/`AGENTS.md` as files it "automatically
   regenerates" on `boost:install`/`boost:update`, and suggest gitignoring them. This
   repo's `CLAUDE.md`/`AGENTS.md` are hand-built, carry the ENTIRE guardrails history for
   this project (payment-security rules, DB-wipe incident notes, doc-drift lessons, the
   API-external-contract warning, etc.) and are meant to be read by *any* AI provider,
   not just Claude — running the install command risks silently clobbering all of that
   unless you inspect exactly what it writes first (`--dry-run` if available, or diff
   after running in a throwaway branch).
2. The **Database Query** MCP tool would let an agent execute arbitrary queries against
   whatever DB connection Laravel currently resolves to — in a repo with **two
   documented real incidents** of automation wiping/bloating the shared Atlas
   `barber_db` (see the `urbanblade-guardrails` skill), handing out unscoped live-query
   access is exactly the kind of capability that caused those incidents, unless it can be
   verified/pinned to only ever hit the local `mongo-test` connection.

**If the project owner wants to proceed anyway:** do it in an isolated worktree/branch
first, diff every file it touches or creates before merging, and treat the DB Query tool
as read-only-until-proven-otherwise (test against `barber_db_test` only, never the
default connection, until that's confirmed safe).
