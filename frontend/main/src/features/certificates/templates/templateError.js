export async function templateError(error, fallback) {
  let data = error.response?.data;
  if (data instanceof Blob) {
    try { data = JSON.parse(await data.text()); } catch { data = null; }
  }
  return data?.errors ? Object.values(data.errors).flat().join(' ') : data?.message || fallback;
}
