import { useEffect, useState } from "react";
import { Bell, ChevronLeft, ChevronRight, RefreshCw } from "lucide-react";
import { useTranslation } from "react-i18next";

import api from '../../Api/axios'
import styles from "./NotificationsPage.module.css";

export default function NotificationsPage() {
  const { t, i18n } = useTranslation("hr");

  const [notifications, setNotifications] = useState([]);
  const [reloadKey, setReloadKey] = useState(0);
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
    let isMounted = true;

    const fetchNotifications = async () => {
      setLoading(true);
      setError("");

      try {
        const response = await api.get(
          "/notification/myNotifications",
          {
            params: { page },
          }
        );

        const result = response.data;
        // console.log(result)
        if (!isMounted) return;

        setNotifications(result.data ?? []);
        setMeta(
          result.meta ?? {
            current_page: page,
            last_page: 1,
            total: 0,
            per_page: 10,
          }
        );
      } catch (err) {
        if (!isMounted) return;

        setError(
          err.response?.data?.message ||
            t("notificationsPage.loadError")
        );
      } finally {
        if (isMounted) {
          setLoading(false);
        }
      }
    };

    fetchNotifications();

    return () => {
      isMounted = false;
    };
  }, [page, reloadKey,t]);

  const formatDate = (date) => {
    if (!date) return "";

    const parsedDate = new Date(date.replace(" ", "T"));

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
      newPage === page
    ) {
      return;
    }

    setPage(newPage);
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

      {error && (
        <div className={styles.errorMessage} role="alert">
          <p>{error}</p>

          <button
            type="button"
            onClick={() => setPage((current) => current)}
          >
            <RefreshCw size={16} />
            {t("notificationsPage.retry")}
          </button>
        </div>
      )}

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
        </div>

        {loading ? (
          <div className={styles.statusMessage}>
            <span className={styles.spinner} />
            <p>{t("notificationsPage.loading")}</p>
          </div>
        ) : error ? null : notifications.length === 0 ? (
          <div className={styles.statusMessage}>
            <Bell size={35} />
            <h3>{t("notificationsPage.emptyTitle")}</h3>
            <p>{t("notificationsPage.emptyDescription")}</p>
          </div>
        ) : (
          <>
            <div className={styles.notificationList}>
              {notifications.map((notification) => (
                <article
                  key={notification.id}
                  className={styles.notificationItem}
                >
                  <div className={styles.notificationIcon}>
                    <Bell size={21} />
                  </div>

                  <div className={styles.notificationContent}>
                    <h3>{notification.title}</h3>

                    <p>{notification.description}</p>

                    <span className={styles.date}>
                      {formatDate(notification.created_at)}
                    </span>
                  </div>
                </article>
              ))}
            </div>

            <footer className={styles.pagination}>
              <button
                type="button"
                onClick={() => changePage(page - 1)}
                disabled={page <= 1}
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
                onClick={() => changePage(page + 1)}
                disabled={page >= meta.last_page}
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
        )}
      </section>
    </main>
  );
}
