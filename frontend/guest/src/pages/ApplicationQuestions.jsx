import { useTranslation } from "react-i18next";
import styles from "./Application.module.css";

export function collectAnswers(questions, values, t) {
  const entries = [];
  const errors = {};

  for (const { id, type, required } of questions) {
    const input = values[id];
    const selected = type === "single_choice" ? (input ? [input] : []) : input ?? [];
    const choice = type === "single_choice" || type === "multiple_choice";
    const value = choice ? selected : (input ?? "").trim();

    if (value.length === 0) {
      if (required) errors[id] = t("applicationQuestions.required");
      continue;
    }
    if (type === "text" && [...value].length > 100) {
      errors[id] = t("applicationQuestions.textLength");
      continue;
    }
    const key = choice ? "option_ids" : type === "text" ? "text_value" : "numeric_value";
    entries.push({ question_id: id, [key]: value });
  }

  return { entries, errors };
}

export function questionErrors(errors, entries) {
  const byQuestion = {};

  for (const [key, messages] of Object.entries(errors)) {
    const [scope, id] = key.split(".");
    if (scope === "questions") byQuestion[id] ??= messages[0];
    if (scope === "answers" && entries[id]) byQuestion[entries[id].question_id] ??= messages[0];
  }

  return byQuestion;
}

export default function ApplicationQuestions({ questions, values, errors, onChange, disabled }) {
  const { t } = useTranslation();

  return (
    <section className={styles.extraQuestions} aria-labelledby="extra-questions-heading">
      <h2 id="extra-questions-heading">{t("applicationQuestions.title")}</h2>
      {questions.map((question) => {
        const { id, type, required, prompt, options, min_value: min, max_value: max } = question;
        const inputId = `application-question-${id}`;
        const error = errors[id];
        const bounds = [
          min !== null && t("applicationQuestions.min", { value: min }),
          max !== null && t("applicationQuestions.max", { value: max }),
        ].filter(Boolean);
        const describedBy = [error && `${inputId}-error`, bounds.length > 0 && `${inputId}-bounds`].filter(Boolean).join(" ");
        const label = <>{prompt}{required && <em aria-hidden="true"> *</em>}</>;
        const inputProps = { id: inputId, disabled, required, "aria-invalid": !!error, "aria-describedby": describedBy || undefined };
        const currentValue = values[id];
        const change = (event) => onChange(id, event.target.value);

        return (
          <div className={type === "text" ? styles.fullField : styles.field} key={id}>
            {type === "multiple_choice" ? (
              <fieldset className={styles.extraOptions} disabled={disabled} aria-describedby={describedBy || undefined}>
                <legend>{label}{required && <span className={styles.visuallyHidden}> {t("applicationQuestions.required")}</span>}</legend>
                {options.map((option) => (
                  <label key={option.id}>
                    <input
                      type="checkbox"
                      checked={currentValue?.includes(option.id) ?? false}
                      onChange={(event) => {
                        const ids = currentValue ?? [];
                        onChange(id, event.target.checked ? [...ids, option.id] : ids.filter((value) => value !== option.id));
                      }}
                    />
                    <span dir="auto">{option.label}</span>
                  </label>
                ))}
              </fieldset>
            ) : (
              <>
                <label htmlFor={inputId} dir="auto">{label}</label>
                {type === "text" && <textarea {...inputProps} rows={3} value={currentValue ?? ""} onChange={change} />}
                {type === "number" && <input {...inputProps} type="text" inputMode="decimal" dir="ltr" value={currentValue ?? ""} onChange={change} />}
                {type === "single_choice" && (
                  <select {...inputProps} value={currentValue ?? ""} onChange={(event) => onChange(id, Number(event.target.value) || "")}>
                    <option value="">{t("applicationQuestions.select")}</option>
                    {options.map((option) => <option key={option.id} value={option.id}>{option.label}</option>)}
                  </select>
                )}
              </>
            )}
            {bounds.length > 0 && <small id={`${inputId}-bounds`}>{bounds.join(" · ")}</small>}
            {error && <span id={`${inputId}-error`} className={styles.errorText}>{error}</span>}
          </div>
        );
      })}
    </section>
  );
}
