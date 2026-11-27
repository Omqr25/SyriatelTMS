import api from './axios';

export const getCertificates = async () => (await api.get('certificates')).data.data;
export const getCertificateFile = async (id, download = false) => {
  const { data: blob, headers } = await api.get(`certificates/${id}/${download ? 'download' : 'preview'}`, { responseType: 'blob' });
  const disposition = headers['content-disposition'] ?? '';
  const encoded = disposition.match(/(?:^|;)\s*filename\*=UTF-8''([^;]+)/i)?.[1];
  if (encoded) {
    try {
      return { blob, filename: decodeURIComponent(encoded) };
    } catch {
      // A malformed extended name can fall back to the standard filename.
    }
  }
  const plain = disposition.match(/(?:^|;)\s*filename=(?:"([^"]+)"|([^;]+))/i);
  return { blob, filename: (plain?.[1] ?? plain?.[2])?.trim() };
};

const templates = 'certificates/templates';
const data = response => response.data.data;
const localized = options => ({
  ...options, headers: { 'Accept-Language': localStorage.getItem('syriatel-tms:lang') === 'en' ? 'en' : 'ar' },
});

export const listCertificateTemplates = async (params, signal) =>
  (await api.get(templates, localized({ params, signal }))).data;
export const getCertificateTemplate = async id => data(await api.get(`${templates}/${id}`));
export const getCertificateAssets = async () => data(await api.get(`${templates}/assets`));
export const getCertificateFont = async (family, style) =>
  (await api.get(`${templates}/fonts/${family}/${style}`, { responseType: 'arraybuffer' })).data;
export const uploadCertificateBackground = async file => {
  const body = new FormData();
  body.append('file', file);
  return data(await api.post(`${templates}/backgrounds`, body, localized()));
};
export const getCertificateBackground = async (background, signal) => (
  background.token
    ? await api.post(`${templates}/draft-background`, { background_token: background.token }, localized({ responseType: 'blob', signal }))
    : await api.get(`${templates}/versions/${background.version}/background`, localized({ responseType: 'blob', signal }))
).data;
export const saveCertificateTemplate = async (id, payload) => data(id
  ? await api.put(`${templates}/${id}`, payload, localized())
  : await api.post(templates, payload, localized()));
export const duplicateCertificateTemplate = async (id, name) =>
  data(await api.post(`${templates}/${id}/duplicate`, { name }, localized()));
export const archiveCertificateTemplate = async id => api.post(`${templates}/${id}/archive`);
export const getCertificateProof = async payload =>
  (await api.post(`${templates}/proof`, payload, localized({ responseType: 'blob' }))).data;

const issuance = 'certificates/issuance';
export const getIssuanceOptions = async signal => data(await api.get(`${issuance}/options`, localized({ signal })));
export const getIssuanceRoster = async (program, params, signal) =>
  data(await api.get(`${issuance}/programs/${program}/roster`, localized({ params, signal })));
export const postIssuance = async (program, action, payload = {}) =>
  data(await api.post(`${issuance}/programs/${program}/${action}`, payload, localized()));
export const updateIssuanceTrainee = async (program, trainee, action, payload) =>
  api.put(`${issuance}/programs/${program}/trainees/${trainee}/${action}`, payload, localized());
export const getCertificateName = async signal => data(await api.get('certificates/name', localized({ signal })));
export const saveCertificateName = async name => data(await api.put('certificates/name', { name }, localized()));

const archive = 'certificates/archive/programs';
export const getCertificateArchive = async signal => data(await api.get(archive, localized({ signal })));
export const getArchiveCertificates = async (program, params, signal) =>
  data(await api.get(`${archive}/${program}/certificates`, localized({ params, signal })));
export const getCertificatePackages = async (program, signal) =>
  data(await api.get(`${archive}/${program}/packages`, localized({ signal })));
export const requestCertificatePackage = async (program, payload) =>
  data(await api.post(`${archive}/${program}/packages`, payload, localized()));
export const retryCertificatePackage = async (program, id) =>
  data(await api.post(`${archive}/${program}/packages/${id}/retry`, {}, localized()));
export const downloadCertificatePackage = async (program, id) =>
  (await api.get(`${archive}/${program}/packages/${id}/download`, localized({ responseType: 'blob', timeout: 120000 }))).data;
