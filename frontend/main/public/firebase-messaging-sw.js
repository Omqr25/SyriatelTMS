
importScripts(
  "https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js"
);
importScripts(
  "https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js"
);

importScripts("/firebase-worker-config.js");

firebase.initializeApp(self.firebaseWorkerConfig);
firebase.messaging().onBackgroundMessage(async (payload) => {
  const windows = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
  for (const client of windows) {
    client.postMessage({ type: "inbox-push", payload: payload.data });
  }
});
