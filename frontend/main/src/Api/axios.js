import axios from "axios";

const api = axios.create({
  // baseURL: "https://syriatel.houseofgeniuses.tech/api/v1",
  // baseURL: "https://syriateltms-production.up.railway.app/api/v1",
  baseURL: import.meta.env.VITE_API_URL,
  timeout: 30000,
});

// إضافة JWT تلقائيًا إلى كل طلب
api.interceptors.request.use((config) => {
  const token = localStorage.getItem("syriatel-tms:access-token");

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

api.interceptors.response.use(
  (response) => response,

  (error) => {
    const status = error.response?.status;

    if (status === 401) {
      window.dispatchEvent(new Event("auth:unauthorized"));
    }

    if (status === 403) {
      window.dispatchEvent(new Event("auth:forbidden"));
    }

    return Promise.reject(error);
  }
);


export default api;