import api from "./axios";

import { downloadEncodedFile } from "./downloadEncodedFile";

export function evidenceFiles(evidence) {
  return evidence.files?.length ? evidence.files : [{ index: 0, name: null }];
}

export async function downloadTaskEvidence(taskId, evidenceId, file) {
  const path = `task/${evidenceId}/download`;
  const { data } = await api.get(file.index === 0 ? path : `${path}/${file.index}`, {
    params: { format: "json" },
  });
  downloadEncodedFile(data, `task-${taskId}-evidence-${evidenceId}`, file.name);
}
