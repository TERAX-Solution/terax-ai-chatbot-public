/**
 * Full URL of a plugin REST route. With pretty permalinks the base is ".../wp-json/terax-ai/v1" and a query string
 * can simply follow the path. With plain permalinks the base is ".../?rest_route=/terax-ai/v1", so a second "?"
 * would end up inside the route name and WordPress would answer 404: the query has to be joined with "&".
 *
 * @param {string} base apiUrl from the page config.
 * @param {string} path e.g. "/conversations?page=2".
 * @returns {string}
 */
export function apiRoute(base, path) {
  const root = String(base).replace(/\/$/, "");
  return root.includes("?") ? root + path.replace("?", "&") : root + path;
}
