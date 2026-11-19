import { Clock } from "lucide-react";
import { useTranslation } from "react-i18next";
import Badge from "../../../../components/ui/Badge";
import Card from "../../../../components/ui/Card";
import styles from "./AnnouncementPage.module.css";
import useAnnouncements from "../../../../hooks/useAnnouncements.js";
import Loading from "../../../../components/ui/Loading.jsx";
import PageHeader from "../../../../components/ui/PageHeader";

import { getAnnouncementsTraineeApi } from "../../../../Api/Trainee/announcementApi";

export default function AnnouncementsPage() {
  const { t } = useTranslation("hr");
  const { announcements, loading } = useAnnouncements(getAnnouncementsTraineeApi);

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
              <Badge>{a.publisher.name}</Badge>
            </div>
            <p className={styles.details}>{a.description}</p>
            <span className={styles.date}>
              <Clock size={14} /> {a.announced_at}
            </span>
          </Card>
        ))}
      </div>
    </>
  );
}
