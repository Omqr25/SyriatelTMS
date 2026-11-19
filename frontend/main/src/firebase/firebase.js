import { initializeApp } from "firebase/app";
import { getMessaging, getToken, isSupported, onMessage } from "firebase/messaging";

import { firebaseConfig } from "./config";

const config = firebaseConfig(import.meta.env);
const vapidKey = import.meta.env.VITE_VAPID_KEY;
const messagingWorkerScope = "/firebase-cloud-messaging-push-scope";
let messaging;

export function hasBrowserPush() {
  return Boolean(config.apiKey && config.projectId && config.appId && config.messagingSenderId
    && vapidKey && window.isSecureContext && window.Notification && navigator.serviceWorker);
}

async function browserMessaging() {
  if (!hasBrowserPush() || !await isSupported()) {
    return null;
  }
  messaging ??= getMessaging(initializeApp(config));
  return messaging;
}

export async function requestForToken() {
  const client = await browserMessaging();
  return client ? getToken(client, { vapidKey }) : null;
}

export function subscribeToForegroundPush(onPush) {
  let active = true;
  let unsubscribe;
  browserMessaging().then((client) => {
    if (active && client) {
      unsubscribe = onMessage(client, async ({ data, notification }) => {
        if (!onPush(data) || !notification || window.Notification?.permission !== "granted") return;
        try {
          const options = {
            body: notification.body,
            tag: data?.notification_id ? `inbox-${data.notification_id}` : undefined,
          };
          const worker = await navigator.serviceWorker.getRegistration(messagingWorkerScope);
          if (!active) return;
          if (worker) {
            await worker.showNotification(notification.title, options);
          } else {
            new window.Notification(notification.title, options);
          }
        } catch (error) {
          console.warn("Could not display browser notification:", error.message);
        }
      });
    }
  }).catch((error) => console.warn("Could not subscribe to browser notifications:", error.message));

  return () => {
    active = false;
    unsubscribe?.();
  };
}
