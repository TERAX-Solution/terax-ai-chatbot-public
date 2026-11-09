#!/usr/bin/env node
/**
 * Release version tool (TX-450).
 *
 *   npm run version:bump -- 1.1.0        set the version everywhere, move CHANGELOG.md "Unreleased" to 1.1.0,
 *                                        add the same entries to readme.txt (= 1.1.0 =)
 *   npm run version:check                every place holds the same version, CHANGELOG.md and readme.txt know it
 *   node scripts/version.mjs check --tag v1.1.0   same, and the git tag must match (used by the release workflow)
 *   node scripts/version.mjs notes 1.1.0 print the CHANGELOG.md section of that version (GitHub Release text)
 *
 * The Pro add-on (folder pro/) has its own version, header and changelog: add --pro to any command
 *   npm run version:bump -- 1.1.0 --pro     node scripts/version.mjs check --pro --tag pro-v1.1.0     tags look like pro-v1.1.0
 *
 * Options for bump: --date YYYY-MM-DD (default today), --allow-empty (no entries under Unreleased), --force (same or lower version).
 * Nothing is written unless every file can be updated.
 */
import { readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const SEMVER = /^(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z.-]+))?$/;

/** Every place that holds the plugin version. The first capture group is kept, the second is the version, the third is kept. */
export const PLACES = [
  { file: "terax-ai-assistant.php", label: "plugin header (Version:)", re: /^([ \t]*\*[ \t]*Version:[ \t]*)(\S+)()/m },
  { file: "plugin.php", label: "TERAX_AI_VERSION in plugin.php", re: /(define\(\s*'TERAX_AI_VERSION',\s*')([^']+)(')/ },
  { file: "package.json", label: "package.json", re: /^([ \t]*"version":[ \t]*")([^"]+)(")/m },
  { file: "package-lock.json", label: "package-lock.json (top)", re: /^([ \t]*"version":[ \t]*")([^"]+)(")/m },
  { file: "package-lock.json", label: "package-lock.json (root package)", re: /("packages":\s*\{\s*"":\s*\{[^}]*?"version":\s*")([^"]+)(")/ },
  { file: "plugin-config.json", label: "plugin-config.json", re: /("plugin_version":\s*")([^"]+)(")/ },
  { file: "readme.txt", label: "readme.txt (Stable tag)", re: /^(Stable tag:[ \t]*)(\S+)()/m },
];

/** The places of the Pro add-on (pro/terax-ai-assistant-pro.php); its changelog is pro/CHANGELOG.md and it has no readme.txt. */
export const PRO_PLACES = [
  { file: "pro/terax-ai-assistant-pro.php", label: "Pro plugin header (Version:)", re: /^([ \t]*\*[ \t]*Version:[ \t]*)(\S+)()/m },
  { file: "pro/terax-ai-assistant-pro.php", label: "TERAX_AI_PRO_VERSION", re: /(define\(\s*'TERAX_AI_PRO_VERSION',\s*')([^']+)(')/ },
];

/** Which files belong to the free plugin or to the Pro add-on. */
const target = (pro) => (pro ? { places: PRO_PLACES, changelog: "pro/CHANGELOG.md", readme: null } : { places: PLACES, changelog: "CHANGELOG.md", readme: "readme.txt" });

const read = (root, file) => readFileSync(join(root, file), "utf8");
const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
const eol = (text) => (text.includes("\r\n") ? "\r\n" : "\n");

export function parse(version) {
  const m = SEMVER.exec(version);
  return m ? { major: +m[1], minor: +m[2], patch: +m[3], pre: m[4] || "" } : null;
}

/** 1 if a is newer than b, -1 if older, 0 if equal (a release is newer than its own pre-release). */
export function compare(a, b) {
  const x = parse(a);
  const y = parse(b);
  for (const k of ["major", "minor", "patch"]) {
    if (x[k] !== y[k]) return x[k] > y[k] ? 1 : -1;
  }
  if (x.pre === y.pre) return 0;
  if (!x.pre) return 1;
  if (!y.pre) return -1;
  return x.pre > y.pre ? 1 : -1;
}

export function readVersions(root, places = PLACES) {
  return places.map((p) => {
    const m = read(root, p.file).match(p.re);
    return { ...p, value: m ? m[2] : null };
  });
}

/** Text of "## [Unreleased]" without the heading. */
export function unreleasedBody(changelog) {
  const m = changelog.match(/^## \[Unreleased\][^\n]*\n([\s\S]*?)(?=^## \[|(?![\s\S]))/m);
  return m ? m[1].trim() : null;
}

/** Text of "## [version]" without the heading, or null. */
export function section(changelog, version) {
  const m = changelog.match(new RegExp(`^## \\[${esc(version)}\\][^\\n]*\\n([\\s\\S]*?)(?=^## \\[|(?![\\s\\S]))`, "m"));
  return m ? m[1].trim() : null;
}

const bulletsOf = (body) => (body || "").split(/\r?\n/).filter((l) => /^\s*[-*]\s+\S/.test(l));

/** Entries of a changelog section as "* Added: text" lines for readme.txt. */
export function readmeEntries(body) {
  const out = [];
  let category = "";
  for (const line of (body || "").split(/\r?\n/)) {
    const h = line.match(/^###\s+(.+?)\s*$/);
    if (h) category = h[1];
    else if (/^\s*[-*]\s+\S/.test(line)) out.push(`* ${category ? `${category}: ` : ""}${line.replace(/^\s*[-*]\s+/, "").trim()}`);
  }
  return out;
}

/** Problems that would break an update or a release; an empty list means all is well. */
export function check(root, { tag, pro = false } = {}) {
  const t = target(pro);
  const problems = [];
  const versions = readVersions(root, t.places);
  for (const v of versions) {
    if (v.value === null) problems.push(`${v.label}: no version found in ${v.file}`);
    else if (!parse(v.value)) problems.push(`${v.label}: "${v.value}" is not a version like 1.2.3`);
  }
  const found = [...new Set(versions.map((v) => v.value).filter(Boolean))];
  if (found.length > 1) {
    problems.push(`The version differs between files:\n${versions.map((v) => `    ${String(v.value).padEnd(12)} ${v.label}`).join("\n")}`);
  }
  const current = found[0];
  if (found.length === 1 && current) {
    if (tag !== undefined && tag.replace(/^(?:pro-)?v/, "") !== current) problems.push(`The tag "${tag}" does not match the version in the files (${current}).`);
    if (section(read(root, t.changelog), current) === null) problems.push(`${t.changelog} has no section "## [${current}]".`);
    if (t.readme && !new RegExp(`^= ${esc(current)} =`, "m").test(read(root, t.readme))) problems.push(`${t.readme} has no changelog entry "= ${current} =".`);
  }
  return { ok: problems.length === 0, problems, versions, current };
}

/**
 * Set a new version everywhere. Returns { changed: [files], entries: [readme lines] } or throws an Error with a reason.
 * Everything is prepared first and written at the end, so a failure leaves the files untouched.
 */
export function bump(root, version, { date = new Date().toISOString().slice(0, 10), allowEmpty = false, force = false, pro = false } = {}) {
  if (!parse(version)) throw new Error(`"${version}" is not a version like 1.2.3 (or 1.2.3-rc.1).`);
  const t = target(pro);
  const state = check(root, { pro });
  if (state.versions.some((v) => v.value === null)) throw new Error(state.problems.join("\n"));
  const current = state.versions[0].value;
  if (!force && compare(version, current) <= 0) throw new Error(`${version} is not newer than the current version ${current} (use --force to override).`);

  const changelog = read(root, t.changelog);
  const body = unreleasedBody(changelog);
  if (body === null) throw new Error(`${t.changelog} has no "## [Unreleased]" section.`);
  if (!allowEmpty && bulletsOf(body).length === 0) throw new Error(`Nothing under "## [Unreleased]" in ${t.changelog}. Add the entries first (or use --allow-empty).`);

  const next = new Map();
  const get = (file) => (next.has(file) ? next.get(file) : read(root, file));
  for (const p of t.places) next.set(p.file, get(p.file).replace(p.re, (_, a, _v, c) => `${a}${version}${c}`));

  const nl = eol(changelog);
  next.set(t.changelog, changelog.replace(/^## \[Unreleased\][^\n]*\n/m, `## [Unreleased]${nl}${nl}## [${version}] - ${date}${nl}`));

  const entries = readmeEntries(body);
  if (t.readme) {
    const readme = get(t.readme);
    const rn = eol(readme);
    const block = `= ${version} =${rn}${(entries.length ? entries : ["* Maintenance release."]).join(rn)}${rn}${rn}`;
    if (!/== Changelog ==/.test(readme)) throw new Error(`${t.readme} has no "== Changelog ==" section.`);
    next.set(t.readme, readme.replace(/(== Changelog ==[ \t]*\r?\n(?:[ \t]*\r?\n)*)/, (m) => m + block));
  }

  for (const [file, text] of next) writeFileSync(join(root, file), text);
  return { changed: [...next.keys()], entries, previous: current };
}

function main(argv) {
  const root = dirname(dirname(fileURLToPath(import.meta.url)));
  const [cmd, ...rest] = argv;
  const flag = (name) => rest.includes(`--${name}`);
  const value = (name) => {
    const i = rest.indexOf(`--${name}`);
    return i >= 0 ? rest[i + 1] : undefined;
  };
  const positional = rest.filter((a, i) => !a.startsWith("--") && !(i > 0 && rest[i - 1].startsWith("--") && ["date", "tag"].includes(rest[i - 1].slice(2))));

  try {
    if (cmd === "bump") {
      if (!positional[0]) throw new Error("Usage: npm run version:bump -- <version>   e.g. 1.1.0");
      const r = bump(root, positional[0], { date: value("date"), allowEmpty: flag("allow-empty"), force: flag("force"), pro: flag("pro") });
      console.log(`Version ${r.previous} -> ${positional[0]} in: ${r.changed.join(", ")}`);
      console.log("readme.txt changelog gets:\n  " + (r.entries.join("\n  ") || "(none)"));
      console.log(`Next: review \`git diff\`, commit, merge, then tag ${flag("pro") ? "pro-v" : "v"}${positional[0]} (see docs/RELEASE.md).`);
    } else if (cmd === "check") {
      const r = check(root, { tag: value("tag"), pro: flag("pro") });
      if (!r.ok) {
        console.error("Version check failed:\n- " + r.problems.join("\n- "));
        process.exit(1);
      }
      console.log(`Version ${r.current} is consistent in ${r.versions.length} places and the changelog${value("tag") ? `, tag ${value("tag")} matches` : ""}.`);
    } else if (cmd === "notes") {
      const file = target(flag("pro")).changelog;
      const body = section(read(root, file), positional[0] || "");
      if (body === null) throw new Error(`${file} has no section "## [${positional[0]}]".`);
      console.log(body);
    } else {
      console.log("Usage: version.mjs bump <version> | check [--tag vX.Y.Z] | notes <version>   (add --pro for the Pro add-on)");
      process.exit(cmd ? 1 : 0);
    }
  } catch (e) {
    console.error(e.message);
    process.exit(1);
  }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) main(process.argv.slice(2));
