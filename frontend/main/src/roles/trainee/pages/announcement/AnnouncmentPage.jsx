import { useCallback } from "react";
import { Clock } from "lucide-react";
import { useTranslation } from "react-i18next";
import Badge from "../../../../components/ui/Badge";
import Card from "../../../../components/ui/Card";
import styles from "./AnnouncementPage.module.css";
import useAnnouncements from "../../../../hooks/useAnnouncements.js";
import Loading from "../../../../components/ui/Loading.jsx";
import PageHeader from "../../../../components/ui/PageHeader";

import { useNotifications } from "../../../../context/NotificationContext";
import { getAnnouncementsTraineeApi } from "../../../../Api/Trainee/announcementApi";

export default function AnnouncementsPage() {
  const { t, i18n } = useTranslation("hr");
  const dateFormat = new Intl.DateTimeFormat(i18n.language, {
    dateStyle: "medium",
    timeStyle: "short",
  });
  const { refresh, announcementRevision } = useNotifications();
  const loadAnnouncements = useCallback(async () => {
    const result = await getAnnouncementsTraineeApi();
    refresh();
    return result;
  }, [refresh]);
  const { announcements, loading } = useAnnouncements(loadAnnouncements, undefined, announcementRevision);

  return loading ? (
    <Loading />
  ) : (
    <>
      <PageHeader title={t("announcements.listTitle")} />
      <div className={styles.list}>
        {announcements.map((a) => (
          <Card key={a.id}>
            <div className={styles.top}>
              <h4>{a.title}</h4>
              <Badge>{a.publisher?.name}</Badge>
            </div>
            <p className={styles.details}>{a.description}</p>
            <div className={styles.dates}>
              <span className={styles.date}>
                <Clock size={14} aria-hidden="true" /> {t("announcements.published")}
                <time dateTime={a.created_at}>
                  {a.created_at ? dateFormat.format(new Date(a.created_at)) : a.announced_at}
                </time>
              </span>
              {a.updated_at && a.updated_at !== a.created_at && (
                <span className={styles.date}>
                  {t("announcements.updated")}
                  <time dateTime={a.updated_at}>{dateFormat.format(new Date(a.updated_at))}</time>
                </span>
              )}
            </div>
          </Card>
        ))}
      </div>
    </>
  );
}
