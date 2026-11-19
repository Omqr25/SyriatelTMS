import api from "./axios";

export function registerNotificationTokenApi(token, signal) {
  return api.post("notification/device-tokens", { token, platform: "web" }, { signal });
}

export async function getInbox(page, signal) {
  const response = await api.get("notification/myNotifications", { params: { page }, signal });
  return response.data;
}

export async function getUnreadCount(signal) {
  const response = await api.get("notification/unread-count", { signal });
  return response.data.data.unread_count;
}

export function markNotificationRead(id) {
  return api.patch(`notification/${id}/read`);
}

export function markAllNotificationsRead() {
  return api.patch("notification/read-all");
}
