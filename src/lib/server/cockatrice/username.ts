import type { UsernameRules } from '#lib/server/config.ts';

/*
 * Servatrice's `usernameIsValid` (servatrice_database_interface.cpp), and a way
 * to bend an arbitrary provider name into something that passes it.
 */

const escapeRegExp = (value: string) => value.replace(/[.*+?^${}()|[\]\\-]/g, '\\$&');

function allowedClass(rules: UsernameRules): string {
  return (rules.allowLowercase ? 'a-z' : '')
    + (rules.allowUppercase ? 'A-Z' : '')
    + (rules.allowNumerics ? '0-9' : '')
    + escapeRegExp(rules.allowedPunctuation);
}

/** Why `name` would be refused by Servatrice, or null if it wouldn't be. */
export function usernameProblem(name: string, rules: UsernameRules): string | null {
  if (name.length < rules.minLength || name.length > rules.maxLength)
    return `must be ${rules.minLength}–${rules.maxLength} characters`;

  if (!rules.allowPunctuationPrefix && rules.allowedPunctuation.includes(name[0]))
    return 'cannot start with punctuation';

  const lower = name.toLowerCase();
  if (rules.disallowedWords.some((word) => lower.includes(word.toLowerCase())))
    return 'contains a word that is not allowed';

  if (rules.disallowedRegExp.some((pattern) => new RegExp(pattern).test(name)))
    return 'is not allowed';

  if (!new RegExp(`^[${allowedClass(rules)}]+$`).test(name))
    return 'contains characters that are not allowed';

  return null;
}

/**
 * The stem a provider's name is turned into: accents dropped, anything Servatrice
 * wouldn't allow removed, banned words cut out, cased to fit, and trimmed to the
 * maximum length. May be shorter than the minimum — {@link usernameCandidates}
 * pads it with digits.
 */
export function usernameStem(raw: string, rules: UsernameRules): string {
  let stem = raw.normalize('NFKD').replace(/\p{Mn}/gu, '');

  if (!rules.allowLowercase) stem = stem.toUpperCase();
  else if (!rules.allowUppercase) stem = stem.toLowerCase();

  // Spaces read better as the first allowed punctuation than as nothing ("Jo Bloggs" → "Jo_Bloggs").
  const joiner = rules.allowedPunctuation[0] ?? '';
  stem = stem.trim().replace(/\s+/g, joiner);
  stem = stem.replace(new RegExp(`[^${allowedClass(rules)}]`, 'g'), '');

  for (const word of rules.disallowedWords)
    stem = stem.replace(new RegExp(escapeRegExp(word), 'gi'), '');

  if (!rules.allowPunctuationPrefix && rules.allowedPunctuation)
    stem = stem.replace(new RegExp(`^[${escapeRegExp(rules.allowedPunctuation)}]+`), '');

  return stem.slice(0, rules.maxLength);
}

/**
 * Names to try, in order, for an account seeded from `raw`: the stem itself,
 * then the stem with 1, 2, 3, … on the end, trimmed so the number always fits.
 * A stem under the minimum length is zero-padded (`bob` → `bob001`). Only names
 * Servatrice would accept are yielded; whether they are free is for the caller.
 * When `raw` can't be made into any (say a disallowed pattern matches every
 * form of it), the names come from `fallback` instead.
 */
export function* usernameCandidates(raw: string, rules: UsernameRules, fallback = 'player'): Generator<string> {
  let any = false;
  const stem = usernameStem(raw, rules);
  if (stem)
    for (const candidate of numbered(stem, rules)) {
      any = true;
      yield candidate;
    }
  if (!any) yield* numbered(usernameStem(fallback, rules), rules);
}

function* numbered(stem: string, rules: UsernameRules): Generator<string> {
  // Without digits there's no way to tell two of the same name apart.
  const limit = rules.allowNumerics ? 100_000 : 1;
  for (let i = 0; i < limit; i++) {
    let suffix = i === 0 ? '' : String(i);
    const short = rules.minLength - stem.length - suffix.length;
    if (short > 0 && rules.allowNumerics)
      suffix = suffix.padStart(suffix.length + short, '0');

    const candidate = stem.slice(0, rules.maxLength - suffix.length) + suffix;
    if (!usernameProblem(candidate, rules))
      yield candidate;
  }
}
