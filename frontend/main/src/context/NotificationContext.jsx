import { createContext, useCallback, useContext, useEffect, useState } from "react";
import { getUnreadCount } from "../Api/notificationApi";
import { subscribeToForegroundPush } from "../firebase/firebase";

const NotificationContext = createContext(null);
const REFRESH_INTERVAL = 30_000;

export function NotificationProvider({ userId, children }) {
  const [unreadCount, setUnreadCount] = useState(0);
  const [revision, setRevision] = useState(0);
  const refresh = useCallback(() => setRevision((value) => value + 1), []);

  useEffect(() => {
    const request = new AbortController();
    getUnreadCount(request.signal).then((count) => {
      if (!request.signal.aborted) setUnreadCount(count);
    }).catch(() => {
      // Keep the last known count. The next refresh can recover it.
    });
    return () => request.abort();
  }, [revision]);

  useEffect(() => {
    const seen = new Set();
    const receivePush = (data) => {
      if (data?.recipient_id && String(data.recipient_id) !== String(userId)) return false;
      if (data?.notification_id) {
        if (seen.has(data.notification_id)) return false;
        seen.add(data.notification_id);
        if (seen.size > 100) seen.delete(seen.values().next().value);
      }
      refresh();
      return true;
    };
    const receiveWorkerMessage = ({ data }) => {
      if (data?.type === "inbox-push") receivePush(data.payload);
    };
    const refreshIfVisible = () => {
      if (document.visibilityState === "visible") refresh();
    };

    const unsubscribe = subscribeToForegroundPush(receivePush);
    navigator.serviceWorker?.addEventListener("message", receiveWorkerMessage);
    window.addEventListener("focus", refreshIfVisible);
    document.addEventListener("visibilitychange", refreshIfVisible);
    const timer = window.setInterval(refreshIfVisible, REFRESH_INTERVAL);

    return () => {
      unsubscribe();
      navigator.serviceWorker?.removeEventListener("message", receiveWorkerMessage);
      window.removeEventListener("focus", refreshIfVisible);
      document.removeEventListener("visibilitychange", refreshIfVisible);
      window.clearInterval(timer);
    };
  }, [refresh, userId]);

  return (
    <NotificationContext.Provider value={{ unreadCount, revision, refresh }}>
      {children}
    </NotificationContext.Provider>
  );
}

export function useNotifications() {
  return useContext(NotificationContext);
}
