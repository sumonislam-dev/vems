# Laravel 12 → 13 / Inertia 2 → 3 Upgrade Plan

Status: **proposed, not yet executed**. This document was produced by checking every
breaking-change item in the official Laravel 13 and Inertia 3 upgrade guides against
this specific codebase (grep-verified, not assumed) before deciding what actually needs
to change.

## Bottom line

This upgrade is smaller than it sounds. Laravel's own guide rates 12→13 as roughly a
"10 minute" upgrade, VEMS doesn't use most of the patterns that break, and — the part
that took the most care — **the whole upgrade fits inside PHP 8.3** (the current WAMP
PHP is 8.3.6) as long as specific dependency majors are pinned rather than jumping to
whatever is newest:

| Package | Latest major | PHP floor | What to actually use | Why |
|---|---|---|---|---|
| `pestphp/pest` | v5.x | **^8.4** | **v4.7.x** | v4 already supports Laravel 13, needs only PHP ^8.3 |
| `spatie/laravel-activitylog` | v5.x | **^8.4** | **stay on v4.12.x** | v4.12.3 already supports Laravel 13 — no bump needed at all |
| `spatie/laravel-permission` | v8.x | ^8.3 | **v7.2.x** (not v8) | Smaller single-major jump, still gets Laravel 13, less API-change risk |
| `maatwebsite/excel` | v4.x | ^8.3 | **stay on ^3.1** | v3.1.70 already supports Laravel 13 — no bump needed |
| `barryvdh/laravel-dompdf` | — | — | **stay on ^3.1** | Already supports Laravel 13 |
| `tightenco/ziggy` | — | — | **stay on ^2.4** | Already supports Laravel 13 |

Blindly running `composer update` to latest-everything would have silently required
PHP 8.4 (not installed on this box) via Pest 5 and `laravel-activitylog` 5. Picking the
majors above avoids that entirely.

## Composer changes required

- `laravel/framework`: `^12.0` → `^13.0`
- `inertiajs/inertia-laravel`: `^2.0` → `^3.0`
- `laravel/tinker`: `^2.10.1` → `^3.0`
- `spatie/laravel-permission`: `^6.21` → `^7.2`
- `pestphp/pest` / `pestphp/pest-plugin-laravel` (dev): `^3.8` / `^3.2` → `^4.7` / `^4.1`
- Everything else (dompdf, ziggy, excel, mockery, faker, sail, pail, collision) needs
  **no constraint change** — each was individually checked against Laravel 13 support.

## npm changes required

- `@inertiajs/react`: `^2.0.0` → `^3.0.0` — React 19 is a peer requirement of v3, and
  the project is already on React 19, so this is friction-free.
- **Add `axios` as an explicit direct dependency.** `resources/js/pages/user-groups/show.tsx`
  does `import axios from 'axios'` today, but axios is **not in `package.json`** — it
  currently only resolves because `@inertiajs/react` v2 bundles axios as a transitive
  dependency that npm happens to hoist. Inertia v3 drops axios entirely, so this import
  would hard-break the build the moment the upgrade lands unless axios is added
  explicitly. (Pre-existing latent fragility, not something the upgrade introduces —
  worth fixing regardless.)
- No other npm packages need bumping — `vite`, `laravel-vite-plugin`, `tailwindcss`,
  etc. are all decoupled from Inertia's version.

## Actual code changes (verified against this repo)

### Backend — required

1. `resources/views/app.blade.php:34` — `<title inertia>` → `<title data-inertia>`
   (Inertia 3's head-management rewrite requires the `data-` prefixed attribute).
2. `config/inertia.php` — restructure the `testing.page_paths` / `testing.page_extensions`
   keys into a new top-level `pages.paths` / `pages.extensions` block, keeping
   `testing.ensure_pages_exist => true` (this file already opts into that check and
   it's worth keeping).
3. CSRF middleware renamed `VerifyCsrfToken` → `PreventRequestForgery` in Laravel 13.
   Grepped the whole app — no direct reference to the class exists anywhere, so this is
   a non-event here; noted only because it's the source of Laravel 13's stricter CSRF
   behavior (adds a `Sec-Fetch-Site` header check).

### Backend — verified NOT applicable (checked and ruled out)

- No `Inertia::lazy()` calls (removed in Inertia v3) — 0 matches.
- No `->upsert()` calls affected by the new empty-`uniqueBy` validation.
- `Trip::boot()` doesn't instantiate `new static()` during its own boot cycle (the
  newly-disallowed pattern) — it only creates unrelated `TripAuditLog` rows.
- No cached PHP objects hit the new `serializable_classes` allow-list requirement —
  the one `Cache::remember()` call (`LocationResolver`) caches a plain geocoded string.
- No `exceptionOccurred` / `QueueBusy->connection` listeners, no custom
  `Str::create*Using()` factories in tests, no direct `Inertia\Testing\Concerns\*`
  trait usage (tests already use the modern `assertInertia()` fluent API).

### Frontend — required

4. `resources/js/app.tsx` — the global error-toast handler added this session must be
   updated: `router.on('invalid', ...)` / `router.on('exception', ...)` →
   `router.on('httpException', ...)` / `router.on('networkError', ...)` (Inertia 3
   renames both global events).
5. No code change needed in `user-groups/show.tsx` itself once axios is a real
   declared dependency (see npm section above) — just the missing `package.json` entry.

### Frontend — verified NOT applicable

- No page uses the `Page.layout = arrowFn` pattern that v3 breaks (VEMS wraps layouts
  inline inside each page component instead) — 0 matches.
- No `router.cancel()`, no `hideProgress` / `revealProgress` imports, no `future`
  config block in `createInertiaApp()`, no direct `qs` / `lodash` imports, no
  CommonJS `require()` anywhere in `resources/js` (already fully ESM, satisfies v3's
  ESM-only requirement).

## Rollout order

1. **Backend first, in isolation**: bump composer deps, apply the two required
   Laravel-13 code touches, run the full Pest suite, fix whatever Laravel 13's
   stricter behaviors (CSRF, cache hardening) surface in practice beyond what static
   grepping can predict.
2. **Frontend second**: bump `@inertiajs/react` and add `axios`, apply the
   `app.blade.php` and `app.tsx` changes and the `config/inertia.php` restructure,
   rebuild, then re-run a live browser smoke check (login → navigate → submit a form →
   confirm no console errors, confirm the error-toast path still fires).
3. **Full regression**: Pest suite, `npm run types` / `lint`, a manual pass through
   the core flows (trip create/approve/complete, vehicle assignment, login) in a real
   browser.

## Explicitly out of scope

- Vite 7→8 — forced only by `laravel-vite-plugin` v3, which nothing here requires;
  the current v2 plugin is fine on Laravel 13 / Inertia 3.
- Chasing every package to its absolute latest major (see the Pest / activitylog /
  permission reasoning above) — smaller deltas, less risk, same end capability.
- SSR verification beyond docs-reading. `config/inertia.php` has `ssr.enabled => true`
  and a `composer dev:ssr` script exists; the Inertia guide says SSR keeps working,
  but this should be verified hands-on during execution, not assumed from the guide.
