import { Clock, Megaphone } from "lucide-react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import Badge from "../../../components/ui/Badge";
import Button from "../../../components/ui/Button";
import Card from "../../../components/ui/Card";
import { Field, Input, Textarea } from "../../../components/ui/Field";
import form from "../../../components/ui/Form.module.css";
import Modal from "../../../components/ui/Modal";
import PageHeader from "../../../components/ui/PageHeader";

import styles from "./AnnouncementsPage.module.css";
import useAnnouncements from "../../../hooks/useAnnouncements.js";
import Loading from "../../../components/ui/Loading.jsx";
import { getAllAnnouncementsSupervisorApi, sendAnnouncementsSupervisorApi } from "../../../Api/SupervisorApi/supervisorApi";

export default function AnnouncementsPage() {
  const { t } = useTranslation("hr");
  const { announcements, loading, loadingCreate, sendAnnouncement } = useAnnouncements(
    getAllAnnouncementsSupervisorApi, sendAnnouncementsSupervisorApi,
  );
  const [showAdd, setShowAdd] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    const data = new FormData(e.currentTarget);
    const sent = await sendAnnouncement({
      title: data.get("title"),
      description: data.get("details"),
    });
    if (sent) setShowAdd(false);
  };

  return loading ? (
    <Loading />
  ) : (
    <>
      <PageHeader
        title={t("announcements.listTitle")}
        action={
          <Button icon={Megaphone} onClick={() => setShowAdd(true)}>
            {t("announcements.send")}
          </Button>
        }
      />

      <div className={styles.list}>
        {announcements.map((a) => (
          <Card key={a.id}>
            <div className={styles.top}>
              <h4>{a.title}</h4>
              <Badge>{t("announcements.assignedTrainees")}</Badge>
            </div>
            <p className={styles.details}>{a.description}</p>
            <span className={styles.date}>
              <Clock size={14} /> {a.announced_at}
            </span>
          </Card>
        ))}
      </div>

      {showAdd && (
        <Modal
          title={t("announcements.send")}
          onClose={() => setShowAdd(false)}
        >
          <form className={form.form} onSubmit={handleSubmit}>
            <Field label={t("announcements.title")}>
              <Input name="title" required maxLength={100} />
            </Field>
            <Field label={t("announcements.details")}>
              <Textarea name="details" required maxLength={2000} />
            </Field>
            <Button type="submit" loading={loadingCreate}>
              {t("action.save", { ns: "common" })}
            </Button>
          </form>
        </Modal>
      )}
    </>
  );
}
