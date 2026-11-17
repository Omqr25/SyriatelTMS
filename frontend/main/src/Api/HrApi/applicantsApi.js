import api from "../axios";

export const getApplicants = async (page = 1, limit = 2) => {
  const response = await api.get("/application", {
    params: {
      page,
      limit,
    },
  });

  return response.data;
};

export const getApplicant = async (id, signal) => {
  const response = await api.get(`/application/${id}`, { signal });

  return response.data;
};

export const rejectApplicant = async (id) => {
  const response = await api.get(
    `/application/acceptOrRejectApplicant/${id}/rejected`,
  );
  return response.data;
};

export const acceptApplicant = async (id) => {
  const response = await api.get(
    `/application/acceptOrRejectApplicant/${id}/accepted`,
  );
  return response.data;
};

export const searchApplicants = async ({
  filters,
  page = 1,
  limit = 10,
  signal,
}) => {
  const params = {
    page,
    limit,
  };
  if (filters.search) params.search = filters.search;
  if (filters.city) params.city = filters.city;
  if (filters.major) params.field_of_study = filters.major;
  if (filters.year) params.year_of_study = filters.year;
  params.gpa_min = filters.gpa_min === "" ? 0 : (filters.gpa_min ?? 0);
  params.gpa_max = filters.gpa_max === "" ? 100 : (filters.gpa_max ?? 100);
  if (filters.status) params.status = filters.status;

  const response = await api.get("/application", {
    params,
    signal,
  });
  return response.data;
};

export const assignApplicant = async (applicationID, supervisorID) => {
  const response = await api.get(
    `/application/${applicationID}/assign/${supervisorID}`,
  );
  return response.data;
};
