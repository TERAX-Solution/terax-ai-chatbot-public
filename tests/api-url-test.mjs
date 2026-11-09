// Standalone test: how the admin builds plugin REST URLs (plain vs pretty permalinks).
//   node tests/api-url-test.mjs
import { apiRoute } from "../src/admin/lib/apiUrl.js";

let fail = 0;
function eq(name, got, want) {
  const ok = got === want;
  console.log(`${ok ? "ok  " : "FAIL"} ${name}${ok ? "" : `\n     got  ${got}\n     want ${want}`}`);
  if (!ok) fail++;
}

const pretty = "https://site.test/wp-json/terax-ai/v1/";
const plain = "http://localhost:8889/?rest_route=/terax-ai/v1/";

eq("pretty, no query", apiRoute(pretty, "/dashboard"), "https://site.test/wp-json/terax-ai/v1/dashboard");
eq("pretty, query", apiRoute(pretty, "/conversations?page=2&search=a"), "https://site.test/wp-json/terax-ai/v1/conversations?page=2&search=a");
eq("plain, no query", apiRoute(plain, "/dashboard"), "http://localhost:8889/?rest_route=/terax-ai/v1/dashboard");
eq("plain, query joined with &", apiRoute(plain, "/conversations?page=2&search=a"), "http://localhost:8889/?rest_route=/terax-ai/v1/conversations&page=2&search=a");
eq("plain, export link", apiRoute(plain, "/conversations/export?_wpnonce=x&delimiter=%3B"), "http://localhost:8889/?rest_route=/terax-ai/v1/conversations/export&_wpnonce=x&delimiter=%3B");
eq("base without trailing slash", apiRoute("https://site.test/wp-json/terax-ai/v1", "/requests"), "https://site.test/wp-json/terax-ai/v1/requests");

console.log(fail ? `\n${fail} failed` : "\nall passed");
process.exit(fail ? 1 : 0);
