import api from "../axios";

export const getDepartmentsApi = async () => {
  const response = await api.get("/department");
  // console.log(response.data)
  return response.data;
};


export const createDepartmentsApi = async (formData) => {
  const response = await api.post("/department",formData);

  // console.log(response.data)

  return response.data;
};



