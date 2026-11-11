// فك ترميز محتوى JWT فقط، دون التحقق من التوقيع
export function decodeJwt(token) {
  try {
    const payload = token.split(".")[1];

    const base64 = payload
      .replace(/-/g, "+")
      .replace(/_/g, "/");

    const decoded = atob(base64);

    const bytes = Uint8Array.from(
      decoded,
      (char) => char.charCodeAt(0)
    );

    return JSON.parse(
      new TextDecoder().decode(bytes)
    );
  } catch {
    return null;
  }
}

// التحقق من انتهاء صلاحية الرمز
export function isTokenExpired(token) {
  const payload = decodeJwt(token);

  if (!payload?.exp) return true;

  return payload.exp <= Date.now() / 1000;
}