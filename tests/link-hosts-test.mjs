// Checks the TX-500 link allow-list (trusted() in src/frontend/markdown.jsx). Run: node tests/link-hosts-test.mjs
import { readFileSync } from "node:fs";

const src = readFileSync(new URL("../src/frontend/markdown.jsx", import.meta.url), "utf8").replace(/\r\n/g, "\n");
const code = src.match(/const SAFE_URL[^\n]*\n[\s\S]*?\n}\n/)[0].replace("export function", "function");
const trusted = new Function(`${code}; return trusted;`)();

const hosts = ["praxis.de", "booking.example.com"];
const cases = [
  ["https://praxis.de/kontakt", true],
  ["https://www.praxis.de/x", true],
  ["http://booking.example.com/a", true],
  ["https://evil-praxis.de", false],
  ["https://praxis.de.evil.com", false],
  ["https://user@evil.com@praxis.de/", true], // browsers read the host after the last @: praxis.de
  ["https://evil.com\\@praxis.de", false], // backslash counts as a slash: host is evil.com
  ["https://evil.com", false],
  ["javascript:alert(1)", false],
  ["data:text/html,x", false],
  ["mailto:a@b.de", true],
  ["tel:+49123", true],
];

let failed = 0;
for (const [url, want] of cases) {
  const got = trusted(url, hosts);
  if (got !== want) {
    failed++;
    console.log(`FAIL ${url}: got ${got}, want ${want}`);
  }
}
if (trusted("https://praxis.de", [])) {
  failed++;
  console.log("FAIL empty allow-list must block web links");
}
console.log(failed ? `${failed} failed` : `all ${cases.length + 1} checks ok`);
process.exit(failed ? 1 : 0);
