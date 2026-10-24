# chickatrice.net — working notes

Website for a Cockatrice server: public statistics, and OIDC (Auth.js)/password sign-in to manage
the in-game (Servatrice) account. SvelteKit 3 (Svelte 5, runes-only) + Drizzle on MySQL.
Structured after labooru (YAML config, Drizzle migrations on start, cron, Docker).
The original PHP site lives on `master` / `v1.0.0` — useful for how things used to work.

## Layout

- `src/lib/server/cockatrice/` — everything that touches Servatrice's tables:
  `accounts.ts` (create/find/update `cockatrice_users`), `password.ts` (both hash formats),
  `username.ts` (Servatrice's name rules + deriving a name from a provider),
  `capabilities.ts` (which optional columns this schema version has), `stats.ts`.
- `src/lib/server/auth/` — `index.ts` (Auth.js config, as in labooru), `adapter.ts` (Auth.js
  users = Cockatrice accounts), `session.ts` (DB sessions, password sign-in), `identities.ts`
  (provider ↔ account links), `guard.ts`, `ratelimit.ts`.
- `src/lib/server/db/` — `cockatrice.ts` (Servatrice tables, never migrated),
  `chickatrice.schema.ts` (ours, migrated), `migrate.ts`, `lock.ts` (MySQL named locks).
- `src/lib/components/charts/` — hand-rolled SVG charts (no chart library).
- `server.js` — production entry point; see "Gotchas".

## Rules that are easy to break

- **Never alter Servatrice's tables.** They're declared in `db/cockatrice.ts`, outside the
  `*.schema.ts` glob drizzle-kit reads, and `tablesFilter` limits it to `chickatrice_*`.
  Anything new is a `chickatrice_` table. No foreign keys into Servatrice's tables.
- **Only declare `cockatrice_users` columns every supported schema has.** Newer ones are
  probed in `capabilities.ts` and written with raw SQL (see `setPassword`).
- **Write accounts the way Servatrice does** (`registerUser`/`changeUserPassword` in
  `servatrice_database_interface.cpp`): `UTC_TIMESTAMP()` dates, `passwordLastChangedDate`
  stamped, `password_legacy_backup` cleared on a password change.
- **An empty `password_sha512` means "no password set yet"**: nothing hashes to it, so it
  can't log in. Provider sign-ups start like that; the UI nags until one is set.
- **Accounts with a linked provider can't use password sign-in** on the site (they can still
  log in to Cockatrice with it). Unlinking the last provider requires a password.
- **Times:** our tables are UTC (compare with `UTC_TIMESTAMP()`); Servatrice's uptime and
  session rows use `NOW()`, so stats work out ages in SQL and return epoch seconds.
- Stats queries are public — keep them cached (`cached()` in `stats.ts`) and indexed.
- **Databases from the old site** are converted by `0001_legacy_import.sql`: its Discord links
  become identities on provider id `discord` (keep that id), and its tables are kept as
  `legacy_chickatrice_*`, outside drizzle-kit's `chickatrice_*` filter. Nothing reads them.

## Gotchas (SvelteKit 3)

- **`$lib` is gone; use `#lib/...` with an explicit `.js` extension** (`#lib/server/db/index.js`
  for directories). Without the extension TypeScript can't resolve it.
- `$app/environment` → `$app/env`; `$env/*` → `$app/env/private` with variables declared in
  `src/env.ts`. Everything else is in `config.yaml` (`#lib/server/config.ts`), and `env:NAME`
  references there read `process.env` (`.env` is loaded by hand in dev).
- `Handle`/`ServerInit` come from `@sveltejs/kit/hooks`.
- `redirect()` refuses external URLs unless given `{ external: [...] }`.
- **adapter-node 6 has no `ORIGIN`** — it trusts the Host header and assumes https. `server.js`
  wraps the handler to restore `ORIGIN`; run `node server.js`, not `node build`.
- **Auth.js owns `/auth/*`.** Forms post straight to `/auth/signin/<id>` and `/auth/signout`,
  not through `@auth/sveltekit`'s `signIn` action (its `redirect()` to the provider throws on
  SvelteKit 3). SvelteKit's CSRF check covers those posts — in a build only; dev skips it.
- `@auth/sveltekit` only supports SvelteKit 2: `patches/@auth__sveltekit@*.patch` moves it onto
  `$app/env` and hard-codes its base path to `/auth` (no `paths.base` here). Re-check on upgrade.
- Auth.js returns its own Response for `/auth/*`, so `event.cookies` set in its callbacks are
  dropped: pass a code in the redirect URL instead (`?error=`, `?linked=`, `?welcome`).

## Commands

```sh
docker compose up db     # MySQL with Servatrice's schema (docker/servatrice.sql)
pnpm dev                 # :5173
pnpm check               # svelte-check — the closest thing to a test suite
pnpm db:generate         # after changing chickatrice.schema.ts; the app migrates on start
```
