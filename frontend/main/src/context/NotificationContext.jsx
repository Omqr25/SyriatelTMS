import { createContext, useCallback, useContext, useEffect, useRef, useState } from "react";
import { getUnreadCount } from "../Api/notificationApi";
import { hasBrowserPush, subscribeToForegroundPush } from "../firebase/firebase";
import { pushOptOutKey, registerBrowser, revokeBrowserRegistration } from "../firebase/registration";

const NotificationContext = createContext(null);
const REFRESH_INTERVAL = 30_000;

export function NotificationProvider({ accessToken, userId, children }) {
  const [unreadCount, setUnreadCount] = useState(null);
  const [countUnavailable, setCountUnavailable] = useState(false);
  const [announcementRevision, setAnnouncementRevision] = useState(0);
  const [revision, setRevision] = useState(0);
  const refresh = useCallback(() => setRevision((value) => value + 1), []);

  const [pushStatus, setPushStatus] = useState("unavailable");
  const pushSession = useRef(null);
  const optOutKey = pushOptOutKey(userId);

  const enableBrowserPush = useCallback(async (prompt = true) => {
    const signal = pushSession.current?.signal;
    if (!signal || signal.aborted) return;

    if (!prompt && localStorage.getItem(optOutKey)) {
      setPushStatus("available");
      return;
    }

    setPushStatus("registering");
    try {
      const permission = prompt && window.Notification.permission === "default"
        ? await window.Notification.requestPermission()
        : window.Notification.permission;
      if (signal.aborted) return;
      if (permission !== "granted") {
        setPushStatus(permission === "denied" ? "blocked" : "available");
        return;
      }

      if (prompt) localStorage.removeItem(optOutKey);
      const registered = await registerBrowser(accessToken, userId, signal);
      if (!signal.aborted) setPushStatus(registered ? "enabled" : "unavailable");
    } catch {
      if (!signal.aborted) setPushStatus("failed");
    }
  }, [accessToken, optOutKey, userId]);

  const disableBrowserPush = async () => {
    const signal = pushSession.current?.signal;
    if (!signal || signal.aborted) return;

    localStorage.setItem(optOutKey, "1");
    setPushStatus("disabling");
    const revoked = await revokeBrowserRegistration(accessToken);
    if (!signal.aborted) setPushStatus(revoked ? "available" : "disable-failed");
  };

  useEffect(() => {
    if (!hasBrowserPush()) return;

    const session = new AbortController();
    pushSession.current = session;
    enableBrowserPush(false);
    return () => session.abort();
  }, [enableBrowserPush, userId]);

  useEffect(() => {
    const request = new AbortController();
    getUnreadCount(request.signal).then((count) => {
      if (request.signal.aborted) return;
      setUnreadCount(count);
      setCountUnavailable(false);
    }).catch(() => {
      if (!request.signal.aborted) setCountUnavailable(true);
    });
    return () => request.abort();
  }, [revision]);

  useEffect(() => {
    const seen = new Set();
    const receivePush = (data) => {
      if (!data?.recipient_id || String(data.recipient_id) !== String(userId)) return false;
      if (data?.notification_id) {
        if (seen.has(data.notification_id)) return false;
        seen.add(data.notification_id);
        if (seen.size > 100) seen.delete(seen.values().next().value);
      }
      refresh();
      const announcementChanged = data.type === "announcement" || data.type === "announcement_sync";
      if (announcementChanged) setAnnouncementRevision((value) => value + 1);
      return data.type !== "announcement_sync" && !localStorage.getItem(optOutKey);
    };
    const receiveWorkerMessage = ({ data }) => {
      if (data?.type === "inbox-push") receivePush(data.payload);
    };
    const refreshIfVisible = () => {
      if (document.visibilityState === "visible") {
        refresh();
        setAnnouncementRevision((value) => value + 1);
      }
    };

    const unsubscribe = subscribeToForegroundPush(receivePush);
    navigator.serviceWorker?.addEventListener("message", receiveWorkerMessage);
    window.addEventListener("focus", refreshIfVisible);
    window.addEventListener("online", refreshIfVisible);
    document.addEventListener("visibilitychange", refreshIfVisible);
    const timer = window.setInterval(refreshIfVisible, REFRESH_INTERVAL);

    return () => {
      unsubscribe();
      navigator.serviceWorker?.removeEventListener("message", receiveWorkerMessage);
      window.removeEventListener("focus", refreshIfVisible);
      window.removeEventListener("online", refreshIfVisible);
      document.removeEventListener("visibilitychange", refreshIfVisible);
      window.clearInterval(timer);
    };
  }, [optOutKey, refresh, userId]);

  return (
    <NotificationContext.Provider value={{ unreadCount, countUnavailable, revision, announcementRevision, refresh, pushStatus, enableBrowserPush, disableBrowserPush }}>
      {children}
    </NotificationContext.Provider>
  );
}

export function useNotifications() {
  return useContext(NotificationContext);
}
