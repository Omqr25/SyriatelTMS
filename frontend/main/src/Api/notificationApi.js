import api from "./axios";

export function registerNotificationTokenApi(token, accessToken) {
  return api.post("notification/device-tokens", { token, platform: "web" }, {
    headers: { Authorization: `Bearer ${accessToken}` },
    timeout: 5000,
  });
}

export function revokeNotificationTokenApi(token, accessToken) {
  return api.delete("notification/device-tokens", {
    data: { token },
    headers: { Authorization: `Bearer ${accessToken}` },
    timeout: 5000,
  });
}

export async function getInbox(page, signal) {
  const response = await api.get("notification/myNotifications", { params: { page }, signal });
  if (!Array.isArray(response.data?.data) || !response.data.meta) {
    throw new Error("Invalid notification inbox response");
  }
  return response.data;
}

export async function getUnreadCount(signal) {
  const response = await api.get("notification/unread-count", { signal });
  const count = response.data?.data?.unread_count;
  if (!Number.isSafeInteger(count) || count < 0) throw new Error("Invalid notification count");
  return count;
}

export function markNotificationRead(id) {
  return api.patch(`notification/${id}/read`);
}

export function markAllNotificationsRead() {
  return api.patch("notification/read-all");
}
