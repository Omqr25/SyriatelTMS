import api from "../../Api/axios";

const requestLocale = () => ({ headers: { "Accept-Language": document.documentElement.lang } });

export const getAttendance = (path, query, signal) =>
  api.get(path, { params: new URLSearchParams(query), signal, ...requestLocale() }).then(({ data }) => data);

export const attendanceLists = {
  absence: "attendance/absences",
  records: "attendance/trainees",
  HR: "attendance/leaves",
  SUPERVISOR: "vacation_request/listForTrainer",
  TRAINEE: "vacation_request/listForTrainee",
};

export const attendanceActions = {
  recordAbsence: (id, body) => api.post(`absence/recordAbsence/${id}`, body, requestLocale()),
  editAbsence: (id, body) => api.patch(`absence/${id}`, body, requestLocale()),
  deleteAbsence: (id) => api.delete(`absence/${id}`, requestLocale()),
  requestLeave: (_, body) => api.post("vacation_request", body, requestLocale()),
  editLeave: (id, body) => api.put(`vacation_request/update/${id}`, body, requestLocale()),
  cancelLeave: (id) => api.delete(`vacation_request/${id}`, requestLocale()),
  cancelApproved: (id) => api.patch(`vacation_request/${id}/cancel-approved`, {}, requestLocale()),
  approve: (id) => api.patch(`vacation_request/decide/${id}`, { status: "accepted" }, requestLocale()),
  reject: (id) => api.patch(`vacation_request/decide/${id}`, { status: "rejected" }, requestLocale()),
};
