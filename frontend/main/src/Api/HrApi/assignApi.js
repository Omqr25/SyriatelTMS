
import api from "../axios";

export const getAllAssignApi = async () => {

  const response = await api.get("application/assignedAndQualifiedApplications");

  // console.log(response.data)
  return response.data;
};