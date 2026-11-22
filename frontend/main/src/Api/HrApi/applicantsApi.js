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
  sort,
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
  if (filters.gpa_eligibility) params.gpa_eligibility = filters.gpa_eligibility;
  if (filters.english_eligibility) params.english_eligibility = filters.english_eligibility;
  if (filters.status) params.status = filters.status;
  if (sort) {
    params.sort_by = sort.key;
    params.sort_direction = sort.direction;
  }

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
