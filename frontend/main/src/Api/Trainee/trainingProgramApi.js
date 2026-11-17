import api from "../axios";

export const getTrainingProgram = async (config = {}) => {
  const response = await api.get("myProgram", config);
  return response.data;
};

export const startTask = async (taskId) => {
  const response = await api.post(`task/tasks/${taskId}/start`);
  return response.data;
};

export const submitEvidence = async (taskId, evidence) => {
  const formData = new FormData();
  formData.append("evidence", evidence);
  const response = await api.post(`task/tasks/${taskId}/evidence`, formData);
  return response.data;
};
