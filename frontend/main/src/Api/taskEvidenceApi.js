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
    responseType: "blob",
  });
  const extension = extensions[data.type];
  const fallback = `task-${taskId}-evidence-${evidenceId}${extension ? `.${extension}` : ""}`;
  const url = URL.createObjectURL(data);
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
