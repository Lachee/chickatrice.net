import { z } from 'zod';
import { parse } from 'yaml';
import * as fs from 'node:fs';
import { building, dev } from '$app/env';
import { CONFIG_FILE } from '$app/env/private';

// `vite dev` hands `.env` to SvelteKit's own variables only, so the `env:NAME`
// references below would never see it. Node never overwrites a variable that is
// already set, so the real environment still wins.
if (dev) {
  try { process.loadEnvFile(); } catch { /* no .env, nothing to load */ }
}

/**
 * A string that may defer to somewhere else for its value, so the config file
 * itself never has to hold a secret:
 * - `env:NAME` reads the environment variable NAME (`.env` included in dev).
 * - `file:/path` reads the file at that path — e.g. a docker compose secret
 *   mounted under `/run/secrets`. Trailing newlines are trimmed.
 * Anything else is taken literally.
 */
export const secret = () => z.string()
  .min(1)
  .transform((value, ctx) => resolveSecret(value, ctx));

function resolveSecret(value: string, ctx: z.RefinementCtx): string {
  if (value.startsWith('env:')) {
    const name = value.slice('env:'.length);
    const resolved = process.env[name];
    if (!resolved) {
      ctx.addIssue({ code: 'custom', message: `environment variable ${name} is not set` });
      return z.NEVER;
    }
    return resolved;
  }

  if (value.startsWith('file:')) {
    const path = value.slice('file:'.length);
    try {
      return fs.readFileSync(path, 'utf-8').replace(/\r?\n$/, '');
    } catch {
      ctx.addIssue({ code: 'custom', message: `file ${path} could not be read` });
      return z.NEVER;
    }
  }

  return value;
}

/** A {@link secret} that has to resolve to a URL. */
const url = () => secret().pipe(z.url());

/** One claim name, or several tried in order until one is present on the profile. */
const claim = (fallback: string | string[]) => z.union([ z.string(), z.array(z.string()).min(1) ])
  .default(fallback)
  .transform((value) => (Array.isArray(value) ? value : [ value ]));

const providerSchema = z.object({
  /** Also the callback path: `<ORIGIN>/auth/callback/<id>`. */
  id: z.string().regex(/^[a-z0-9_-]+$/i, 'provider ids may only contain letters, digits, - and _'),
  name: z.string().optional(),
  /**
   * Shown on the provider's buttons. A selfh.st icon name (https://selfh.st/icons),
   * like `discord` or `authentik-light`, is the SVG unless it ends in `.png` or
   * `.webp`; anything else must be a full https URL to an image.
   */
  icon: z.union([ z.string().regex(/^[a-z0-9-]+(\.(svg|png|webp))?$/), z.url({ protocol: /^https$/ }) ]).optional(),
  type: z.enum([ 'oidc', 'oauth' ]).default('oidc'),
  clientId: secret(),
  clientSecret: secret(),
  /**
   * OIDC issuer; discovery is fetched from `<issuer>/.well-known/openid-configuration`.
   * Auth.js has no way to point it at a discovery document anywhere else.
   */
  issuer: url().optional(),
  scope: z.string().optional(),
  /** For plain OAuth2 providers with no discovery document. */
  endpoints: z.object({
    authorization: url().optional(),
    token: url().optional(),
    userinfo: url().optional(),
  }).prefault({}),
  /**
   * Which profile fields hold what. The defaults are the standard OIDC claims.
   * `username` seeds the in-game name; when none of them is present the local
   * part of the email is used, and failing that a generic name. A claim with
   * `{field}` in it is a template filled from the profile's fields, e.g.
   * `https://cdn.discordapp.com/avatars/{id}/{avatar}.png`.
   */
  claims: z.object({
    id: claim('sub'),
    username: claim([ 'preferred_username', 'nickname', 'name' ]),
    email: claim('email'),
    image: claim('picture'),
  }).prefault({}),
});

const configSchema = z.object({
  db: z.object({
    url: secret(),
  }),
  server: z.object({
    /** What players type into Cockatrice's connect dialog. */
    host: z.string().default('localhost'),
    port: z.number().int().min(1).max(65535).default(4748),
    /** Webatrice / websocket port, shown when set. */
    websocketPort: z.number().int().min(1).max(65535).optional(),
  }).prefault({}),
  cockatrice: z.object({
    /** Servatrice's `database/prefix`. Tables are `<prefix>_users` and so on. */
    prefix: z.string().regex(/^[a-z0-9_]+$/i).default('cockatrice'),
    /** Servatrice's `server/id`: whose rooms, server message and sessions the admin pages work with. */
    serverId: z.number().int().min(1).max(255).default(1),
    /**
     * Servatrice's `security/trusted_sources`. Its own bans never ban these
     * addresses (a proxy's, say, which every player would share), and nor do
     * the admin pages.
     */
    trustedSources: z.array(z.string().min(1)).default([ '127.0.0.1', '::1' ]),
    /** Servatrice's `server/officialwarnings`, without the `|level`s: the reasons offered when warning a player. */
    officialWarnings: z.array(z.string().min(1)).default([
      'Abusive Language', 'Calling Out User', 'Causing Drama', 'Cheating', 'Disrespecting Staff', 'Disrupting a Draft',
      'Inappropriate Avatar', 'Inappropriate Game Name', 'Kicking Without Valid Reason', 'Spamming', 'Targeted Harassment',
    ]),
    /**
     * How new passwords are stored. `legacy` is the salted SHA-512 every released
     * Servatrice understands; `scrypt` is the verifier format newer Servatrice
     * builds use (and migrate legacy rows to on login). Both are always accepted
     * when checking a password.
     */
    passwordFormat: z.enum([ 'legacy', 'scrypt' ]).default('legacy'),
    /** Servatrice's `users/minpasswordlength`. */
    minPasswordLength: z.number().int().min(1).default(6),
    /** Hours a player must wait between renaming themselves. Names the site picks, and staff renames, don't count. */
    nameChangeCooldownHours: z.number().min(0).default(24),
    /**
     * Servatrice's `[users]` name rules. Names derived from a provider are made
     * to fit these, so keep them in step with servatrice.ini or the server will
     * turn away accounts this site created.
     */
    usernames: z.object({
      minLength: z.number().int().min(1).default(6),
      maxLength: z.number().int().min(1).max(35).default(12),
      allowLowercase: z.boolean().default(true),
      allowUppercase: z.boolean().default(true),
      allowNumerics: z.boolean().default(true),
      allowedPunctuation: z.string().default('_.-'),
      allowPunctuationPrefix: z.boolean().default(false),
      disallowedWords: z.array(z.string().min(1)).default([ 'admin' ]),
      disallowedRegExp: z.array(z.string().min(1)).default([]),
    }).prefault({}),
  }).prefault({}),
  stats: z.object({
    /**
     * Seconds since Servatrice last wrote to the uptime table after which the
     * server counts as offline. It writes every `server/statusupdate` ms (15s
     * by default), so leave room for a missed beat or two.
     */
    offlineAfterSeconds: z.number().int().min(1).default(45),
    /** Days of sessions the country and daily charts cover. */
    days: z.number().int().min(1).max(365).default(14),
  }).prefault({}),
  avatar: z.object({
    /** Avatars are scaled to fit this square, in pixels, and stored as PNG. */
    size: z.number().int().min(16).max(1024).default(256),
    /** Largest upload accepted, in bytes, before any resizing. */
    maxUploadBytes: z.number().int().min(1024).default(5 * 1024 * 1024),
  }).prefault({}),
  decks: z.object({
    /**
     * Decks an account may keep before the site stops adding more (uploads,
     * imports and copies). Decks uploaded from the client aren't held to it.
     */
    maxPerAccount: z.number().int().min(1).default(200),
  }).prefault({}),
  /** Umami (https://umami.is) analytics. Left out, nothing is tracked. */
  umami: z.object({
    /** Where the Umami instance is served, e.g. `https://umami.example.com`. */
    host: url().transform((value) => value.replace(/\/+$/, '')),
    /** The website's id, from its settings in Umami. */
    websiteId: secret().pipe(z.string().regex(/^[\w-]+$/, 'not a website id')),
    /** Report page load times (Web Vitals) as well. */
    performance: z.boolean().default(true),
    /** Load Umami's recorder for session replays and heatmaps; switch either on in Umami too. */
    replays: z.boolean().default(false),
    heatmap: z.boolean().default(false),
  }).optional(),
  auth: z.object({
    /** Auth.js encrypts the cookies that carry a sign-in to the provider and back with it. Generate with `openssl rand -hex 32`. */
    secret: secret().pipe(z.string().min(32, 'use at least 32 characters')),
    /** Days a sign-in lasts without a visit. Each visit past the halfway mark renews it. */
    sessionDays: z.number().int().min(1).default(30),
    /** Let accounts with no provider linked sign in with their in-game name and password. */
    allowPasswordLogin: z.boolean().default(true),
    /** Let anyone sign up through a provider. Off, providers can only be linked to existing accounts. */
    allowRegistration: z.boolean().default(true),
    providers: z.array(providerSchema).default([]).superRefine((providers, ctx) => {
      const seen = new Set<string>();
      for (const [ index, provider ] of providers.entries()) {
        if (seen.has(provider.id))
          ctx.addIssue({ code: 'custom', path: [ index, 'id' ], message: `duplicate provider id ${provider.id}` });
        seen.add(provider.id);

        if (provider.type === 'oidc' && !provider.issuer)
          ctx.addIssue({ code: 'custom', path: [ index ], message: 'an oidc provider needs an issuer URL' });

        const { authorization, token, userinfo } = provider.endpoints;
        if (provider.type === 'oauth' && (!authorization || !token || !userinfo))
          ctx.addIssue({ code: 'custom', path: [ index, 'endpoints' ], message: 'an oauth provider needs authorization, token and userinfo endpoints' });
      }
    }),
  }),
}).superRefine((config, ctx) => {
  const { minLength, maxLength } = config.cockatrice.usernames;
  if (minLength > maxLength)
    ctx.addIssue({ code: 'custom', path: [ 'cockatrice', 'usernames' ], message: 'minLength is longer than maxLength' });
});

export type Config = z.output<typeof configSchema>;
export type AuthProviderConfig = Config['auth']['providers'][number];
export type UsernameRules = Config['cockatrice']['usernames'];

/**
 * SvelteKit's post-build analyse pass imports the server modules, so it would
 * otherwise need a real config file (and its secrets) just to build. Nothing
 * runs against these values — no request is served and no session is signed.
 */
const BUILD_PLACEHOLDER = {
  db: { url: 'mysql://build:build@127.0.0.1:3306/build' },
  auth: { secret: 'build-stage-placeholder-that-signs-nothing' },
};

function loadConfig(filepath: string): Config {
  if (building)
    return configSchema.parse(BUILD_PLACEHOLDER);

  let content: string;
  try {
    content = fs.readFileSync(filepath, 'utf8');
  } catch (cause) {
    throw new Error(`could not read config file ${filepath} — copy config.example.yaml to config.yaml, or point CONFIG_FILE at one`, { cause });
  }

  const result = configSchema.safeParse(parse(content) ?? {});
  if (!result.success)
    throw new Error(`invalid config file ${filepath}:\n${z.prettifyError(result.error)}`);

  return result.data;
}

function deepFreeze<T>(value: T): T {
  if (value !== null && typeof value === 'object' && !Object.isFrozen(value)) {
    Object.freeze(value);
    for (const child of Object.values(value))
      deepFreeze(child);
  }
  return value;
}

export const config: Readonly<Config> = deepFreeze(loadConfig(CONFIG_FILE || 'config.yaml'));
