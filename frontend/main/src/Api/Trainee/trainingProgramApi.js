import api from "../axios";

export const getTrainingProgram = async (config = {}) => {
  const response = await api.get("myProgram", config);
  return response.data;
};

export const startTask = async (taskId) => {
  const response = await api.post(`task/tasks/${taskId}/start`);
  return response.data;
};

export const submitEvidence = async (taskId, files) => {
  const formData = new FormData();
  files.forEach(file => formData.append("evidences[]", file));
  const response = await api.post(`task/tasks/${taskId}/evidence`, formData);
  return response.data;
};
