import { ACCESS_TOKEN_KEY } from "../Api/axios";
import { registerNotificationTokenApi, revokeNotificationTokenApi } from "../Api/notificationApi";
import { decodeJwt } from "../features/auth/auth.utils";
import { deleteBrowserToken, requestForToken } from "./firebase";

const REGISTRATION_KEY = "syriatel-tms:push-device";
// Shared with the service worker. This contains only the active push account ID.
const OWNER_CACHE = "syriatel-tms-push-owner";
const OWNER_PATH = "/__tms_push_owner__";
export const pushOptOutKey = (userId) => `syriatel-tms:push-disabled:${userId}`;
let queue = Promise.resolve();

function serialize(operation) {
  const run = () => navigator.locks
    ? navigator.locks.request(REGISTRATION_KEY, operation)
    : operation();
  const task = queue.then(run);
  queue = task.catch(() => {});
  return task;
}

function storedRegistration() {
  try {
    return JSON.parse(localStorage.getItem(REGISTRATION_KEY));
  } catch {
    return null;
  }
}

export function registerBrowser(accessToken, userId, signal) {
  const current = () => !signal.aborted
    && localStorage.getItem(ACCESS_TOKEN_KEY) === accessToken
    && !localStorage.getItem(pushOptOutKey(userId));
  return serialize(async () => {
    if (!current()) return false;
    const token = await requestForToken();
    if (!token || !current()) return false;

    const previous = storedRegistration();
    if (previous && previous.userId === String(userId) && previous.token !== token) {
      await revokeNotificationTokenApi(previous.token, accessToken);
      if (!current()) return false;
    }

    // Keep the token even if the response is lost after the server registers it.
    localStorage.setItem(REGISTRATION_KEY, JSON.stringify({ token, userId: String(userId) }));
    const owner = await caches.open(OWNER_CACHE);
    await owner.put(OWNER_PATH, new Response(String(userId)));
    await registerNotificationTokenApi(token, accessToken);
    return current();
  });
}

export function revokeBrowserRegistration(accessToken) {
  return serialize(async () => {
    const registration = storedRegistration();
    if (!registration || registration.userId !== String(decodeJwt(accessToken)?.sub)) return true;

    // Prevent even an already queued push from displaying after this browser signs out.
    await caches.delete(OWNER_CACHE).catch(() => {});
    try {
      await revokeNotificationTokenApi(registration.token, accessToken);
    } catch {
      // An expired session cannot revoke its server-side row. Invalidate the FCM token instead.
      try {
        if (!await deleteBrowserToken()) return false;
      } catch {
        return false;
      }
    }

    localStorage.removeItem(REGISTRATION_KEY);
    return true;
  }).catch(() => false);
}
