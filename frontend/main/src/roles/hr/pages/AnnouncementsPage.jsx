import { Clock, Megaphone, Pencil, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import Badge from "../../../components/ui/Badge";
import Button from "../../../components/ui/Button";
import Card from "../../../components/ui/Card";
import { Field, Input, Select, Textarea } from "../../../components/ui/Field";
import form from "../../../components/ui/Form.module.css";
import Modal from "../../../components/ui/Modal";
import PageHeader from "../../../components/ui/PageHeader";
import Loading from "../../../components/ui/Loading";
import useAnnouncements from "../../../hooks/useAnnouncements";
import { getDepartmentsApi } from "../../../Api/HrApi/departmentsApi";
import {
  deleteAnnouncementApi, getAllAnnouncementsApi, sendAnnouncementsApi, updateAnnouncementApi,
} from "../../../Api/HrApi/hrApi";
import styles from "./AnnouncementsPage.module.css";

export default function AnnouncementsPage() {
  const { t, i18n } = useTranslation("hr");
  const { announcements, loading, loadingCreate, sendAnnouncement, mutateAnnouncement } = useAnnouncements(
    getAllAnnouncementsApi, sendAnnouncementsApi,
  );
  const [dialog, setDialog] = useState(null);
  const [departments, setDepartments] = useState([]);
  const [departmentsError, setDepartmentsError] = useState(false);
  const [validationError, setValidationError] = useState(false);
  const editing = dialog?.announcement;
  const allTrainees = t("common:audience.allTrainees");
  const dateFormat = new Intl.DateTimeFormat(i18n.language, {
    dateStyle: "short", timeStyle: "short", timeZone: "Asia/Damascus",
  });

  useEffect(() => {
    let active = true;
    getDepartmentsApi().then(({ data }) => {
      if (active) setDepartments(data);
    }).catch(() => {
      if (active) setDepartmentsError(true);
    });
    return () => { active = false; };
  }, []);

  function openDialog(type, announcement) {
    setValidationError(false);
    setDialog({ type, announcement });
  }

  function closeDialog() {
    if (!loadingCreate) setDialog(null);
  }

  async function handleSubmit(event) {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    const title = formData.get("title").trim();
    const description = formData.get("details").trim();
    if (!title || !description) {
      setValidationError(true);
      return;
    }
    setValidationError(false);
    const body = {
      title,
      description,
      department_id: formData.get("department_id") ? Number(formData.get("department_id")) : null,
    };
    const saved = editing
      ? await mutateAnnouncement(
        () => updateAnnouncementApi(editing.id, body),
        (items, updated) => items.map((item) => item.id === updated.id ? updated : item),
      )
      : await sendAnnouncement(body);
    if (saved) setDialog(null);
  }

  async function confirmDelete() {
    const deleted = await mutateAnnouncement(
      () => deleteAnnouncementApi(editing.id),
      (items) => items.filter((item) => item.id !== editing.id),
    );
    if (deleted) setDialog(null);
  }

  if (loading) return <Loading />;

  return (
    <>
      <PageHeader
        title={t("announcements.listTitle")}
        action={<Button icon={Megaphone} onClick={() => openDialog("create")}>{t("announcements.send")}</Button>}
      />
      <div className={styles.list}>
        {announcements.map((announcement) => (
          <Card key={announcement.id}>
            <div className={styles.top}>
              <h4>{announcement.title}</h4>
              <Badge>{announcement.department_name || allTrainees}</Badge>
            </div>
            <p className={styles.details}>{announcement.description}</p>
            <div className={styles.footer}>
              <div className={styles.dates}>
                <span className={styles.date}>
                  <Clock size={14} aria-hidden="true" /> {t("announcements.published")}
                  <time dateTime={announcement.created_at}>
                    {announcement.created_at ? dateFormat.format(new Date(announcement.created_at)) : announcement.announced_at}
                  </time>
                </span>
                {announcement.updated_at && announcement.updated_at !== announcement.created_at && (
                  <span className={styles.date}>
                    {t("announcements.updated")}
                    <time dateTime={announcement.updated_at}>{dateFormat.format(new Date(announcement.updated_at))}</time>
                  </span>
                )}
              </div>
              <div className={styles.actions}>
                <Button size="sm" variant="secondary" icon={Pencil}
                  onClick={() => openDialog("edit", announcement)}>{t("announcements.edit")}</Button>
                <Button size="sm" variant="soft" icon={Trash2}
                  onClick={() => openDialog("delete", announcement)}>{t("announcements.delete")}</Button>
              </div>
            </div>
          </Card>
        ))}
      </div>

      {dialog && dialog.type !== "delete" && (
        <Modal title={t(editing ? "announcements.edit" : "announcements.send")} onClose={closeDialog}>
          <form className={form.form} onSubmit={handleSubmit}>
            <Field label={t("announcements.title")}>
              <Input name="title" defaultValue={editing?.title} required maxLength={100} disabled={loadingCreate} />
            </Field>
            <Field label={t("announcements.details")}>
              <Textarea name="details" defaultValue={editing?.description} required maxLength={2000} disabled={loadingCreate} />
            </Field>
            <Field label={t("announcements.target")}>
              <Select name="department_id" defaultValue={editing?.department_id ?? ""} disabled={loadingCreate}>
                <option value="">{allTrainees}</option>
                {editing?.department_id && !departments.some(({ id }) => id === editing.department_id) && (
                  <option value={editing.department_id}>{editing.department_name}</option>
                )}
                {departments.map((department) => (
                  <option key={department.id} value={department.id}>{department.name}</option>
                ))}
              </Select>
            </Field>
            {departmentsError && <p role="alert" className={styles.error}>{t("announcements.departmentsError")}</p>}
            {validationError && <p role="alert" className={styles.error}>{t("announcements.required")}</p>}
            <div className={styles.actions}>
              <Button variant="secondary" onClick={closeDialog} disabled={loadingCreate}>{t("common:action.cancel")}</Button>
              <Button type="submit" loading={loadingCreate}>
                {t(editing ? "announcements.saveChanges" : "announcements.send")}
              </Button>
            </div>
          </form>
        </Modal>
      )}
      {dialog?.type === "delete" && (
        <Modal title={t("announcements.delete")} onClose={closeDialog}>
          <div className={form.form}>
            <p>{t("announcements.deleteWarning", { title: editing.title })}</p>
            <div className={styles.actions}>
              <Button variant="secondary" onClick={closeDialog} disabled={loadingCreate}>{t("common:action.cancel")}</Button>
              <Button icon={Trash2} loading={loadingCreate} onClick={confirmDelete}>{t("announcements.confirmDelete")}</Button>
            </div>
          </div>
        </Modal>
      )}
    </>
  );
}
