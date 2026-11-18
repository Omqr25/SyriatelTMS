
import { initializeApp } from "firebase/app";
import { getMessaging, getToken, onMessage } from "firebase/messaging";

export const firebaseConfig = {
  apiKey: import.meta.env.VITE_API_KEY,
  authDomain: import.meta.env.VITE_AUTH_DOMAIN,
  projectId: import.meta.env.VITE_PROJECT_ID,
  storageBucket: import.meta.env.VITE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_MESSAGING_SENDER_ID,
  appId: import.meta.env.VITE_APP_ID,
};

export const FIREBASE_VAPID_KEY = import.meta.env.VITE_VAPID_KEY;

// Firebase is optional: without its settings the app still loads, without push.
const firebaseEnabled = Boolean(
  firebaseConfig.apiKey && firebaseConfig.projectId && firebaseConfig.appId,
);
let messaging = null;
if (firebaseEnabled) {
  try {
    messaging = getMessaging(initializeApp(firebaseConfig));
  } catch (err) {
    console.error("Firebase init failed:", err);
  }
}

export const requestForToken = () => {
  if (!messaging) return Promise.resolve(null);
  return getToken(messaging, { vapidKey: FIREBASE_VAPID_KEY })
    .then((currentToken) => {
      if (currentToken) {
        return currentToken;
      } else {
        alert(
          "No registration token available. Request permission to generate one."
        );
        return null;
      }
    })
    .catch((err) => {
      alert("An error occurred while retrieving token - " + err);
      return null;
    });
};

if (messaging) {
  onMessage(messaging, ({ notification }) => {
    new Notification(notification.title, {
      body: notification.body,
      icon: notification.icon,
    });
  });
}

