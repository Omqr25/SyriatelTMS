import api, { ACCESS_TOKEN_KEY } from "../axios";

const exportsPath = (program) => `/training_program/${program}/applicant-exports`;

export async function listApplicantExports(program, signal) {
  return (await api.get(exportsPath(program), { signal })).data.data;
}

export async function requestApplicantExport(program, payload) {
  return (await api.post(exportsPath(program), payload)).data.id;
}

export async function changeApplicantExport(program, snapshot, action) {
  return (await api.post(`${exportsPath(program)}/${snapshot}/${action}`)).data;
}

export async function downloadExportManifest(program, snapshot) {
  const { data } = await api.get(`${exportsPath(program)}/${snapshot}/manifest`, { responseType: "blob" });
  const url = URL.createObjectURL(data);
  const link = document.createElement("a");
  link.href = url;
  link.download = `snapshot-${snapshot}-manifest.json`;
  link.click();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function downloadApplicantArtifact(snapshot, artifact) {
  const token = localStorage.getItem(ACCESS_TOKEN_KEY);
  if (!token) {
    window.dispatchEvent(new Event("auth:unauthorized"));
    return;
  }

  // A top-level request can set the artifact-scoped cookie even across frontend/API origins.
  // The browser consumes the 303 and streams the file using its download manager.
  const form = document.createElement("form");
  form.hidden = true;
  form.method = "POST";
  form.target = "_blank";
  form.rel = "noopener";
  form.action = api.getUri({ url: `/applicant-export-downloads/${snapshot}/${artifact}/ticket` });
  const credential = document.createElement("input");
  credential.type = "hidden";
  credential.name = "access_token";
  credential.value = token;
  form.append(credential);
  document.body.append(form);
  form.submit();
  form.remove();
}
