
import api from "../axios";

export const createInterviewApi = async (applicationId,data) => {

  const response = await api.post(`application/${applicationId}/interview`,data);

  console.log(response.data)
  return response.data;
};


export const getAllInterviewApi = async () => {

  const response = await api.get("application/interviewApplications");

  console.log(response.data)
  return response.data;
};






