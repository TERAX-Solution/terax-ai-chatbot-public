// Storage helpers that never throw (private mode, blocked cookies, etc.).
export const store = {
  get(key, fallback = null) {
    try {
      const v = window.sessionStorage.getItem(key);
      return v ? JSON.parse(v) : fallback;
    } catch (e) {
      return fallback;
    }
  },
  set(key, value) {
    try {
      window.sessionStorage.setItem(key, JSON.stringify(value));
    } catch (e) {}
  },
  remove(key) {
    try {
      window.sessionStorage.removeItem(key);
    } catch (e) {}
  },
  getLocal(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  },
  setLocal(key, value) {
    try {
      window.localStorage.setItem(key, value);
    } catch (e) {}
  },
};

export function uuid() {
  if (window.crypto && window.crypto.randomUUID)
    return window.crypto.randomUUID();
  return (
    "s-" +
    Date.now().toString(36) +
    "-" +
    Math.random().toString(36).slice(2, 12)
  );
}
