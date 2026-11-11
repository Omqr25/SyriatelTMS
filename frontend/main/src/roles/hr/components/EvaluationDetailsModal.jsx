
import { CalendarDays, ClipboardCheck } from "lucide-react";
import { useTranslation } from "react-i18next";

import Modal from "../../../components/ui/Modal";
import Button from "../../../components/ui/Button";

import styles from "./EvaluationDetailsModal.module.css";

const INDICATOR_KEYS = {
  "Task performance": "taskPerformance",
  Attendance: "attendance",
  Progress: "progress",
};

export default function EvaluationDetailsModal({
  isOpen=true,
  onClose,
  evaluation,
}) {
  const { t } = useTranslation("hr");

  if (!isOpen) return null;

  const formatDate = (value) => {
    if (!value) return "—";

    const match = value.match(
      /^(\d{2})-(\d{2})-(\d{4})(?:\s+(\d{2}):(\d{2}))?$/
    );

    if (match) {
      const [, day, month, year, hours, minutes] = match;

      return hours
        ? `${day}/${month}/${year} ${hours}:${minutes}`
        : `${day}/${month}/${year}`;
    }

    return value;
  };

  const getScoreClass = (score) => {
    if (score >= 80) return styles.highScore;
    if (score >= 50) return styles.mediumScore;
    return styles.lowScore;
  };

  return (
    <Modal
      title={t("evaluation.evaluation")}
      onClose={onClose}
    >
      {!evaluation ? (
        <div className={styles.empty}>
          {t("evaluation.noEvaluation")}
        </div>
      ) : (
        <div className={styles.container}>
          <header className={styles.header}>
            <div className={styles.heading}>
              <span className={styles.icon}>
                <ClipboardCheck size={21} />
              </span>

              <div>
                <h4>{t("evaluation.indicatorsTitle")}</h4>
                <p>
                  {t("evaluation.subtitle")}
                </p>
              </div>
            </div>

            <div className={styles.date}>
              <CalendarDays size={16} />
              <span>
                {formatDate(evaluation.evaluation_date)}
              </span>
            </div>
          </header>

          <section className={styles.description}>
            <h5>{t("evaluation.description")}</h5>
            <p>
              {evaluation.description || "—"}
            </p>
          </section>

          <section className={styles.indicatorsSection}>
            <h4>{t("evaluation.indicatorsTitle")}</h4>

            <div className={styles.indicators}>
              {(evaluation.indicators || []).map(
                (indicator) => {
                  const key =
                    INDICATOR_KEYS[indicator.name];

                  const score = Number(indicator.score);
                  const validScore = Number.isFinite(score)
                    ? Math.min(100, Math.max(0, score))
                    : 0;

                  return (
                    <article
                      key={indicator.id ?? indicator.name}
                      className={styles.indicator}
                    >
                      <div className={styles.indicatorHeader}>
                        <h5>
                          {key
                            ? t(`evaluation.indicators.${key}`)
                            : indicator.name}
                        </h5>

                        <span
                          className={`${styles.score} ${getScoreClass(validScore)}`}
                        >
                          {indicator.score ?? "—"}
                          <small> / 100</small>
                        </span>
                      </div>

                      <div className={styles.progressTrack}>
                        <div
                          className={`${styles.progressBar} ${getScoreClass(validScore)}`}
                          style={{
                            width: `${validScore}%`,
                          }}
                        />
                      </div>

                      <div className={styles.notice}>
                        <span>
                          {t("evaluation.notice")}
                        </span>
                        <p>{indicator.notice || "—"}</p>
                      </div>
                    </article>
                  );
                }
              )}
            </div>
          </section>

          <footer className={styles.footer}>
            <Button
              type="button"
              onClick={onClose}
            >
              {t("action.close",{ns:"common"})}
            </Button>
          </footer>
        </div>
      )}
    </Modal>
  );
}
