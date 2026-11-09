// WCAG contrast helpers: the brand color is chosen by the practice, so text colors are derived from it.
const DEFAULT = "#0f766e";
const DARK = "#111827";

function parseHex(value) {
  const m = /^#?([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(String(value || "").trim());
  if (!m) return null;
  let h = m[1];
  if (h.length === 3) h = h.replace(/./g, "$&$&");
  return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16));
}

function luminance([r, g, b]) {
  const lin = [r, g, b].map((v) => {
    const c = v / 255;
    return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2];
}

function contrast(a, b) {
  const [hi, lo] = a > b ? [a, b] : [b, a];
  return (hi + 0.05) / (lo + 0.05);
}

const toHex = (rgb) =>
  "#" + rgb.map((v) => Math.round(v).toString(16).padStart(2, "0")).join("");

/**
 * Text color on top of the brand color. "auto" picks the more readable one (WCAG);
 * "light" / "dark" are the owner's explicit choice (e.g. white on a bright brand orange).
 */
function pickOn(lum, mode) {
  if (mode === "light") return "#ffffff";
  if (mode === "dark") return DARK;
  return contrast(lum, 1) >= contrast(lum, luminance(parseHex(DARK)))
    ? "#ffffff"
    : DARK;
}

/** Contrast ratio (1 decimal) of the chosen text color on the brand color, e.g. 3.3. Used by the admin warning. */
export function onContrast(value, mode = "auto") {
  const rgb = parseHex(value);
  if (!rgb) return null;
  const lum = luminance(rgb);
  const onLum = pickOn(lum, mode) === "#ffffff" ? 1 : luminance(parseHex(DARK));
  return Math.round(contrast(lum, onLum) * 10) / 10;
}

/**
 * @returns {{color: string, on: string, link: string}}
 *   color: the brand color, on: readable text color on top of it,
 *   link: brand-colored text that reaches 4.5:1 on white (darkened when needed).
 */
export function palette(value, mode = "auto") {
  const rgb = parseHex(value) || parseHex(DEFAULT);
  const lum = luminance(rgb);
  const on = pickOn(lum, mode);

  let link = rgb;
  for (let i = 0; i < 30 && contrast(luminance(link), 1) < 4.5; i++)
    link = link.map((v) => v * 0.9);

  return { color: toHex(rgb), on, link: toHex(link) };
}
