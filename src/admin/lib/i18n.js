import { adminConfig } from "./api";

const dict = adminConfig.i18n || {};

/** BCP 47 tag of the admin user's locale, for dates and numbers (browser default if unknown). */
export const locale = adminConfig.locale || undefined;

/**
 * Translate an English UI string. The English text is the key, so a missing
 * translation falls back to English. Each %s is replaced by the next argument.
 */
export function t(text, ...args) {
  let i = 0;
  return String(dict[text] ?? text ?? "").replace(/%s/g, () =>
    String(args[i++] ?? ""),
  );
}
