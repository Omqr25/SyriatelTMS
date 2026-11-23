const extensions = {
  "application/pdf": "pdf",
  "image/jpeg": "jpg",
  "image/png": "png",
  "image/webp": "webp",
  "image/gif": "gif",
};

export function downloadEncodedFile(data, fallbackName, filename) {
  if (typeof data?.content !== "string" || !data.content) {
    throw new Error("The response did not contain a file.");
  }

  const type = data.mime_type || "application/octet-stream";
  const decoded = atob(data.content);
  const bytes = new Uint8Array(decoded.length);
  for (let i = 0; i < decoded.length; i++) bytes[i] = decoded.charCodeAt(i);
  const extension = extensions[type];
  const fallback = `${fallbackName}${extension ? `.${extension}` : ""}`;
  const url = URL.createObjectURL(new Blob([bytes], { type }));
  const link = document.createElement("a");
  link.href = url;
  link.download = filename || fallback;
  link.hidden = true;
  document.body.appendChild(link);
  try {
    link.click();
  } finally {
    // Keep cleanup outside the transfer result and give the browser time to read the blob.
    window.setTimeout(() => {
      link.remove();
      URL.revokeObjectURL(url);
    }, 60_000);
  }
}
