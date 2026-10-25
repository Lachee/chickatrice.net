# chickatrice.net

The website for a [Cockatrice](https://cockatrice.github.io/) server: live status and
statistics, plus self-service accounts.

- **Statistics** — players online, games running, uptime/availability, daily players and
  games, and where players are from, all read from the tables Servatrice already keeps.
- **Sign in with OIDC / OAuth2** — the first sign-in creates a Cockatrice account named
  after the provider's username (bent to fit Servatrice's name rules, with a number added
  if it's taken). The player then sets their in-game password here.
- **Existing accounts** — anyone registered from the Cockatrice client can sign in with
  their in-game name and password, as long as they haven't linked a provider. They can
  link one from their account page.
- **Account settings** — change the in-game password, set an avatar (uploaded, or copied
  from the provider), and choose a country flag.
- **Admin tools** (`/admin`, for accounts with a staff flag in `cockatrice_users.admin`) —
  a dashboard (players, games, traffic, sign-ups, client versions), who's connected now,
  player lookup with sessions, likely alts, ban/warning history and staff notes; placing
  and lifting bans, recording warnings, activating accounts and resetting passwords;
  player reports (schema 36+); chat log and audit search; staff roles and privileges;
  and the database-held server settings: rooms and game types, the login message, and
  card art rules. Everything is done the way Servatrice's own moderator commands do it,
  and logged. Developers get the read-only parts; admins get roles and server settings.

SvelteKit 3 (Svelte 5) + Drizzle on the MySQL database Servatrice uses. The site reads
and writes Servatrice's own tables (`cockatrice_users` and friends) in the formats
Servatrice expects, and keeps anything extra in its own tables, all prefixed
`chickatrice_`. The original PHP site is on the `master` branch (`v1.0.0`).

## Running it

```sh
cp config.example.yaml config.yaml    # then edit: server host, providers, ...
cp .env.example .env                  # then fill in AUTH_SECRET and the provider secrets
docker compose up --build
```

That starts MySQL (seeded with Servatrice's schema on first start), Servatrice itself
on ports 4747 (TCP) and 4748 (WebSocket), and the site on <http://localhost:3000>.
Servatrice is Cockatrice's own image (`ghcr.io/cockatrice/servatrice`), with its settings
in `docker/servatrice.ini`. `SERVATRICE_TAG` picks another release, but it must use the
same schema version as `docker/servatrice.sql` (3.1.1's, version 37).

To run against an existing Servatrice database, only the `app` service (or the image on
its own) is needed: set `DATABASE_URL`, mount `config.yaml` at `/config/config.yaml`, and
run `node server.js` (the image's default command). On start it applies its own
migrations; it never alters Servatrice's tables.

### Things to get right in production

- **MySQL strict mode must be off** — Servatrice refuses to start with it on, and its
  schema's defaults need it off (`--sql-mode=NO_ENGINE_SUBSTITUTION`, as in the compose file).
- **`ORIGIN`** is the public address (`https://chickatrice.net`). Provider callback URLs
  are `<ORIGIN>/auth/callback/<provider id>`; register those with each provider.
- **Behind a reverse proxy, set `ADDRESS_HEADER`** (e.g. `X-Forwarded-For`, with
  `XFF_DEPTH`). Login attempts are rate limited per client address; without it every
  visitor shares the proxy's address and one budget.
- **Keep `cockatrice.usernames` and `minPasswordLength` in step with `servatrice.ini`**, or
  accounts made here may be ones Servatrice won't accept.
- **`cockatrice.passwordFormat`** — `legacy` (the default) works with every released
  Servatrice. Newer Servatrice builds also understand `scrypt`; both are always accepted
  when signing in.

## Developing

```sh
pnpm install
docker compose up db        # or `pnpm db:start`
pnpm dev                    # http://localhost:5173 (reads config.yaml and .env)
pnpm check                  # svelte-check: the test suite there is
```

Schema changes to the `chickatrice_*` tables go in `src/lib/server/db/chickatrice.schema.ts`,
then `pnpm db:generate` writes a migration into `drizzle/`, which the app applies on its
next start. `db:push` is disabled: it would bypass the migration history.

See `CLAUDE.md` for the architecture and the gotchas.
