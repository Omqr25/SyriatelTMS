import api from "../axios";

export const getProgramsApi = async (signal) =>
  (await api.get("/training_program/management/list", { signal })).data.data;

export const createProgramsApi = async (fields) =>
  (await api.post("/training_program", fields)).data.data;

export const updateProgramApi = async (id, fields) =>
  (await api.put(`/training_program/${id}`, fields)).data.data;

export const deleteProgramApi = (id, revision) =>
  api.delete(`/training_program/${id}`, { data: { expected_revision: revision } });

export const setProgramArchivedApi = async (id, archive, revision) =>
  (await api.post(`/training_program/${id}/${archive ? "archive" : "restore"}`, { expected_revision: revision })).data.data;

const formPath = (id) => `/training_program/${id}/application-form`;

export const getApplicationForm = async (id, signal) =>
  (await api.get(formPath(id), { signal })).data.data;

export const saveApplicationForm = async (id, fields, locale) =>
  (await api.put(formPath(id), fields, { headers: { "Accept-Language": locale } })).data.data;

export const publishApplicationForm = async (id, fields, locale) =>
  (await api.post(`${formPath(id)}/publish`, fields, { headers: { "Accept-Language": locale } })).data.data;
