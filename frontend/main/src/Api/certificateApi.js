import api from './axios';

export const getCertificates = async () => (await api.get('certificates')).data.data;
export const getCertificateCandidates = async () => (await api.get('certificates/candidates')).data.data;
export const issueCertificate = async (traineeId, file) => {
  const body = new FormData();
  body.append('file', file);
  return (await api.post(`certificates/trainees/${traineeId}`, body)).data.data;
};
export const getCertificateFile = async (id, download = false) =>
  (await api.get(`certificates/${id}/${download ? 'download' : 'preview'}`, { responseType: 'blob' })).data;

const templates = 'certificates/templates';
const data = response => response.data.data;
const templateConfig = options => ({
  ...options, headers: { 'Accept-Language': localStorage.getItem('syriatel-tms:lang') === 'en' ? 'en' : 'ar' },
});

export const listCertificateTemplates = async (params, signal) =>
  (await api.get(templates, templateConfig({ params, signal }))).data;
export const getCertificateTemplate = async id => data(await api.get(`${templates}/${id}`));
export const getCertificateAssets = async () => data(await api.get(`${templates}/assets`));
export const getCertificateFont = async (family, style) =>
  (await api.get(`${templates}/fonts/${family}/${style}`, { responseType: 'arraybuffer' })).data;
export const uploadCertificateBackground = async file => {
  const body = new FormData();
  body.append('file', file);
  return data(await api.post(`${templates}/backgrounds`, body, templateConfig()));
};
export const getCertificateBackground = async (background, signal) => (
  background.token
    ? await api.post(`${templates}/draft-background`, { background_token: background.token }, templateConfig({ responseType: 'blob', signal }))
    : await api.get(`${templates}/versions/${background.version}/background`, templateConfig({ responseType: 'blob', signal }))
).data;
export const saveCertificateTemplate = async (id, payload) => data(id
  ? await api.put(`${templates}/${id}`, payload, templateConfig())
  : await api.post(templates, payload, templateConfig()));
export const duplicateCertificateTemplate = async (id, name) =>
  data(await api.post(`${templates}/${id}/duplicate`, { name }, templateConfig()));
export const archiveCertificateTemplate = async id => api.post(`${templates}/${id}/archive`);
export const getCertificateProof = async payload =>
  (await api.post(`${templates}/proof`, payload, templateConfig({ responseType: 'blob' }))).data;
