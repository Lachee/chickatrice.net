import { createHash, randomBytes, randomInt, scrypt as scryptCallback, timingSafeEqual, type ScryptOptions } from 'node:crypto';

/*
 * Servatrice's two stored password formats, ported from libcockatrice's
 * `PasswordHasher` (libcockatrice_utility/.../passwordhasher.cpp):
 *
 * - legacy:  <16-char salt><base64(sha512^1000(salt + password))>
 *            What every released Servatrice writes and checks.
 * - scrypt:  $scrypt$<N>$<r>$<p>$<base64 salt>$<base64 64-byte verifier>
 *            What newer Servatrice builds write. They still accept legacy rows
 *            and convert them on the next in-game login.
 *
 * An empty `password_sha512` is used here to mean "no password set yet": no
 * password ever hashes to it in either format, so nothing can log in with it.
 */

export type PasswordFormat = 'legacy' | 'scrypt';

const LEGACY_SALT_LENGTH = 16;
const LEGACY_ROUNDS = 1000;
const SALT_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

const SCRYPT_N = 32768;
const SCRYPT_R = 8;
const SCRYPT_P = 1;
const SCRYPT_SALT_LENGTH = 16;
const SCRYPT_VERIFIER_LENGTH = 64;

/** Hash `password` for storage in `cockatrice_users.password_sha512`. */
export async function hashPassword(password: string, format: PasswordFormat): Promise<string> {
  if (format === 'scrypt') {
    const salt = randomBytes(SCRYPT_SALT_LENGTH);
    const verifier = await deriveScrypt(password, salt, SCRYPT_N, SCRYPT_R, SCRYPT_P);
    return `$scrypt$${SCRYPT_N}$${SCRYPT_R}$${SCRYPT_P}$${salt.toString('base64')}$${verifier.toString('base64')}`;
  }

  return legacyHash(password, randomSalt());
}

/** Whether `password` matches what Servatrice has stored, in either format. */
export async function verifyPassword(password: string, stored: string): Promise<boolean> {
  if (!stored)
    return false;

  if (!stored.startsWith('$'))
    return safeEqual(legacyHash(password, stored.slice(0, LEGACY_SALT_LENGTH)), stored);

  const parsed = parseScrypt(stored);
  if (!parsed)
    return false;

  const derived = await deriveScrypt(password, parsed.salt, parsed.n, parsed.r, parsed.p);
  return derived.length === parsed.verifier.length && timingSafeEqual(derived, parsed.verifier);
}

function legacyHash(password: string, salt: string): string {
  let hash = Buffer.from(salt + password, 'utf8');
  for (let i = 0; i < LEGACY_ROUNDS; i++)
    hash = createHash('sha512').update(hash).digest();
  return salt + hash.toString('base64');
}

function randomSalt(): string {
  let salt = '';
  for (let i = 0; i < LEGACY_SALT_LENGTH; i++)
    salt += SALT_CHARS[randomInt(SALT_CHARS.length)];
  return salt;
}

function parseScrypt(stored: string) {
  const parts = stored.split('$');
  if (parts.length !== 7 || parts[1] !== 'scrypt')
    return null;

  const [ n, r, p ] = [ parts[2], parts[3], parts[4] ].map(Number);
  // The same bounds Servatrice enforces, so a corrupt row can't make us allocate gigabytes.
  const sane = Number.isInteger(n) && n >= 1024 && n <= 1 << 20 && (n & (n - 1)) === 0
    && Number.isInteger(r) && r >= 1 && r <= 32
    && Number.isInteger(p) && p >= 1 && p <= 16;
  if (!sane)
    return null;

  const salt = Buffer.from(parts[5], 'base64');
  const verifier = Buffer.from(parts[6], 'base64');
  if (salt.length === 0 || verifier.length !== SCRYPT_VERIFIER_LENGTH)
    return null;

  return { n, r, p, salt, verifier };
}

function deriveScrypt(password: string, salt: Buffer, n: number, r: number, p: number): Promise<Buffer> {
  // Node's default 32 MiB cap is just under what N=32768, r=8 needs.
  const options: ScryptOptions = { N: n, r, p, maxmem: 128 * n * r + 128 * r * p + 4 * 1024 * 1024 };
  return new Promise((resolve, reject) =>
    scryptCallback(Buffer.from(password, 'utf8'), salt, SCRYPT_VERIFIER_LENGTH, options, (err, key) => (err ? reject(err) : resolve(key))));
}

function safeEqual(a: string, b: string): boolean {
  const left = Buffer.from(a);
  const right = Buffer.from(b);
  return left.length === right.length && timingSafeEqual(left, right);
}
