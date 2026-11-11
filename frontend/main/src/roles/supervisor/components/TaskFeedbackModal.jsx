
import { useState } from "react";
import { useTranslation } from "react-i18next";

import Modal from "../../../components/ui/Modal";
import Button from "../../../components/ui/Button";
import { Field, Textarea } from "../../../components/ui/Field";

import styles from "./TaskFeedbackModal.module.css";

export default function TaskFeedbackModal({
  action,
  onClose,
  onSubmit,
  loading = false,
}) {
  const { t } = useTranslation();

  const [feedback, setFeedback] = useState("");
  const [error, setError] = useState("");

  const isEvaluation = action === "evaluate";

  const title = isEvaluation
    ? t("task.evaluate", "تقييم المهمة")
    : t("task.requestRevision", "طلب إصلاح المهمة");

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");

    const value = feedback.trim();

    if (!value) {
      setError(
        t("task.feedbackRequired", "يرجى كتابة الملاحظات أولاً")
      );
      return;
    }

    try {
      await onSubmit(value);
    } catch (err) {
      setError(
        err?.response?.data?.message ||
          err?.message ||
          t("task.feedbackError", "حدث خطأ، يرجى المحاولة مجدداً")
      );
    }
  };

  return (
    <Modal title={title} onClose={onClose}>
      <form className={styles.form} onSubmit={handleSubmit}>
        <Field label={t("task.feedback", "الملاحظات")}>
          <Textarea
            value={feedback}
            onChange={(e) => setFeedback(e.target.value)}
            placeholder={
              isEvaluation
                ? t(
                    "task.evaluationPlaceholder",
                    "اكتب تقييمك وملاحظاتك حول أداء المتدرب..."
                  )
                : t(
                    "task.revisionPlaceholder",
                    "حدد التعديلات المطلوبة من المتدرب..."
                  )
            }
            rows={5}
            maxLength={3000}
            required
            disabled={loading}
          />
        </Field>

        {error && (
          <p className={styles.error} role="alert">
            {Array.isArray(error) ? error.join(", ") : error}
          </p>
        )}

        <div className={styles.actions}>
          <Button
            variant="secondary"
            onClick={onClose}
            disabled={loading}
          >
            {t("action.cancel", "إلغاء")}
          </Button>

          <Button
            type="submit"
            variant="primary"
            loading={loading}
          >
            {isEvaluation
              ? t("task.confirmEvaluation", "تأكيد التقييم")
              : t("task.confirmRevision", "إرسال طلب الإصلاح")}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
