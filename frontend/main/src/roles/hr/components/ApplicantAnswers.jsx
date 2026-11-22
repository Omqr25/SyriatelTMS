import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { getApplicantAnswers } from "../../../Api/HrApi/applicantsApi";
import styles from "./ApplicantDetailsModal.module.css";

export default function ApplicantAnswers({ applicationId }) {
  const { t } = useTranslation("hr");
  const [result, setResult] = useState(null);

  useEffect(() => {
    const controller = new AbortController();
    getApplicantAnswers(applicationId, controller.signal)
      .then((data) => {
        if (!controller.signal.aborted) setResult(data);
      })
      .catch(() => {
        if (!controller.signal.aborted) setResult({ failed: true });
      });
    return () => controller.abort();
  }, [applicationId]);

  if (!result) return <p role="status">{t("applicationForm.loadingAnswers")}</p>;
  if (result.failed) return <p role="alert">{t("applicationForm.answersFailed")}</p>;
  if (result.application_form_id === null) return null;

  return (
    <section aria-label={t("applicationForm.answersTitle")}>
      <h5 className={styles.sub}>{t("applicationForm.answersTitle")}</h5>
      {result.answers.length === 0
        ? <p>{t("applicationForm.noAnswers")}</p>
        : (
          <dl className={styles.grid}>
            {result.answers.map((answer) => (
              <div key={answer.question_id}>
                <dt dir="auto">{answer.prompt}</dt>
                <dd dir="auto">
                  {answer.text_value ?? answer.numeric_value ?? answer.options.map(({ label }) => label).join(t("applicationForm.optionSeparator"))}
                </dd>
              </div>
            ))}
          </dl>
        )}
    </section>
  );
}
