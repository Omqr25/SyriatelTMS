
import { useEffect, useState } from "react";
import { Save } from "lucide-react";
import { useTranslation } from "react-i18next";

import Modal from "../../../components/ui/Modal";
import Button from "../../../components/ui/Button";
import {
  Field,
  Input,
  Textarea,
} from "../../../components/ui/Field";

import styles from "./EvaluationModal.module.css";
import { toast } from "react-toastify";

const INDICATORS = [
  { name: "Task performance", key: "taskPerformance" },
  { name: "Attendance", key: "attendance" },
  { name: "Progress", key: "progress" },
];

const createInitialForm = (evaluation) => ({
  description: evaluation?.description ?? "",
  indicators: INDICATORS.map(({ name }) => {
    const existing = evaluation?.indicators?.find(
      (item) => item.name === name
    );

    return {
      name,
      score: existing?.score ?? "",
      notice: existing?.notice ?? "",
    };
  }),
});

export default function EvaluationModal({
  isOpen=true,
  onClose,
  onSubmit,
  evaluation = null,
  loading = false,
  trId,
}) {
  const { t } = useTranslation("hr");

  const [form, setForm] = useState(() =>
    createInitialForm(evaluation)
  );
  const [errors, setErrors] = useState({});

  const isEditMode = Boolean(evaluation);

  useEffect(() => {
    if (!isOpen) return;

    setForm(createInitialForm(evaluation));
    setErrors({});
  }, [isOpen, evaluation]);

  const handleDescriptionChange = (event) => {
    setForm((prev) => ({
      ...prev,
      description: event.target.value,
    }));

    setErrors((prev) => ({
      ...prev,
      description: "",
    }));
  };

  const handleIndicatorChange = (index, field, value) => {
    setForm((prev) => ({
      ...prev,
      indicators: prev.indicators.map((indicator, i) =>
        i === index
          ? { ...indicator, [field]: value }
          : indicator
      ),
    }));

    setErrors((prev) => ({
      ...prev,
      [`${index}.${field}`]: "",
    }));
  };

  const validateForm = () => {
    const nextErrors = {};

    if (!form.description.trim()) {
      nextErrors.description = t(
        "evaluation.errors.description"
      );
    }

    form.indicators.forEach((indicator, index) => {
      const score = indicator.score;

      if (
        score === "" ||
        !Number.isFinite(Number(score)) ||
        Number(score) < 0 ||
        Number(score) > 100
      ) {
        nextErrors[`${index}.score`] = t(
          "evaluation.errors.score"
        );
      }

      if (!indicator.notice.trim()) {
        nextErrors[`${index}.notice`] = t(
          "evaluation.errors.notice"
        );
      }
    });

    setErrors(nextErrors);

    return Object.keys(nextErrors).length === 0;
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (loading || !validateForm()) return;

    const payload = {
      description: form.description.trim(),
      indicators: form.indicators.map((indicator) => ({
        name: indicator.name,
        score: Number(indicator.score),
        notice: indicator.notice.trim(),
      })),
    };

    console.log("payload",payload)

    try {
            await onSubmit(trId,payload);
             onClose()
    } catch (error) {
        const message =
              error.response?.data?.message || error.message || "error in interview";
            toast.error(Array.isArray(message) ? message.join("، ") : message);
    }
    
  };

  if (!isOpen) return null;

  return (
    <Modal
      title={t(
        isEditMode
          ? "evaluation.editTitle"
          : "evaluation.createTitle"
      )}
      onClose={onClose}
    >
      <form
        className={styles.form}
        onSubmit={handleSubmit}
        noValidate
      >
        <div className={styles.intro}>
          <p>{t("evaluation.subtitle")}</p>
        </div>

        <div className={styles.descriptionField}>
          <Field label={t("evaluation.description")}>
            <Textarea
              name="description"
              value={form.description}
              onChange={handleDescriptionChange}
              placeholder={t(
                "evaluation.descriptionPlaceholder"
              )}
              maxLength={1000}
              required
              aria-invalid={Boolean(errors.description)}
            />
          </Field>

          {errors.description && (
            <p className={styles.error}>
              {errors.description}
            </p>
          )}
        </div>

        <div className={styles.sectionHeader}>
          <h4>{t("evaluation.indicatorsTitle")}</h4>
          <span>{t("evaluation.scoreRange")}</span>
        </div>

        <div className={styles.indicators}>
          {form.indicators.map((indicator, index) => {
            const indicatorKey = INDICATORS[index].key;

            return (
              <section
                key={indicator.name}
                className={styles.indicator}
              >
                <div className={styles.indicatorTitle}>
                  <span className={styles.number}>
                    {index + 1}
                  </span>

                  <h5>
                    {t(
                      `evaluation.indicators.${indicatorKey}`
                    )}
                  </h5>
                </div>

                <div className={styles.scoreField}>
                  <Field label={t("evaluation.score")}>
                    <Input
                      name={`score-${index}`}
                      type="number"
                      min="0"
                      max="100"
                      step="1"
                      value={indicator.score}
                      onChange={(event) =>
                        handleIndicatorChange(
                          index,
                          "score",
                          event.target.value
                        )
                      }
                      placeholder="0 - 100"
                      required
                      aria-invalid={Boolean(
                        errors[`${index}.score`]
                      )}
                    />
                  </Field>

                  <span className={styles.scoreSuffix}>
                    / 100
                  </span>
                </div>

                {errors[`${index}.score`] && (
                  <p className={styles.error}>
                    {errors[`${index}.score`]}
                  </p>
                )}

                <div className={styles.noticeField}>
                  <Field label={t("evaluation.notice")}>
                    <Textarea
                      name={`notice-${index}`}
                      value={indicator.notice}
                      onChange={(event) =>
                        handleIndicatorChange(
                          index,
                          "notice",
                          event.target.value
                        )
                      }
                      placeholder={t(
                        "evaluation.noticePlaceholder"
                      )}
                      maxLength={500}
                      required
                      aria-invalid={Boolean(
                        errors[`${index}.notice`]
                      )}
                    />
                  </Field>

                  {errors[`${index}.notice`] && (
                    <p className={styles.error}>
                      {errors[`${index}.notice`]}
                    </p>
                  )}
                </div>
              </section>
            );
          })}
        </div>

        <div className={styles.footer}>
          <Button
            type="button"
            variant="secondary"
            onClick={onClose}
            disabled={loading}
          >
            {t("evaluation.cancel")}
          </Button>

          <Button
            type="submit"
            loading={loading}
            icon={Save}
          >
            {t(
              isEditMode
                ? "evaluation.saveChanges"
                : "evaluation.create"
            )}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
