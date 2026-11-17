import api from "../axios";
export const getAllTraineesApi = async (params = {}) => {
  try {
    const response = await api.get("/trainee", { params });
    console.log("API Response Data:", response.data); 
    return response.data;
  } catch (error) {
    console.error("API Error:", error);
    throw error;
  }
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
