import api from "../axios";

export const getAllTraineesApi = async () => {
  const response = await api.get("/trainee");
  console.log(response.data);
  return response.data;
};

export const activeTraineeAccountApi = async (id) => {
  const response = await api.get(`application/activateTrainee/${id}`);
  console.log(response.data);
  return response.data;
};

export const fireTraineeApi = async (id, reason) => {
  const response = await api.post(`trainee/fire/${id}`, { reason });
  console.log(response.data);
  return response.data;
};
