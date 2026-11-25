import api from "../axios";

const requestLocale = () => ({
  headers: { "Accept-Language": document.documentElement.lang },
});

export const getVacationsRequests = async (page = 1) =>
  (await api.get("vacation_request/listForTrainee", { params: { page } })).data;

export const requestVacation = async (body) =>
  (await api.post("vacation_request", body, requestLocale())).data;

export const updateVacation = async (vacationId, body) =>
  (await api.put(`vacation_request/update/${vacationId}`, body, requestLocale())).data;

export const canceltVacation = async (vacationId) =>
  (await api.delete(`vacation_request/${vacationId}`, requestLocale())).data;
