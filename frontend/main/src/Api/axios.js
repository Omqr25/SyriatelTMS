import axios from "axios";
import { apiBaseUrl } from "./apiBaseUrl";

export const ACCESS_TOKEN_KEY = "syriatel-tms:access-token";

const api = axios.create({
  baseURL: apiBaseUrl(import.meta.env.VITE_API_URL),
  timeout: 30000,
});

// إضافة JWT تلقائيًا إلى كل طلب
api.interceptors.request.use((config) => {
  const token = localStorage.getItem(ACCESS_TOKEN_KEY);

  if (token && !config.headers.has("Authorization") && config.url !== "auth/login") {
    config.headers.set("Authorization", `Bearer ${token}`);
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,

  (error) => {
    const status = error.response?.status;

    const token = localStorage.getItem(ACCESS_TOKEN_KEY);
    const currentSession = token && error.config?.headers?.get("Authorization") === `Bearer ${token}`;
    if (status === 401 && currentSession) {
      window.dispatchEvent(new Event("auth:unauthorized"));
    }

    if (status === 403 && currentSession) {
      window.dispatchEvent(new Event("auth:forbidden"));
    }

    return Promise.reject(error);
  }
);


export default api;
