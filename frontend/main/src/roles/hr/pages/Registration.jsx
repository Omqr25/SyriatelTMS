import { useRef, useState } from "react";
import { useTranslation } from "react-i18next";
import { Archive, ArchiveRestore, Pencil, Plus, School, Trash2 } from "lucide-react";
import { toast } from "react-toastify";
import Badge from "../../../components/ui/Badge";
import Button from "../../../components/ui/Button";
import Card from "../../../components/ui/Card";
import { Field, Input, Select } from "../../../components/ui/Field";
import form from "../../../components/ui/Form.module.css";
import Loading from "../../../components/ui/Loading";
import Modal from "../../../components/ui/Modal";
import PageHeader from "../../../components/ui/PageHeader";
import useRegistration from "../../../hooks/HrHooks/useRegistration";
import styles from "./Registration.module.css";

const dateLabels = {
  start_date: "startDate",
  end_date: "endDate",
  application_start_date: "startDateRegistration",
  application_end_date: "endDateRegistration",
};

const phases = ["upcoming", "registration_open", "registration_closed", "in_progress", "completed", "archived"];

const phaseTones = { registration_open: "green", in_progress: "blue", upcoming: "amber" };

function validateDates(fields, t) {
  const errors = {};
  if (fields.application_end_date < fields.application_start_date) {
    errors.application_end_date = t("registration.registrationOrder");
  }
  if (fields.start_date <= fields.application_end_date) {
    errors.start_date = t("registration.trainingAfterRegistration");
  }
  if (fields.end_date < fields.start_date) {
    errors.end_date = t("registration.trainingOrder");
  }
  return errors;
}

function ProgramEditor({ program, onClose, onSave, reload }) {
  const { t } = useTranslation("hr");
  const [fields, setFields] = useState(() => Object.fromEntries(
    ["name", ...Object.keys(dateLabels)].map((key) => [key, program?.[key] ?? ""])
  ));
  const [errors, setErrors] = useState({});
  const [confirmed, setConfirmed] = useState(false);
  const [hasApplicants, setHasApplicants] = useState(program?.has_applicants);
  const [saving, setSaving] = useState(false);
  const submitting = useRef(false);
  const changedDates = program && Object.keys(dateLabels).some((key) => fields[key] !== program[key]);
  const needsConfirmation = hasApplicants && changedDates;

  function change(key, value) {
    setFields((prev) => ({ ...prev, [key]: value }));
    setErrors({});
    if (key !== "name") setConfirmed(false);
  }

  async function submit(event) {
    event.preventDefault();
    if (submitting.current || (needsConfirmation && !confirmed)) return;
    const validation = validateDates(fields, t);
    if (!fields.name.trim()) validation.name = t("registration.nameRequired");
    setErrors(validation);
    if (Object.keys(validation).length) return;

    submitting.current = true;
    setSaving(true);
    try {
      const payload = { ...fields, name: fields.name.trim() };
      if (program) {
        payload.expected_revision = program.revision;
        payload.confirm_date_change = !!(needsConfirmation && confirmed);
      }
      await onSave(program, payload);
      toast.success(t(program ? "registration.updated" : "registration.created"));
      onClose();
    } catch (err) {
      const body = err.response?.data;
      const reason = body?.data?.reason;
      if (reason === "confirm_dates") {
        setHasApplicants(true);
        setConfirmed(false);
      } else if (reason === "stale" || [403, 404].includes(err.response?.status)) {
        toast.error(reason === "stale" ? t("registration.stale") : body?.message);
        onClose();
        reload();
      } else {
        setErrors(Object.fromEntries(Object.entries(body?.errors ?? {}).map(([key, values]) => [key, values[0]])));
        toast.error(body?.message || t("registration.saveFailed"));
      }
    } finally {
      submitting.current = false;
      setSaving(false);
    }
  }

  return (
    <Modal title={t(program ? "registration.edit" : "registration.add")} onClose={() => !submitting.current && onClose()}>
      <form className={form.form} onSubmit={submit}>
        {Object.entries({ name: "name", ...dateLabels }).map(([key, label]) => (
          <Field key={key} label={t(`registration.${label}`)}>
            <Input name={key} type={key === "name" ? "text" : "date"} value={fields[key]}
              maxLength={key === "name" ? (program ? 255 : 30) : undefined}
              required disabled={saving} aria-invalid={!!errors[key]}
              aria-describedby={errors[key] ? `error-${key}` : undefined}
              onChange={(event) => change(key, event.target.value)} />
            {errors[key] && <span id={`error-${key}`} role="alert">{errors[key]}</span>}
          </Field>
        ))}
        {needsConfirmation && (
          <label className={styles.warning}>
            <input type="checkbox" checked={confirmed} disabled={saving}
              onChange={(event) => setConfirmed(event.target.checked)} />
            {t("registration.confirmDates")}
          </label>
        )}
        <Button type="submit" loading={saving} disabled={needsConfirmation && !confirmed}>
          {t(program ? "common:action.save" : "common:action.create")}
        </Button>
      </form>
    </Modal>
  );
}

export default function Registration() {
  const { t } = useTranslation("hr");
  const { programs, canManage, canArchive, loading, error, reload, save, remove, setArchived } = useRegistration();
  const [editor, setEditor] = useState(null);
  const [action, setAction] = useState(null);
  const [query, setQuery] = useState("");
  const [filter, setFilter] = useState("current");
  const [busy, setBusy] = useState(false);
  const pending = useRef(false);
  const search = query.trim().toLocaleLowerCase();
  const visiblePrograms = programs.filter((program) =>
    program.name.toLocaleLowerCase().includes(search) &&
    (filter === "all" || (filter === "current" ? !program.archived_at : program.phase === filter))
  );

  async function confirmAction() {
    if (pending.current) return;
    pending.current = true;
    setBusy(true);
    const { type, program } = action;
    try {
      if (type === "delete") await remove(program);
      else await setArchived(program, type === "archive");
      const success = { delete: "deleted", archive: "archived", restore: "restored" };
      toast.success(t(`registration.${success[type]}`));
      setAction(null);
    } catch (err) {
      const body = err.response?.data;
      const reason = body?.data?.reason;
      const message = ["opened", "linked", "unknown_history", "archived"].includes(reason)
        ? t(`registration.blocks.${reason}`) : body?.message;
      toast.error(message || t("registration.saveFailed"));
      if ([403, 404, 409].includes(err.response?.status)) {
        setAction(null);
        reload();
      }
    } finally {
      pending.current = false;
      setBusy(false);
    }
  }

  if (loading) return <Loading />;
  if (error) return <div role="alert">{t("registration.loadFailed")} <Button onClick={reload}>{t("registration.retry")}</Button></div>;

  return (
    <>
      <PageHeader title={t("registration.title")} action={
        <Button icon={Plus} onClick={() => setEditor({ program: null })}>{t("registration.add")}</Button>
      } />
      <div className={styles.filters}>
        <Field label={t("registration.search")}>
          <Input type="search" value={query} onChange={(event) => setQuery(event.target.value)} />
        </Field>
        <Field label={t("common:label.status")}>
          <Select value={filter} onChange={(event) => setFilter(event.target.value)}>
            <option value="current">{t("registration.current")}</option>
            <option value="all">{t("registration.all")}</option>
            {phases.map((phase) => <option key={phase} value={phase}>{t(`registration.phases.${phase}`)}</option>)}
          </Select>
        </Field>
      </div>
      {!visiblePrograms.length && <p role="status">{t("registration.noMatches")}</p>}
      <div className={styles.grid}>
        {visiblePrograms.map((program) => (
          <Card key={program.id} className={styles.card}>
            <div className={styles.flex}>
              <School color="#e60000" size={22} />
              <h3>{program.name}</h3>
              <Badge tone={phaseTones[program.phase]}>{t(`registration.phases.${program.phase}`)}</Badge>
            </div>
            {Object.entries(dateLabels).map(([key, label]) => (
              <div key={key} className={styles.flex}>
                <span className={styles.bold}>{t(`registration.${label}`)}:</span>
                <p className={styles.value}>{program[key]}</p>
              </div>
            ))}
            {canManage && (
              <>
                <div className={styles.flex}>
                  <Button size="sm" variant="secondary" icon={Pencil} onClick={() => setEditor({ program })}>{t("registration.edit")}</Button>
                  {canArchive && (
                    <Button size="sm" variant="secondary" icon={program.archived_at ? ArchiveRestore : Archive}
                      onClick={() => setAction({ type: program.archived_at ? "restore" : "archive", program })}>
                      {t(program.archived_at ? "registration.restore" : "registration.archive")}
                    </Button>
                  )}
                  <Button size="sm" variant="soft" icon={Trash2} disabled={!!program.delete_block}
                    aria-describedby={program.delete_block ? `blocked-${program.id}` : undefined}
                    onClick={() => setAction({ type: "delete", program })}>{t("registration.delete")}</Button>
                </div>
                {program.delete_block && <small className={styles.value} id={`blocked-${program.id}`}>{t(`registration.blocks.${program.delete_block}`)}</small>}
              </>
            )}
          </Card>
        ))}
      </div>
      {editor && <ProgramEditor program={editor.program} onClose={() => setEditor(null)} onSave={async (program, fields) => {
        await save(program, fields);
        if (!program) {
          setQuery("");
          setFilter("current");
        }
      }} reload={reload} />}
      {action && (
        <Modal title={t(`registration.${action.type}`)} onClose={() => !pending.current && setAction(null)}>
          <p>{t(`registration.confirmAction.${action.type}`, { name: action.program.name })}</p>
          <div className={styles.flex}>
            <Button variant="secondary" disabled={busy} onClick={() => setAction(null)}>{t("common:action.cancel")}</Button>
            <Button loading={busy} onClick={confirmAction}>{t(`registration.${action.type}`)}</Button>
          </div>
        </Modal>
      )}
    </>
  );
}
