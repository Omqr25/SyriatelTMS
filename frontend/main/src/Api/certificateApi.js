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
