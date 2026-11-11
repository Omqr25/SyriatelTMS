import api from "../axios";

export const getSupervisorsApi = async () => {
  const response = await api.get("/trainer");
  // console.log(response.data)

  return response.data;
};

export const createSupervisorsApi = async (formData) => {
  const response = await api.post("/trainer",formData);
  console.log(response.data)

  return response.data;
};



