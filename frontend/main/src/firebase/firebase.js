import { initializeApp } from "firebase/app";
import { getMessaging, getToken, isSupported, onMessage } from "firebase/messaging";

const firebaseConfig = {
  apiKey: import.meta.env.VITE_API_KEY,
  authDomain: import.meta.env.VITE_AUTH_DOMAIN,
  projectId: import.meta.env.VITE_PROJECT_ID,
  storageBucket: import.meta.env.VITE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_MESSAGING_SENDER_ID,
  appId: import.meta.env.VITE_APP_ID,
};

const vapidKey = import.meta.env.VITE_VAPID_KEY;
let messaging;

async function browserMessaging() {
  if (!firebaseConfig.apiKey || !firebaseConfig.projectId || !firebaseConfig.appId || !await isSupported()) {
    return null;
  }
  messaging ??= getMessaging(initializeApp(firebaseConfig));
  return messaging;
}

export async function requestForToken() {
  const client = await browserMessaging();
  return client && vapidKey ? getToken(client, { vapidKey }) : null;
}

export function subscribeToForegroundPush(onPush) {
  let active = true;
  let unsubscribe;
  browserMessaging().then((client) => {
    if (active && client) {
      unsubscribe = onMessage(client, ({ data, notification }) => {
        if (!onPush(data) || !notification || window.Notification?.permission !== "granted") return;
        try {
          new Notification(notification.title, {
            body: notification.body,
            tag: data?.notification_id ? `inbox-${data.notification_id}` : undefined,
          });
        } catch {
          // Some browsers only allow service workers to display system notifications.
        }
      });
    }
  }).catch((error) => console.warn("Could not subscribe to browser notifications:", error.message));

  return () => {
    active = false;
    unsubscribe?.();
  };
}
