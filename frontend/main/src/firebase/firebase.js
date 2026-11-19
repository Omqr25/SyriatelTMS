import { initializeApp } from "firebase/app";
import { deleteToken, getMessaging, getToken, isSupported, onMessage } from "firebase/messaging";

import { firebaseConfig } from "./config";

const config = firebaseConfig(import.meta.env);
const vapidKey = import.meta.env.VITE_VAPID_KEY;
const messagingWorkerScope = `${import.meta.env.BASE_URL}firebase-cloud-messaging-push-scope`;
let messaging;

export function hasBrowserPush() {
  return Boolean(config.apiKey && config.projectId && config.appId && config.messagingSenderId
    && vapidKey && window.isSecureContext && window.Notification && navigator.serviceWorker && window.caches);
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

export async function deleteBrowserToken() {
  const client = await browserMessaging();
  return client ? deleteToken(client) : false;
}

export function subscribeToForegroundPush(onPush) {
  let active = true;
  let unsubscribe;
  browserMessaging().then((client) => {
    if (active && client) {
      unsubscribe = onMessage(client, async ({ data }) => {
        if (!onPush(data) || window.Notification.permission !== "granted") return;
        try {
          const worker = await navigator.serviceWorker.getRegistration(messagingWorkerScope);
          if (active) worker?.active?.postMessage({ type: "show-inbox-push", payload: data });
        } catch (error) {
          console.warn("Could not forward browser notification:", error.message);
        }
      });
    }
  }).catch((error) => console.warn("Could not subscribe to browser notifications:", error.message));

  return () => {
    active = false;
    unsubscribe?.();
  };
}
