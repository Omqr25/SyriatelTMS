

import api from "../axios";

export const getMyAssignApi = async () => {

  const response = await api.get("application/trainer/assigned");

  console.log(response.data)
  return response.data;
};

export const TrainerAcceptOrRejectApplicationApi = async (applicationId,type) => {

  const response = await api.get(`application/trainer/${applicationId}/${type}`);
  console.log(response.data)
  return response.data;
};