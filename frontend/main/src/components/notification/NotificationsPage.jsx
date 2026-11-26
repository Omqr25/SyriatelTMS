import { useEffect, useState } from "react";
import { Bell, ChevronLeft, ChevronRight, RefreshCw } from "lucide-react";
import { useTranslation } from "react-i18next";

import { Link } from "react-router-dom";
import { getInbox, markAllNotificationsRead, markNotificationRead } from "../../Api/notificationApi";
import { useNotifications } from "../../context/NotificationContext";
import { useAuth } from "../../context/AuthContext";
import Button from "../ui/Button";
import styles from "./NotificationsPage.module.css";

const DESTINATIONS = {
  HR: { evaluation_completed: "/hr/evaluations", training_program_registration_reminder: "/hr/registration" },
  SUPERVISOR: {
    application_assigned: "/supervisor/assigned-requests",
    "training_program.dates_changed": "/supervisor/programs",
    interview: "/supervisor/interviews",
    reevaluation_request_created: "/supervisor/re-evaluations",
    vacation_request_created: "/supervisor/attendance?tab=leave",
  },
  TRAINEE: {
    evaluation_completed: "/trainee/evaluation",
    reevaluation_completed: "/trainee/evaluation",
    vacation_request_decided: "/trainee/attendance?tab=leave",
    "training_program.dates_changed": "/trainee/training-programs",
    announcement: "/trainee/announcements",
  },
};

function destination(notification, role) {
  const id = notification.metadata?.task_id;
  const taskId = typeof id === "string" && /^\d+$/.test(id) ? Number(id) : id;
  if (Number.isSafeInteger(taskId) && taskId > 0) {
    if (role === "SUPERVISOR" && notification.type === "task_evidence_submitted") {
      return `/supervisor/tasks/details/${taskId}`;
    }
    if (role === "TRAINEE" && ["task_assigned", "task_feedback_added"].includes(notification.type)) {
      return `/trainee/tasks/details/${taskId}`;
    }
  }
  return DESTINATIONS[role]?.[notification.type];
}

export default function NotificationsPage() {
  const { t, i18n } = useTranslation("hr");

  const [notifications, setNotifications] = useState([]);
  const { unreadCount, revision, refresh, pushStatus, enableBrowserPush, disableBrowserPush } = useNotifications();
  const { user } = useAuth();
  const [marking, setMarking] = useState(false);
  const [actionError, setActionError] = useState(false);
  const [page, setPage] = useState(1);
  const [meta, setMeta] = useState({
    current_page: 1,
    last_page: 1,
    total: 0,
    per_page: 10,
  });

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const isArabic = i18n.language.startsWith("ar");

  useEffect(() => {
    const request = new AbortController();

    getInbox(page, request.signal).then((result) => {
      if (request.signal.aborted) return;
      setNotifications([...new Map(result.data.map((item) => [item.id, item])).values()]);
      setMeta(result.meta);
      setError("");
    }).catch((err) => {
      if (!request.signal.aborted) setError(typeof err.response?.data?.message === "string"
        ? err.response.data.message : t("notificationsPage.loadError"));
    }).finally(() => {
      if (!request.signal.aborted) setLoading(false);
    });
    return () => request.abort();
  }, [page, revision, t]);

  const markRead = async (id) => {
    setMarking(true);
    setActionError(false);
    try {
      await (id === undefined ? markAllNotificationsRead() : markNotificationRead(id));
      refresh();
    } catch {
      setActionError(true);
    } finally {
      setMarking(false);
    }
  };

  const formatDate = (date) => {
    if (!date) return "";

    const parsedDate = new Date(date);

    if (Number.isNaN(parsedDate.getTime())) {
      return date;
    }

    return parsedDate.toLocaleString(
      isArabic ? "ar" : "en",
      {
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      }
    );
  };

  const changePage = (newPage) => {
    if (
      newPage < 1 ||
      newPage > meta.last_page ||
      newPage === meta.current_page
    ) {
      return;
    }

    setLoading(true);
    if (newPage === page) refresh();
    else setPage(newPage);
  };

  return (
    <main
      className={styles.page}
      dir={isArabic ? "rtl" : "ltr"}
      lang={isArabic ? "ar" : "en"}
    >
      <header className={styles.header}>
        <div className={styles.headerIcon}>
          <Bell size={25} />
        </div>

        <div className={styles.headerContent}>
          <h1>{t("notificationsPage.title")}</h1>
          <p>{t("notificationsPage.subtitle")}</p>
        </div>

        <span className={styles.totalBadge}>
          {meta.total}
        </span>
      </header>

      {["available", "registering", "failed"].includes(pushStatus) && (
        <div>
          <Button variant="secondary" loading={pushStatus === "registering"} onClick={() => enableBrowserPush()}>
            {t("notificationsPage.enableBrowserPush")}
          </Button>
          {pushStatus === "failed" && <p role="alert">{t("notificationsPage.browserPushError")}</p>}
        </div>
      )}
      {["enabled", "disabling", "disable-failed"].includes(pushStatus) && (
        <div>
          <Button variant="secondary" loading={pushStatus === "disabling"} onClick={disableBrowserPush}>
            {t("notificationsPage.disableBrowserPush")}
          </Button>
          {pushStatus === "disable-failed" && <p role="alert">{t("notificationsPage.disableBrowserPushError")}</p>}
        </div>
      )}
      {pushStatus === "unavailable" && <p>{t("notificationsPage.browserPushUnavailable")}</p>}
      {pushStatus === "blocked" && <p>{t("notificationsPage.browserPushBlocked")}</p>}

      {error && (
        <div className={styles.errorMessage} role="alert">
          <p>{error}</p>

          <button
            type="button"
            onClick={refresh}
          >
            <RefreshCw size={16} />
            {t("notificationsPage.retry")}
          </button>
        </div>
      )}

      {actionError && <p role="alert">{t("notificationsPage.markError")}</p>}

      <section className={styles.panel}>
        <div className={styles.panelHeader}>
          <div>
            <h2>{t("notificationsPage.listTitle")}</h2>
            <p>
              {t("notificationsPage.totalCount", {
                count: meta.total,
              })}
            </p>
          </div>
          <Button size="sm" variant="secondary" disabled={marking || unreadCount === 0} onClick={() => markRead()}>
            {t("notificationsPage.markAllRead")}
          </Button>
        </div>

        {loading ? (
          <div className={styles.statusMessage}>
            <span className={styles.spinner} />
            <p>{t("notificationsPage.loading")}</p>
          </div>
        ) : notifications.length > 0 ? (
          <>
            <div className={styles.notificationList}>
              {notifications.map((notification) => {
                const href = destination(notification, user?.roles?.[0]);
                return (
                  <article
                    key={notification.id}
                    className={styles.notificationItem}
                  >
                    <div className={styles.notificationIcon}>
                      <Bell size={21} />
                    </div>

                    <div className={styles.notificationContent}>
                      <h3>
                        {notification.title}
                        {!notification.read && <span className={styles.unread} aria-label={t("notificationsPage.unread")} />}
                      </h3>

                      <p>{notification.description}</p>

                      <span className={styles.date}>
                        {formatDate(notification.created_at)}
                      </span>
                      <div className={styles.itemActions}>
                        {!notification.read && (
                          <Button size="sm" variant="secondary" disabled={marking} onClick={() => markRead(notification.id)}>
                            {t("notificationsPage.markRead")}
                          </Button>
                        )}
                        {href && (
                          <Link to={href}>
                            {t("common:action.viewDetails")}
                          </Link>
                        )}
                      </div>
                    </div>
                  </article>
                );
              })}
            </div>

            <footer className={styles.pagination}>
              <button
                type="button"
                onClick={() => changePage(meta.current_page - 1)}
                disabled={meta.current_page <= 1}
                aria-label={t("notificationsPage.previous")}
              >
                {isArabic ? (
                  <ChevronRight size={18} />
                ) : (
                  <ChevronLeft size={18} />
                )}
                <span>{t("notificationsPage.previous")}</span>
              </button>

              <span className={styles.pageInfo}>
                {t("notificationsPage.pageInfo", {
                  current: meta.current_page,
                  total: meta.last_page,
                })}
              </span>

              <button
                type="button"
                onClick={() => changePage(meta.current_page + 1)}
                disabled={meta.current_page >= meta.last_page}
                aria-label={t("notificationsPage.next")}
              >
                <span>{t("notificationsPage.next")}</span>
                {isArabic ? (
                  <ChevronLeft size={18} />
                ) : (
                  <ChevronRight size={18} />
                )}
              </button>
            </footer>
          </>
        ) : !error && (
          <div className={styles.statusMessage}>
            <Bell size={35} />
            <h3>{t("notificationsPage.emptyTitle")}</h3>
            <p>{t("notificationsPage.emptyDescription")}</p>
          </div>
        )}
      </section>
    </main>
  );
}
