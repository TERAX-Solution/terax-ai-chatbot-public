/**
 * Standalone test for the release version tool (TX-450): node tests/version-test.mjs
 * Works on copies of small fixture files in a temp folder; the real repo is never touched.
 */
import { cpSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import { bump, check, compare, readmeEntries, section, unreleasedBody } from "../scripts/version.mjs";

let fails = 0;
const ok = (label, cond) => {
  console.log(`${cond ? "PASS" : "FAIL"} ${label}`);
  if (!cond) fails++;
};
const throwsWith = (fn, text) => {
  try {
    fn();
  } catch (e) {
    return e.message.includes(text);
  }
  return false;
};

const FILES = {
  "terax-ai-assistant.php": "<?php\n/**\n * Plugin Name: Demo\n * Version: 1.0.0\n * License: GPLv2 or later\n */\n",
  "plugin.php": "<?php\nif ( ! defined( 'X' ) ) {\n\tdefine( 'TERAX_AI_VERSION', '1.0.0' );\n\tdefine( 'TERAX_AI_DB_VERSION', '1.3.0' );\n}\n",
  "package.json": '{\n  "name": "demo",\n  "version": "1.0.0",\n  "scripts": {}\n}\n',
  "package-lock.json": '{\n  "name": "demo",\n  "version": "1.0.0",\n  "lockfileVersion": 3,\n  "packages": {\n    "": {\n      "name": "demo",\n      "version": "1.0.0",\n      "dependencies": { "a": "^1.0.0" }\n    },\n    "node_modules/a": {\n      "version": "5.3.0"\n    }\n  }\n}\n',
  "plugin-config.json": '{\n  "plugin_name": "Demo",\n  "plugin_version": "1.0.0"\n}\n',
  "readme.txt": "=== Demo ===\nStable tag: 1.0.0\nLicense: GPLv2 or later\n\n== Description ==\n\nText\n\n== Changelog ==\n\n= 1.0.0 =\n* Initial release.\n",
  "pro/terax-ai-assistant-pro.php": "<?php\n/**\n * Plugin Name: Demo Pro\n * Version: 1.0.0\n */\ndefine( 'TERAX_AI_PRO_VERSION', '1.0.0' );\n",
  "pro/CHANGELOG.md": "# Changelog\n\n## [Unreleased]\n\n### Added\n- Pro entry\n\n## [1.0.0] - 2026-11-09\n\n### Added\n- Initial\n",
  "CHANGELOG.md": "# Changelog\n\n## [Unreleased]\n\n### Added\n- New insights page\n- Call-back form\n\n### Fixed\n- Blank pages with plain permalinks\n\n## [1.0.0] - 2026-10-09\n\n### Added\n- Initial release\n",
};

function fixture(overrides = {}, crlf = false) {
  const dir = mkdtempSync(join(tmpdir(), "terax-version-"));
  for (const [name, text] of Object.entries({ ...FILES, ...overrides })) {
    mkdirSync(join(dir, name, ".."), { recursive: true });
    writeFileSync(join(dir, name), crlf ? text.replace(/\n/g, "\r\n") : text);
  }
  return dir;
}
const text = (dir, f) => readFileSync(join(dir, f), "utf8");

// ---- compare ----
ok("compare: 1.2.0 is newer than 1.1.9", compare("1.2.0", "1.1.9") === 1);
ok("compare: 1.0.10 is newer than 1.0.9 (numbers, not text)", compare("1.0.10", "1.0.9") === 1);
ok("compare: a release is newer than its pre-release", compare("1.1.0", "1.1.0-rc.1") === 1 && compare("1.1.0-rc.1", "1.1.0") === -1);
ok("compare: equal", compare("2.0.0", "2.0.0") === 0);

// ---- changelog helpers ----
const log = FILES["CHANGELOG.md"];
ok("unreleasedBody: the entries under Unreleased only", unreleasedBody(log).includes("New insights page") && !unreleasedBody(log).includes("Initial release"));
ok("section: one version only", section(log, "1.0.0") === "### Added\n- Initial release" && section(log, "9.9.9") === null);
ok("readmeEntries: category prefix, one line per entry", readmeEntries(unreleasedBody(log)).join("|") === "* Added: New insights page|* Added: Call-back form|* Fixed: Blank pages with plain permalinks");

// ---- check ----
let dir = fixture();
let r = check(dir);
ok("check: consistent files pass (7 places)", r.ok && r.versions.length === 7 && r.current === "1.0.0");
ok("check: tag v1.0.0 matches", check(dir, { tag: "v1.0.0" }).ok);
r = check(dir, { tag: "v1.0.1" });
ok("check: wrong tag is reported", !r.ok && r.problems[0].includes("does not match"));
rmSync(dir, { recursive: true });

dir = fixture({ "plugin.php": FILES["plugin.php"].replace("'1.0.0'", "'1.0.1'") });
r = check(dir);
ok("check: one forgotten place is found and named", !r.ok && r.problems[0].includes("differs") && r.problems[0].includes("TERAX_AI_VERSION"));
rmSync(dir, { recursive: true });

dir = fixture({ "readme.txt": FILES["readme.txt"].replace("= 1.0.0 =\n* Initial release.\n", "") });
ok("check: missing readme changelog entry is reported", check(dir).problems.some((p) => p.includes("readme.txt has no changelog entry")));
rmSync(dir, { recursive: true });

dir = fixture({ "CHANGELOG.md": "# Changelog\n\n## [Unreleased]\n" });
ok("check: missing CHANGELOG section is reported", check(dir).problems.some((p) => p.includes("CHANGELOG.md has no section")));
rmSync(dir, { recursive: true });

// ---- bump ----
dir = fixture();
const res = bump(dir, "1.1.0", { date: "2026-11-20" });
ok("bump: all 7 places carry the new version and check passes", check(dir, { tag: "v1.1.0" }).ok && res.previous === "1.0.0");
ok("bump: files outside the version are unchanged", text(dir, "plugin.php").includes("TERAX_AI_DB_VERSION', '1.3.0'") && text(dir, "package-lock.json").includes('"version": "5.3.0"') && text(dir, "package-lock.json").includes('"a": "^1.0.0"'));
ok("bump: CHANGELOG Unreleased is emptied into the new version with the date", /## \[Unreleased\]\n\n## \[1\.1\.0\] - 2026-11-20\n\n### Added\n- New insights page/.test(text(dir, "CHANGELOG.md")) && text(dir, "CHANGELOG.md").includes("## [1.0.0] - 2026-10-09"));
ok("bump: readme changelog gets the entries above the old ones", /== Changelog ==\n\n= 1\.1\.0 =\n\* Added: New insights page\n\* Added: Call-back form\n\* Fixed: Blank pages with plain permalinks\n\n= 1\.0\.0 =/.test(text(dir, "readme.txt")));
ok("bump: the GitHub release notes are the new section", section(text(dir, "CHANGELOG.md"), "1.1.0").includes("Call-back form"));
ok("bump: refuses the same version", throwsWith(() => bump(dir, "1.1.0"), "not newer"));
ok("bump: refuses a lower version", throwsWith(() => bump(dir, "1.0.5"), "not newer"));
ok("bump: refuses an empty Unreleased", throwsWith(() => bump(dir, "1.2.0"), "Nothing under"));
ok("bump: --allow-empty writes a maintenance entry", (() => { bump(dir, "1.1.1", { allowEmpty: true }); return text(dir, "readme.txt").includes("= 1.1.1 =\n* Maintenance release."); })());
ok("bump: not a version", throwsWith(() => bump(dir, "v1.2"), "is not a version"));
rmSync(dir, { recursive: true });

// A failure must not leave half-updated files.
dir = fixture({ "readme.txt": FILES["readme.txt"].replace("== Changelog ==", "== Log ==") });
ok("bump: a file it cannot update stops everything before anything is written", throwsWith(() => bump(dir, "1.1.0"), "no \"== Changelog ==\"") && text(dir, "package.json").includes('"version": "1.0.0"') && text(dir, "CHANGELOG.md") === FILES["CHANGELOG.md"]);
rmSync(dir, { recursive: true });

// Pre-release.
dir = fixture();
bump(dir, "1.1.0-rc.1", { date: "2026-11-20" });
ok("bump: pre-release versions work and a release after it is newer", check(dir, { tag: "v1.1.0-rc.1" }).ok && compare("1.1.0", "1.1.0-rc.1") === 1);
rmSync(dir, { recursive: true });

// Windows line endings (some repo files are CRLF) stay CRLF.
dir = fixture({}, true);
bump(dir, "1.1.0", { date: "2026-11-20" });
const crlfOk = ["readme.txt", "CHANGELOG.md", "package.json", "plugin.php"].every((f) => !/[^\r]\n/.test(text(dir, f)));
ok("bump: CRLF files stay CRLF and check passes", crlfOk && check(dir).ok);
rmSync(dir, { recursive: true });

// The Pro add-on has its own version, changelog and tag (pro-v1.2.3); the free plugin's files stay untouched.
dir = fixture();
r = check(dir, { pro: true, tag: "pro-v1.0.0" });
ok("pro check: 2 places, tag pro-v1.0.0 matches", r.ok && r.versions.length === 2 && r.current === "1.0.0");
ok("pro check: a plain v tag also names the version, a wrong one fails", check(dir, { pro: true, tag: "pro-v2.0.0" }).ok === false);
bump(dir, "1.1.0", { pro: true, date: "2026-11-20" });
ok("pro bump: header, constant and pro/CHANGELOG.md change", text(dir, "pro/terax-ai-assistant-pro.php").includes("Version: 1.1.0") && text(dir, "pro/terax-ai-assistant-pro.php").includes("'TERAX_AI_PRO_VERSION', '1.1.0'") && text(dir, "pro/CHANGELOG.md").includes("## [1.1.0] - 2026-11-20"));
ok("pro bump: the free plugin's version, changelog and readme are not touched", text(dir, "package.json").includes('"version": "1.0.0"') && text(dir, "CHANGELOG.md") === FILES["CHANGELOG.md"] && text(dir, "readme.txt") === FILES["readme.txt"]);
ok("pro bump: the new version passes the pro check and the free check still passes", check(dir, { pro: true, tag: "pro-v1.1.0" }).ok && check(dir).ok);
rmSync(dir, { recursive: true });

// The real repo must pass its own check.
const repoRoot = new URL("..", import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, "$1");
ok("the repository itself is consistent", check(repoRoot).ok);
ok("the Pro add-on in the repository is consistent", check(repoRoot, { pro: true }).ok);

console.log(fails ? `\n${fails} FAILED` : "\nAll good");
process.exit(fails ? 1 : 0);
