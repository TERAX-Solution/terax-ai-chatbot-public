export async function post(endpoint, path, body) {
  const res = await fetch(endpoint.replace(/\/$/, "") + path, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "omit", // Public endpoint: no cookies → works with full-page caching.
    body: JSON.stringify(body),
  });
  let data = null;
  try {
    data = await res.json();
  } catch (e) {}
  if (!res.ok) {
    const err = new Error((data && data.message) || `HTTP ${res.status}`);
    err.status = res.status;
    err.serverMessage = data && data.message;
    throw err;
  }
  return data;
}
