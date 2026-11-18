import api from "./axios";

const extensions = {
  "application/pdf": "pdf",
  "image/jpeg": "jpg",
  "image/png": "png",
  "image/webp": "webp",
  "image/gif": "gif",
};

export function evidenceFiles(evidence) {
  return evidence.files?.length ? evidence.files : [{ index: 0, name: null }];
}

export async function downloadTaskEvidence(taskId, evidenceId, file) {
  const path = `task/${evidenceId}/download`;
  const { data } = await api.get(file.index === 0 ? path : `${path}/${file.index}`, {
    params: { format: "json" },
  });
  if (typeof data?.content !== "string" || !data.content) {
    throw new Error("The evidence response did not contain a file.");
  }

  const type = data.mime_type || "application/octet-stream";
  const decoded = atob(data.content);
  const bytes = new Uint8Array(decoded.length);
  for (let i = 0; i < decoded.length; i++) bytes[i] = decoded.charCodeAt(i);
  const extension = extensions[type];
  const fallback = `task-${taskId}-evidence-${evidenceId}${extension ? `.${extension}` : ""}`;
  const url = URL.createObjectURL(new Blob([bytes], { type }));
  const link = document.createElement("a");
  link.href = url;
  link.download = file.name || fallback;
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
