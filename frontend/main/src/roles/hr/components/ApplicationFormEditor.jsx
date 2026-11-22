import { useEffect, useRef, useState } from "react";
import { useTranslation } from "react-i18next";
import { getApplicationForm, publishApplicationForm, saveApplicationForm } from "../../../Api/HrApi/registrationApi";
import Button from "../../../components/ui/Button";
import { Field, Input, Select } from "../../../components/ui/Field";
import Modal from "../../../components/ui/Modal";
import styles from "./ApplicationFormEditor.module.css";

const types = ["text", "number", "single_choice", "multiple_choice"];
const isChoice = (type) => type === "single_choice" || type === "multiple_choice";

function move(items, index, offset) {
  const reordered = [...items];
  [reordered[index], reordered[index + offset]] = [reordered[index + offset], reordered[index]];
  return reordered;
}

function OrderButtons({ index, count, onMove, t }) {
  return (
    <>
      <Button type="button" size="sm" variant="secondary" disabled={index === 0} onClick={() => onMove(-1)}>
        {t("applicationForm.moveUp")}
      </Button>
      <Button type="button" size="sm" variant="secondary" disabled={index === count - 1} onClick={() => onMove(1)}>
        {t("applicationForm.moveDown")}
      </Button>
    </>
  );
}

export default function ApplicationFormEditor({ program, onClose, onPublished }) {
  const { t, i18n } = useTranslation("hr");
  const [info, setInfo] = useState(null);
  const [questions, setQuestions] = useState([]);
  const [failure, setFailure] = useState(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [dirty, setDirty] = useState(false);
  const [confirmedUnopened, setConfirmedUnopened] = useState(false);
  const [confirming, setConfirming] = useState(false);
  const [reload, setReload] = useState(0);
  const nextKey = useRef(0);
  const submitting = useRef(false);

  function applyResponse(data) {
    setInfo(data);
    setQuestions((data.form?.questions ?? []).map((question) => ({
      ...question,
      key: nextKey.current++,
      options: question.options.map((option) => ({ ...option, key: nextKey.current++ })),
    })));
    setDirty(false);
  }

  useEffect(() => {
    const controller = new AbortController();
    setLoading(true);
    setFailure(null);
    getApplicationForm(program.id, controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) applyResponse(data);
      })
      .catch(() => {
        if (!controller.signal.aborted) setFailure({ load: true });
      })
      .finally(() => {
        if (!controller.signal.aborted) setLoading(false);
      });
    return () => controller.abort();
  }, [program.id, reload]);

  function update(nextQuestions) {
    setQuestions(nextQuestions);
    setDirty(true);
    setFailure(null);
    setConfirming(false);
  }

  function updateQuestion(index, change) {
    update(questions.map((question, position) => position === index ? { ...question, ...change } : question));
  }

  async function persist(publish = false) {
    if (submitting.current) return;
    submitting.current = true;
    setBusy(true);
    setFailure(null);

    try {
      const locale = i18n.resolvedLanguage;
      const data = publish
        ? await publishApplicationForm(program.id, { expected_revision: info.revision, confirm_unopened: confirmedUnopened }, locale)
        : await saveApplicationForm(program.id, {
          expected_revision: info.revision,
          questions: questions.map(({ type, prompt, required, min_value, max_value, options }) => ({
            type, prompt, required,
            min_value: type === "number" ? min_value || null : null,
            max_value: type === "number" ? max_value || null : null,
            options: isChoice(type) ? options.map(({ label }) => label) : [],
          })),
        }, locale);
      applyResponse(data);
      setConfirming(false);
      if (publish) onPublished();
    } catch (err) {
      const body = err.response?.data;
      setFailure({ stale: err.response?.status === 409, errors: body?.errors, message: body?.message });
    } finally {
      submitting.current = false;
      setBusy(false);
    }
  }

  const errorMessages = Object.values(failure?.errors ?? {}).flat();
  const canEdit = info?.can_edit;

  return (
    <Modal title={t("applicationForm.title", { name: program.name })} onClose={() => !submitting.current && onClose()}>
      {loading && <p role="status">{t("applicationForm.loading")}</p>}
      {failure?.load && (
        <div role="alert">
          {t("applicationForm.loadFailed")} <Button onClick={() => setReload((count) => count + 1)}>{t("registration.retry")}</Button>
        </div>
      )}
      {!loading && !failure?.load && info && (
        <form className={styles.editor} onSubmit={(event) => {
          event.preventDefault();
          if (canEdit && dirty) persist();
        }}>
          {info.published_at && <p role="status">{t("applicationForm.published")}</p>}
          {!canEdit && !info.published_at && <p>{t("applicationForm.closed")}</p>}
          {questions.map((question, index) => (
            <fieldset className={styles.question} key={question.key} disabled={!canEdit || busy}>
              <legend>{t("applicationForm.question", { number: index + 1 })}</legend>
              <Field label={t("applicationForm.prompt")}>
                <Input value={question.prompt} maxLength={255} required onChange={(event) => updateQuestion(index, { prompt: event.target.value })} />
              </Field>
              <Field label={t("applicationForm.type")}>
                <Select value={question.type} onChange={(event) => updateQuestion(index, { type: event.target.value })}>
                  {types.map((type) => <option key={type} value={type}>{t(`applicationForm.types.${type}`)}</option>)}
                </Select>
              </Field>
              <label className={styles.checkbox}>
                <input type="checkbox" checked={question.required} onChange={(event) => updateQuestion(index, { required: event.target.checked })} />
                {t("applicationForm.required")}
              </label>
              {question.type === "number" && (
                <div className={styles.bounds}>
                  {["min_value", "max_value"].map((bound) => (
                    <Field key={bound} label={t(`applicationForm.${bound}`)}>
                      <Input dir="ltr" inputMode="decimal" value={question[bound] ?? ""} onChange={(event) => updateQuestion(index, { [bound]: event.target.value })} />
                    </Field>
                  ))}
                </div>
              )}
              {isChoice(question.type) && (
                <div className={styles.options}>
                  {question.options.map((option, optionIndex) => (
                    <div key={option.key} className={styles.option}>
                      <Field label={t("applicationForm.option", { number: optionIndex + 1 })}>
                        <Input value={option.label} maxLength={255} required onChange={(event) => updateQuestion(index, {
                          options: question.options.map((item) => item.key === option.key ? { ...item, label: event.target.value } : item),
                        })} />
                      </Field>
                      {canEdit && (
                        <div className={styles.actions}>
                          <OrderButtons index={optionIndex} count={question.options.length} t={t}
                            onMove={(offset) => updateQuestion(index, { options: move(question.options, optionIndex, offset) })} />
                          <Button type="button" size="sm" variant="secondary" onClick={() => updateQuestion(index, {
                            options: question.options.filter((item) => item.key !== option.key),
                          })}>{t("applicationForm.removeOption")}</Button>
                        </div>
                      )}
                    </div>
                  ))}
                  {canEdit && <Button type="button" size="sm" variant="secondary" onClick={() => updateQuestion(index, {
                    options: [...question.options, { key: nextKey.current++, label: "" }],
                  })}>{t("applicationForm.addOption")}</Button>}
                </div>
              )}
              {canEdit && (
                <div className={styles.actions}>
                  <OrderButtons index={index} count={questions.length} t={t} onMove={(offset) => update(move(questions, index, offset))} />
                  <Button type="button" size="sm" variant="secondary" onClick={() => update(questions.filter((item) => item.key !== question.key))}>
                    {t("applicationForm.removeQuestion")}
                  </Button>
                </div>
              )}
            </fieldset>
          ))}
          {failure && (
            <div role="alert">
              {errorMessages.length > 0
                ? <ul>{errorMessages.map((message, index) => <li key={index}>{message}</li>)}</ul>
                : <p>{failure.message || t("applicationForm.saveFailed")}</p>}
              {failure.stale && <Button type="button" onClick={() => setReload((count) => count + 1)}>{t("applicationForm.reload")}</Button>}
            </div>
          )}
          {canEdit && (
            <>
              <div className={styles.actions}>
                <Button type="button" variant="secondary" disabled={busy} onClick={() => update([
                  ...questions, { key: nextKey.current++, type: "text", prompt: "", required: false, options: [] },
                ])}>{t("applicationForm.addQuestion")}</Button>
                <Button type="submit" disabled={busy || !dirty}>{t("applicationForm.saveDraft")}</Button>
                <Button type="button" variant="dark" disabled={busy || dirty || info.revision === 0 || questions.length === 0}
                  onClick={() => setConfirming(true)}>{t("applicationForm.publish")}</Button>
              </div>
              {confirming && (
                <div className={styles.confirmation}>
                  <p>{t("applicationForm.confirmPublish")}</p>
                  {info.requires_history_confirmation && (
                    <label className={styles.checkbox}>
                      <input type="checkbox" checked={confirmedUnopened} disabled={busy}
                        onChange={(event) => setConfirmedUnopened(event.target.checked)} />
                      {t("applicationForm.confirmUnopened")}
                    </label>
                  )}
                  <Button type="button" variant="dark" disabled={busy || (info.requires_history_confirmation && !confirmedUnopened)}
                    onClick={() => persist(true)}>{t("applicationForm.confirmButton")}</Button>
                </div>
              )}
            </>
          )}
        </form>
      )}
    </Modal>
  );
}
