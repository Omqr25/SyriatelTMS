import api from "../axios";

export const ENGLISH_SCORES_CHANGED = "syriatel-tms:english-score-import";

const path = "/english-score-imports";

export async function previewEnglishScores(programId, file, locale) {
  const body = new FormData();
  body.append("training_program_id", programId);
  body.append("file", file);
  return (await api.post(`${path}/preview`, body, { headers: { "Accept-Language": locale } })).data.data;
}

export async function confirmEnglishScores(id, replaceExisting, locale) {
  return (await api.post(`${path}/${id}/confirm`, { replace_existing: replaceExisting }, {
    headers: { "Accept-Language": locale },
  })).data.data;
}

export async function getEnglishScoreHistory(page, signal) {
  return (await api.get(path, { params: { page }, signal })).data;
}

export async function getEnglishScoreRows(id, page, exceptions, signal) {
  return (await api.get(`${path}/${id}/rows`, { params: { page, exceptions: exceptions ? 1 : 0 }, signal })).data;
}

async function downloadWorkbook(endpoint, filename, locale) {
  const { data } = await api.get(endpoint, { responseType: "blob", headers: { "Accept-Language": locale } });
  const url = URL.createObjectURL(data);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.click();
  // Let the browser consume the URL before revoking it.
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function downloadEnglishScoreTemplate() {
  return downloadWorkbook(`${path}/template`, "english-score-template.xlsx");
}

export function downloadEnglishScoreExceptions(id, locale) {
  return downloadWorkbook(`${path}/${id}/exceptions`, `english-score-exceptions-${id}.xlsx`, locale);
}
