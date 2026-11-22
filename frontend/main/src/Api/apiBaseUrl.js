export function apiBaseUrl(value) {
  const configured = value?.trim().replace(/\/+$/, "");
  if (!configured) return "/api/v1";

  const origin = "http://localhost";
  const isRelative = configured.startsWith("/") && !configured.startsWith("//");
  const url = parseUrl(configured, isRelative ? origin : undefined);
  if (!url || !/^https?:$/.test(url.protocol) || url.username || url.password || url.search || url.hash
    || (isRelative && url.origin !== origin) || !url.pathname.endsWith("/api/v1")) {
    throw new Error("VITE_API_URL must point to an HTTP(S) or same-origin /api/v1 endpoint.");
  }
  return isRelative ? url.pathname : url.href;
}

function parseUrl(url, base) {
  try {
    return new URL(url, base);
  } catch {
    return null;
  }
}
