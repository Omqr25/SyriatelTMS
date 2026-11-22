import { useId, useRef, useState } from "react";
import { useTranslation } from "react-i18next";
import { Archive, ArchiveRestore, CalendarDays, CircleAlert, Pencil, Plus, School, Trash2 } from "lucide-react";
import { toast } from "react-toastify";
import Badge from "../../../components/ui/Badge";
import Button from "../../../components/ui/Button";
import Card from "../../../components/ui/Card";
import { Field, Input, Select } from "../../../components/ui/Field";
import fieldStyles from "../../../components/ui/Field.module.css";
import form from "../../../components/ui/Form.module.css";
import Loading from "../../../components/ui/Loading";
import Modal from "../../../components/ui/Modal";
import PageHeader from "../../../components/ui/PageHeader";
import ApplicationFormEditor from "../components/ApplicationFormEditor";
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

const dateFormatter = new Intl.DateTimeFormat("en-AU", {
  day: "numeric",
  month: "numeric",
  year: "2-digit",
  timeZone: "UTC",
});

function formatDate(isoDate) {
  return isoDate ? dateFormatter.format(new Date(`${isoDate}T00:00:00Z`)) : "";
}

function parseDate(value) {
  const text = value.trim();
  const short = /^(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})$/.exec(text);
  let isoDate = text;
  if (short) {
    const [, day, month, year] = short;
    isoDate = `${year.length === 2 ? `20${year}` : year}-${month.padStart(2, "0")}-${day.padStart(2, "0")}`;
  }
  if (!/^[1-9]\d{3}-\d{2}-\d{2}$/.test(isoDate)) return "";
  const date = new Date(`${isoDate}T00:00:00Z`);
  return Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== isoDate ? "" : isoDate;
}

function ErrorMessage({ id, children }) {
  return (
    <p id={id} className={styles.error} role="alert">
      <CircleAlert size={16} aria-hidden="true" />
      <span>{children}</span>
    </p>
  );
}

function ProgramDateField({ name, label, value, onChange, disabled, error }) {
  const { t } = useTranslation("hr");
  const id = useId();
  const picker = useRef(null);
  const isoDate = parseDate(value);
  const hasPicker = typeof HTMLInputElement.prototype.showPicker === "function";

  return (
    <div className={fieldStyles.field}>
      <label className={fieldStyles.label} htmlFor={id}>{label}</label>
      <div className={styles.dateControl} dir="ltr">
        <Input id={id} name={name} value={isoDate === value ? formatDate(isoDate) : value}
          placeholder="D/M/YY" required disabled={disabled} aria-invalid={!!error}
          aria-describedby={error ? `${id}-error` : undefined}
          onChange={(event) => onChange(event.target.value)}
          onBlur={() => { if (isoDate && isoDate !== value) onChange(isoDate); }} />
        {hasPicker && (
          <>
            <input ref={picker} className={styles.nativeDate} type="date" value={isoDate}
              tabIndex={-1} aria-hidden="true" disabled={disabled}
              onChange={(event) => onChange(event.target.value)} />
            <button type="button" className={styles.calendarButton} disabled={disabled}
              aria-label={t("registration.openCalendar", { field: label })}
              onClick={() => picker.current.showPicker()}>
              <CalendarDays size={17} aria-hidden="true" />
            </button>
          </>
        )}
      </div>
      {error && <ErrorMessage id={`${id}-error`}>{error}</ErrorMessage>}
    </div>
  );
}

function validateDates(fields, t) {
  const errors = {};
  for (const key of Object.keys(dateLabels)) {
    if (!fields[key]) errors[key] = t("registration.invalidDate");
  }
  if (fields.application_end_date && fields.application_start_date && fields.application_end_date < fields.application_start_date) {
    errors.application_end_date = t("registration.registrationOrder");
  }
  if (fields.start_date && fields.application_end_date && fields.start_date <= fields.application_end_date) {
    errors.start_date = t("registration.trainingAfterRegistration");
  }
  if (fields.end_date && fields.start_date && fields.end_date < fields.start_date) {
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
  const [saveError, setSaveError] = useState(null);
  const [confirmed, setConfirmed] = useState(false);
  const [hasApplicants, setHasApplicants] = useState(program?.has_applicants);
  const [saving, setSaving] = useState(false);
  const submitting = useRef(false);
  const dates = Object.fromEntries(Object.keys(dateLabels).map((key) => [key, parseDate(fields[key])]));
  const changedDates = program && Object.keys(dateLabels).some((key) => dates[key] !== program[key]);
  const needsConfirmation = hasApplicants && changedDates;

  function change(key, value) {
    setFields((prev) => ({ ...prev, [key]: value }));
    setErrors({});
    setSaveError(null);
    if (key !== "name" && parseDate(value) !== dates[key]) setConfirmed(false);
  }

  async function submit(event) {
    event.preventDefault();
    if (submitting.current || (needsConfirmation && !confirmed)) return;
    setSaveError(null);
    const draft = !program && event.nativeEvent.submitter?.value === "draft";
    const validation = validateDates(dates, t);
    if (!fields.name.trim()) validation.name = t("registration.nameRequired");
    setErrors(validation);
    if (Object.keys(validation).length) return;

    submitting.current = true;
    setSaving(true);
    try {
      const payload = { ...dates, name: fields.name.trim() };
      if (draft) payload.draft = true;
      if (program) {
        payload.expected_revision = program.revision;
        payload.confirm_date_change = !!(needsConfirmation && confirmed);
      }
      await onSave(program, payload);
      toast.success(t(program ? "registration.updated" : draft ? "registration.draftSaved" : "registration.created"));
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
        const fieldErrors = Object.fromEntries(
          Object.entries(body?.errors ?? {}).filter(([key]) => key in fields).map(([key, values]) => [key, values[0]])
        );
        setErrors(fieldErrors);
        if (!Object.keys(fieldErrors).length) setSaveError(body?.message || t("registration.saveFailed"));
      }
    } finally {
      submitting.current = false;
      setSaving(false);
    }
  }

  return (
    <Modal title={t(program ? "registration.edit" : "registration.add")} onClose={() => !submitting.current && onClose()}>
      <form className={`${form.form} ${styles.editor}`} onSubmit={submit} noValidate>
        <div className={fieldStyles.field}>
          <Field label={t("registration.name")}>
            <Input name="name" value={fields.name} maxLength={program ? 255 : 30} required
              disabled={saving} aria-invalid={!!errors.name}
              aria-describedby={errors.name ? "registration-name-error" : undefined}
              onChange={(event) => change("name", event.target.value)} />
          </Field>
          {errors.name && <ErrorMessage id="registration-name-error">{errors.name}</ErrorMessage>}
        </div>
        {Object.entries(dateLabels).map(([key, label]) => (
          <ProgramDateField key={key} name={key} label={t(`registration.${label}`)}
            value={fields[key]} onChange={(value) => change(key, value)}
            disabled={saving} error={errors[key]} />
        ))}
        {needsConfirmation && (
          <label className={styles.warning}>
            <input type="checkbox" checked={confirmed} disabled={saving}
              onChange={(event) => setConfirmed(event.target.checked)} />
            {t("registration.confirmDates")}
          </label>
        )}
        {saveError && <ErrorMessage>{saveError}</ErrorMessage>}
        <Button type="submit" loading={saving} disabled={needsConfirmation && !confirmed}>
          {t(program ? "common:action.save" : "registration.publishNow")}
        </Button>
        {!program && <Button variant="secondary" type="submit" value="draft" disabled={saving}>{t("registration.saveDraft")}</Button>}
      </form>
    </Modal>
  );
}

export default function Registration() {
  const { t } = useTranslation("hr");
  const { programs, canManage, canArchive, loading, error, reload, save, remove, setArchived } = useRegistration();
  const [editor, setEditor] = useState(null);
  const [formProgram, setFormProgram] = useState(null);
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
      else {
        await setArchived(program, type === "archive");
        setFilter(type === "archive" ? "archived" : "current");
      }
      const success = { delete: "deleted", archive: "archived", restore: "restored" };
      toast.success(t(`registration.${success[type]}`));
      setAction(null);
    } catch (err) {
      const body = err.response?.data;
      const reason = body?.data?.reason;
      const message = ["opened", "linked", "unknown_history", "archived", "published_form"].includes(reason)
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
                <p className={styles.value} dir="ltr">{formatDate(program[key])}</p>
              </div>
            ))}
            {canManage && (
              <>
                <div className={styles.flex}>
                  <Button size="sm" variant="secondary" icon={Pencil} onClick={() => setEditor({ program })}>{t("registration.edit")}</Button>
                  <Button size="sm" variant="secondary" onClick={() => setFormProgram(program)}>
                    {t("applicationForm.manage")}
                  </Button>
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
          setFilter(fields.draft ? "archived" : "current");
        }
      }} reload={reload} />}
      {formProgram && (
        <ApplicationFormEditor
          key={formProgram.id}
          program={formProgram}
          onClose={() => setFormProgram(null)}
          onPublished={() => {
            setFormProgram(null);
            toast.success(t("applicationForm.publishedNotice"));
            reload();
          }}
        />
      )}
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
