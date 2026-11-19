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
