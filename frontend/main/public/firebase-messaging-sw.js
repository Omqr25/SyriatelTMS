
importScripts(
  "https://www.gstatic.com/firebasejs/13.1.0/firebase-app-compat.js"
);
importScripts(
  "https://www.gstatic.com/firebasejs/13.1.0/firebase-messaging-compat.js"
);

importScripts("./firebase-worker-config.js");

const config = self.firebaseWorkerConfig;
if (config.apiKey && config.projectId && config.appId && config.messagingSenderId) {
  firebase.initializeApp(config);
  firebase.messaging().onBackgroundMessage(async (payload) => {
    const windows = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    for (const client of windows) {
      client.postMessage({ type: "inbox-push", payload: payload.data });
    }
  });
}
