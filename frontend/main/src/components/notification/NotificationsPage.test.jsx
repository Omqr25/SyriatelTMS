import { act, cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, expect, it, vi } from "vitest";
import { MemoryRouter } from "react-router-dom";
import { getInbox, getUnreadCount, markNotificationRead, revokeNotificationTokenApi, registerNotificationTokenApi } from "../../Api/notificationApi";
import { NotificationProvider, useNotifications } from "../../context/NotificationContext";
import { ACCESS_TOKEN_KEY } from "../../Api/axios";
import { hasBrowserPush, requestForToken, subscribeToForegroundPush } from "../../firebase/firebase";
import NotificationsPage from "./NotificationsPage";
import TraineeAnnouncements from "../../roles/trainee/pages/announcement/AnnouncmentPage";
import { getAnnouncementsTraineeApi } from "../../Api/Trainee/announcementApi";

vi.mock("../../Api/Trainee/announcementApi");

vi.mock("../../Api/notificationApi", () => ({
  getInbox: vi.fn(),
  getUnreadCount: vi.fn(),
  markNotificationRead: vi.fn(),
  markAllNotificationsRead: vi.fn(),
  registerNotificationTokenApi: vi.fn(),
  revokeNotificationTokenApi: vi.fn(),
}));

vi.mock("../../firebase/firebase", () => ({
  hasBrowserPush: vi.fn(() => false),
  requestForToken: vi.fn(),
  subscribeToForegroundPush: vi.fn(() => () => {}),
}));
vi.mock("../../context/AuthContext", () => ({ useAuth: () => ({ user: { roles: ["TRAINEE"] } }) }));

vi.mock("react-i18next", () => {
  const t = (key) => key;
  return { useTranslation: () => ({ t, i18n: { language: "en" } }) };
});

afterEach(() => {
  cleanup();
  localStorage.clear();
  vi.unstubAllGlobals();
  hasBrowserPush.mockReturnValue(false);
});

const notification = {
  id: 1,
  title: "Task approved",
  description: "Training task",
  read: false,
  type: "task_feedback_added",
  metadata: { task_id: "9" },
};

function inbox(item) {
  return { data: [item], meta: { total: 1, current_page: 1, last_page: 1 } };
}

function BadgeCount() {
  const { unreadCount } = useNotifications();
  return <output data-testid="unread-count">{unreadCount}</output>;
}

const accessToken = `header.${btoa(JSON.stringify({ sub: 42 }))}.signature`;

function showInbox() {
  localStorage.setItem(ACCESS_TOKEN_KEY, accessToken);
  vi.stubGlobal("caches", {
    open: vi.fn(async () => ({ put: vi.fn(async () => {}) })),
    delete: vi.fn(async () => true),
  });
  render(
    <MemoryRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <NotificationProvider accessToken={accessToken} userId={42}>
        <BadgeCount />
        <NotificationsPage />
      </NotificationProvider>
    </MemoryRouter>
  );
}

it("retries a failed inbox request and reconciles the list and bell after marking read", async () => {
  getInbox.mockRejectedValueOnce(new Error("Offline")).mockResolvedValueOnce(inbox(notification));
  getUnreadCount.mockResolvedValue(1);
  showInbox();

  fireEvent.click(await screen.findByRole("button", { name: "notificationsPage.retry" }));
  await screen.findByText("Task approved");
  expect(screen.getByRole("link", { name: "common:action.viewDetails" }).getAttribute("href"))
    .toBe("/trainee/tasks/details/9");

  getInbox.mockResolvedValue(inbox({ ...notification, read: true }));
  getUnreadCount.mockResolvedValue(0);
  markNotificationRead.mockResolvedValue({});
  fireEvent.click(screen.getByRole("button", { name: "notificationsPage.markRead" }));
  await waitFor(() => expect(screen.queryByRole("button", { name: "notificationsPage.markRead" })).toBeNull());
  expect(markNotificationRead).toHaveBeenCalledWith(1);
  expect(screen.getByTestId("unread-count").textContent).toBe("0");
});

it("ignores other recipients and duplicate push signals, and discards stale inbox results", async () => {
  let resolveOldResponse;
  getInbox.mockImplementationOnce(() => new Promise((resolve) => { resolveOldResponse = resolve; }))
    .mockResolvedValue(inbox({ ...notification, title: "Newer notification" }));
  getUnreadCount.mockResolvedValue(1);
  showInbox();

  const receivePush = subscribeToForegroundPush.mock.calls[0][0];
  act(() => receivePush({ recipient_id: "99", notification_id: "1" }));
  expect(getInbox).toHaveBeenCalledTimes(1);
  act(() => receivePush({ recipient_id: "42", notification_id: "1" }));
  await screen.findByText("Newer notification");
  act(() => receivePush({ recipient_id: "42", notification_id: "1" }));
  expect(getInbox).toHaveBeenCalledTimes(2);

  await act(async () => resolveOldResponse(inbox({ ...notification, title: "Stale notification" })));
  expect(screen.queryByText("Stale notification")).toBeNull();
});

it("registers browsers that already granted permission when a session opens", async () => {
  hasBrowserPush.mockReturnValue(true);
  const requestPermission = vi.fn();
  vi.stubGlobal("Notification", { permission: "granted", requestPermission });
  requestForToken.mockResolvedValue("existing-browser-token");
  registerNotificationTokenApi.mockResolvedValue({});
  getInbox.mockResolvedValue(inbox(notification));
  getUnreadCount.mockResolvedValue(1);
  showInbox();

  await waitFor(() => expect(registerNotificationTokenApi).toHaveBeenCalledWith(
    "existing-browser-token", accessToken,
  ));
  expect(requestPermission).not.toHaveBeenCalled();
});

it("asks for browser permission on a click and retries a failed registration", async () => {
  hasBrowserPush.mockReturnValue(true);
  const browser = {
    permission: "default",
    requestPermission: vi.fn(async () => {
      browser.permission = "granted";
      return "granted";
    }),
  };
  vi.stubGlobal("Notification", browser);
  requestForToken.mockResolvedValue("new-browser-token");
  registerNotificationTokenApi.mockRejectedValueOnce(new Error("Offline")).mockResolvedValueOnce({});
  getInbox.mockResolvedValue(inbox(notification));
  getUnreadCount.mockResolvedValue(1);
  showInbox();

  const label = "notificationsPage.enableBrowserPush";
  expect(browser.requestPermission).not.toHaveBeenCalled();
  fireEvent.click(await screen.findByRole("button", { name: label }));
  await screen.findByText("notificationsPage.browserPushError");
  fireEvent.click(screen.getByRole("button", { name: label }));
  await waitFor(() => expect(screen.queryByRole("button", { name: label })).toBeNull());
  expect(browser.requestPermission).toHaveBeenCalledTimes(1);
  expect(registerNotificationTokenApi).toHaveBeenCalledTimes(2);
});


it("retains the rendered list if refreshing fails and resolves a program date destination", async () => {
  getInbox.mockResolvedValue(inbox({ ...notification, type: "training_program.dates_changed" }));
  getUnreadCount.mockResolvedValue(1);
  showInbox();
  await screen.findByText("Task approved");
  expect(screen.getByRole("link").getAttribute("href")).toBe("/trainee/training-programs");

  getInbox.mockRejectedValue(new Error("Network error"));
  getUnreadCount.mockRejectedValue(new Error("Network error"));
  act(() => subscribeToForegroundPush.mock.calls[0][0]({ recipient_id: "42", notification_id: "47" }));
  await screen.findByRole("button", { name: "notificationsPage.retry" });
  expect(screen.getByText("Task approved")).toBeTruthy();
  expect(screen.getByTestId("unread-count").textContent).toBe("1");
});

it("revokes the token when browser notifications are disabled", async () => {
  hasBrowserPush.mockReturnValue(true);
  vi.stubGlobal("Notification", { permission: "granted" });
  requestForToken.mockResolvedValue("device-to-revoke");
  registerNotificationTokenApi.mockResolvedValue({});
  revokeNotificationTokenApi.mockResolvedValue({});
  getInbox.mockResolvedValue(inbox(notification));
  getUnreadCount.mockResolvedValue(1);
  showInbox();

  fireEvent.click(await screen.findByRole("button", { name: "notificationsPage.disableBrowserPush" }));
  await screen.findByRole("button", { name: "notificationsPage.enableBrowserPush" });
  expect(revokeNotificationTokenApi).toHaveBeenCalledWith("device-to-revoke", accessToken);
});

it("refreshes the open trainee announcements after push, deletion and reconnect", async () => {
  getUnreadCount.mockResolvedValue(0);
  getAnnouncementsTraineeApi.mockResolvedValue({ data: [] });
  render(
    <NotificationProvider accessToken={accessToken} userId={42}>
      <TraineeAnnouncements />
    </NotificationProvider>
  );
  await screen.findByText("announcements.listTitle");
  const receivePush = subscribeToForegroundPush.mock.calls.at(-1)[0];
  const announcement = {
    id: 7, title: "New announcement", description: "Schedule",
    publisher: { name: "HR" }, announced_at: "2026-11-22 10:30",
  };
  getAnnouncementsTraineeApi.mockResolvedValue({ data: [announcement] });
  act(() => receivePush({ notification_id: "40", recipient_id: "42", type: "announcement" }));
  expect(await screen.findByText("New announcement")).toBeTruthy();

  getAnnouncementsTraineeApi.mockResolvedValue({ data: [{ ...announcement, title: "Updated announcement" }] });
  act(() => expect(receivePush({ notification_id: "41", recipient_id: "42", type: "announcement_sync" })).toBe(false));
  expect(await screen.findByText("Updated announcement")).toBeTruthy();

  getAnnouncementsTraineeApi.mockResolvedValue({ data: [] });
  act(() => receivePush({ notification_id: "42", recipient_id: "42", type: "announcement_sync" }));
  await waitFor(() => expect(screen.queryByText("Updated announcement")).toBeNull());

  getAnnouncementsTraineeApi.mockResolvedValue({ data: [announcement] });
  act(() => window.dispatchEvent(new Event("online")));
  expect(await screen.findByText("New announcement")).toBeTruthy();
});
