import api from "../axios";

export const getApplicants = async (page = 1, limit = 2) => {
  const response = await api.get("/application", {
    params: {
      page,
      limit,
    },
  });
  // console.log("getApplicants response:", response.data);

  return response.data;
};

export const getApplicant = async (id) => {
  const response = await api.get(`/application/${id}`);

  return response.data;
};

export const rejectApplicant = async (id) => {
  const response = await api.get(`/application/acceptOrRejectApplicant/${id}/rejected`);
  // console.log("rejected data",response.data)
  return response.data;
};

export const acceptApplicant = async (id) => {
  const response = await api.get(`/application/acceptOrRejectApplicant/${id}/accepted`);
  // console.log("rejected data",response.data)
  return response.data;
};

export const searchApplicants = async ({ filters, page=1, limit=10 }) => {

  const params = {
  page,
  limit,
};
  if (filters.search) params.search = filters.search;
  if (filters.city) params.city = filters.city;
  if (filters.major) params.field_of_study = filters.major;
  if (filters.year) params.year_of_study = filters.year;
  if (filters.gpa) params.gpa = filters.gpa;
  if (filters.status) params.status = filters.status;

  const response = await api.get("/application", {
   params
  });
  console.log("Search filters:", params);
  // console.log("page:", page);
  // console.log("limit:", limit);
  console.log(response.data);
  return response.data;
};

export const assignApplicant = async (applicationID,supervisorID) => {
  const response = await api.get(`/application/${applicationID}/assign/${supervisorID}`);
  console.log("assign data",response.data)
  return response.data;
};

