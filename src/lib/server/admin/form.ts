/** Reading the admin pages' forms: everything arrives as strings, or not at all. */

/** The field, trimmed; '' when it's missing. */
export const text = (form: FormData, name: string) => String(form.get(name) ?? '').trim();

/** A whole number, or null when the field is missing or isn't one. */
export function integer(form: FormData, name: string): number | null {
  const value = text(form, name);
  return /^-?\d+$/.test(value) ? Number(value) : null;
}

/** A checkbox: present means ticked. */
export const checked = (form: FormData, name: string) => form.get(name) !== null;

/** Escapes `%`, `_` and `\` so user input can sit inside a LIKE pattern. */
export const likeEscape = (value: string) => value.replace(/[\\%_]/g, (c) => `\\${c}`);
