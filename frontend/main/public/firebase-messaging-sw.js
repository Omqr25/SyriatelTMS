const inboxPaths = new Set(["/hr/notification", "/supervisor/notification", "/trainee/notification"]);
const shown = new Set();
const ownerCache = "syriatel-tms-push-owner";
const ownerPath = "/__tms_push_owner__";

// Register the click handler before loading Firebase so the inbox controls its own click targets.
self.addEventListener("notificationclick", (event) => {
  if (!event.notification.tag.startsWith("inbox-")) return;
  event.notification.close();
  const path = event.notification.data?.path;
  const url = new URL(inboxPaths.has(path) ? path : "/", self.location.origin).href;
  event.waitUntil((async () => {
    const windows = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    const existing = windows.find((client) => client.url === url);
    return existing ? existing.focus() : self.clients.openWindow(url);
  })());
});

async function showInboxPush(data) {
  if (data?.type === "announcement_sync") return;
  if (!/^\d+$/.test(data?.notification_id) || !/^\d+$/.test(data?.recipient_id)) return;
  const owner = await caches.match(ownerPath, { cacheName: ownerCache });
  if (await owner?.text() !== data.recipient_id) return;

  const tag = `inbox-${data.recipient_id}-${data.notification_id}`;
  if (shown.has(tag)) return;
  shown.add(tag);
  if (shown.size > 100) shown.delete(shown.values().next().value);

  try {
    const existing = await self.registration.getNotifications({ tag });
    if (existing.length) return;
    const arabic = self.navigator.language?.startsWith("ar");
    await self.registration.showNotification("Syriatel TMS", {
      body: arabic ? "افتح التطبيق لعرض الإشعار الجديد." : "Open the app to view your new notification.",
      tag,
      data: { path: data.inbox_path },
    });
  } catch (error) {
    shown.delete(tag);
    throw error;
  }
}

self.addEventListener("message", (event) => {
  if (event.data?.type === "show-inbox-push") {
    event.waitUntil(showInboxPush(event.data.payload));
  }
});

importScripts(
  "./firebase-app-compat.js",
  "./firebase-messaging-compat.js",
  "./firebase-worker-config.js",
);

const config = self.firebaseWorkerConfig;
if (config?.apiKey && config.projectId && config.appId && config.messagingSenderId) {
  firebase.initializeApp(config);
  firebase.messaging().onBackgroundMessage(async (payload) => {
    const windows = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    for (const client of windows) {
      client.postMessage({ type: "inbox-push", payload: payload.data });
    }
    // Old messages already displayed by Firebase must not generate a second pop-up.
    if (!payload.notification) await showInboxPush(payload.data);
  });
}
