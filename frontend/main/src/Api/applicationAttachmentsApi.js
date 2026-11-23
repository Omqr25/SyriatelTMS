import api from "./axios";
import { downloadEncodedFile } from "./downloadEncodedFile";

export async function downloadApplicationAttachment(applicationId, type) {
  const { data } = await api.get(`application/${applicationId}/attachments/${type}`, {
    params: { format: "json" },
  });
  downloadEncodedFile(data, type, data?.filename);
}
