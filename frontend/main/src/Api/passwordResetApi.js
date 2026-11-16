import api from "./axios";

export async function requestPasswordReset(email) {
  const response = await api.post("auth/forgotPassword", { email });
  return response.data;
}

export async function resetPassword(body) {
  const response = await api.post("auth/reset-password", body);
  return response.data;
}
