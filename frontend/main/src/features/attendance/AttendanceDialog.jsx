import { useTranslation } from "react-i18next";
import Button from "../../components/ui/Button";
import { Field, Input, Select, Textarea } from "../../components/ui/Field";
import Modal from "../../components/ui/Modal";
import form from "../../components/ui/Form.module.css";
import { AttendanceError } from "./AttendanceTables";
import useAttendanceData from "./useAttendanceData";
import styles from "./AttendancePage.module.css";

export default function AttendanceDialog({ dialog, close, submit, busy, bounds }) {
  const { t } = useTranslation("attendance");
  const { action, row } = dialog;
  const participants = useAttendanceData(action === "recordAbsence" ? "absence/eligible-trainees" : null);
  const absence = action === "recordAbsence" || action === "editAbsence";
  const leave = action === "requestLeave" || action === "editLeave";
  const confirmation = !absence && !leave;
  const canRecord = action !== "recordAbsence" || Boolean(participants.result?.data.length);

  const onSubmit = (event) => {
    event.preventDefault();
    const fields = Object.fromEntries(new FormData(event.currentTarget));
    const id = row?.id ?? fields.trainee;
    delete fields.trainee;
    submit(action, id, fields);
  };

  return (
    <Modal title={t(`action.${action}`)} onClose={() => { if (!busy) close(); }}>
      <form className={form.form} onSubmit={onSubmit}>
        {confirmation && <p>{t(`confirmation.${action}`)}</p>}
        {action === "recordAbsence" && (
          <Field label={t("field.name")}>
            <Select name="trainee" required disabled={!canRecord} defaultValue="">
              <option value="" disabled>{t(participants.pending ? "loading" : canRecord ? "selectTrainee" : "noEligibleTrainees")}</option>
              {participants.result?.data.map((person) => <option key={person.id} value={person.id}>{person.name}</option>)}
            </Select>
          </Field>
        )}
        {participants.error && <AttendanceError reload={participants.reload} />}
        {!confirmation && (
          <Field label={t("field.reason")}>
            <Textarea name="reason" defaultValue={row?.reason ?? ""} required maxLength={action === "editLeave" ? 2000 : 255} />
          </Field>
        )}
        {leave && (
          <div className={styles.dates}>
            {["start_date", "end_date"].map((key) => (
              <Field key={key} label={t(`field.${key}`)}>
                <Input name={key} type="date" defaultValue={row?.[key]} min={bounds?.earliest_leave_date} max={bounds?.latest_leave_date} required />
              </Field>
            ))}
          </div>
        )}
        <div className={styles.dialogActions}>
          <Button variant="secondary" disabled={busy} onClick={close}>{t("action.close", { ns: "common" })}</Button>
          <Button type="submit" loading={busy} disabled={!canRecord}>
            {confirmation ? t(`action.${action}`) : t("action.save", { ns: "common" })}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
