import { useEffect, useState } from "react";
import { Plus, Trash2 } from "lucide-react";
import { useTranslation } from "react-i18next";

import Modal from "../../../components/ui/Modal";
import { Field, Input, Select, Textarea } from "../../../components/ui/Field";
import Button from "../../../components/ui/Button";

import styles from "./CreateTaskModal.module.css";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";
import {
  createTaskApi,
  updateTaskApi,
} from "../../../Api/SupervisorApi/supervisorApi";
import { toast } from "react-toastify";

const initialForm = {
  name: "",
  description: "",
  deadline: "",
  trainee_id: "",
  instructions: "",
  attachments: [""],
  priority: "2",
};

export default function CreateTaskModal({ onClose, phase, task = null,onUpdated
  
 }) {
  const isEditMode = Boolean(task);
  const { t } = useTranslation("common");

  const { getMyTrainees, myTrainees } = useAssigned();
  // const [form, setForm] = useState({...task}|| initialForm);
  const [form, setForm] = useState(() => ({
    ...initialForm,
    ...(task
      ? {
          name: task.name ?? "",
          description: task.description ?? "",

          deadline: task.deadline
            ? (() => {
                const [date] = task.deadline.split(" ");
                const [day, month, year] = date.split("-");
                return `${year}-${month}-${day}`;
              })()
            : "",

          trainee_id: String(task.trainee?.id ?? ""),

          instructions: task.instructions ?? "",

          attachments: task.attachments?.length
            ? task.attachments.map((attachment) =>
                typeof attachment === "string"
                  ? attachment
                  : (attachment.path ?? ""),
              )
            : [""],

          priority: String(task.priority ?? 2),
        }
      : {}),
  }));
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  

  const updateField = (name, value) => {
    setForm((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  // تحديث رابط محدد داخل مصفوفة المرفقات
  const updateAttachment = (index, value) => {
    setForm((prev) => ({
      ...prev,
      attachments: prev.attachments.map((url, i) =>
        i === index ? value : url,
      ),
    }));
  };

  // إضافة حقل رابط جديد
  const addAttachment = () => {
    setForm((prev) => ({
      ...prev,
      attachments: [...prev.attachments, ""],
    }));
  };

  // حذف حقل رابط
  const removeAttachment = (index) => {
    setForm((prev) => ({
      ...prev,
      attachments: prev.attachments.filter((_, i) => i !== index),
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    const payload = {
      name: form.name.trim(),
      description: form.description.trim(),
      deadline: form.deadline,
      trainee_id: form.trainee_id,
      instructions: form.instructions.trim(),
      attachments: form.attachments.map((url) => url.trim()).filter(Boolean),
      priority: Number(form.priority),
    };

    // console.log(payload)

    try {
      if (isEditMode) {
        const data = await updateTaskApi(task.id, payload);
        toast.success(data.message);
        
      } else {
        const data = await createTaskApi(phase?.id, payload);
        toast.success(data.message);
      }

      await onUpdated?.()
      onClose();
    } catch (err) {
      setError(
        err?.response?.data?.message ||
          err?.message ||
          "تعذر إنشاء المهمة، يرجى المحاولة مجدداً.",
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    getMyTrainees(true);
  }, []);

  return (
    <Modal title={t("task.create", { ns: "common" })} onClose={onClose}>
      <form className={styles.form} onSubmit={handleSubmit}>
        {/* اسم المهمة */}
        <Field label={t("task.name", { ns: "common" })}>
          <Input
            name="name"
            value={form.name}
            onChange={(e) => updateField("name", e.target.value)}
            placeholder=" Implement Login API"
            required
            maxLength={150}
          />
        </Field>

        {/* وصف المهمة */}
        <Field label={t("task.description")}>
          <Textarea
            name="description"
            value={form.description}
            onChange={(e) => updateField("description", e.target.value)}
            placeholder="اكتب وصف المهمة..."
            required
            maxLength={2000}
          />
        </Field>

        {/* تاريخ الانتهاء */}
        <Field label={t("task.deadline")}>
          <Input
            type="date"
            name="deadline"
            value={form.deadline}
            onChange={(e) => updateField("deadline", e.target.value)}
            min={new Date().toLocaleDateString("en-CA")}
            required
          />
        </Field>

        {/* المتدرب المعني */}
        <Field label={t("task.trainee")}>
          <Select
            name="trainee_id"
            value={form.trainee_id}
            onChange={(e) => updateField("trainee_id", e.target.value)}
            required
          >
            <option value="">----</option>

            {myTrainees.map((trainee) => (
              <option key={trainee.id} value={trainee.id}>
                {trainee.fullname || trainee.name}
              </option>
            ))}
          </Select>
        </Field>

        {/* المرفقات والروابط */}
        <div className={styles.attachmentsSection}>
          <div className={styles.sectionHeader}>
            <span>{t("task.attachments")}</span>

            <button
              type="button"
              className={styles.addButton}
              onClick={addAttachment}
            >
              <Plus size={16} />
            </button>
          </div>

          {form.attachments.map((url, index) => (
            <div className={styles.attachmentRow} key={index}>
              <Input
                type="url"
                value={url}
                onChange={(e) => updateAttachment(index, e.target.value)}
                placeholder="https://example.com/resource"
                aria-label={`رابط المرفق ${index + 1}`}
              />

              {form.attachments.length > 1 && (
                <button
                  type="button"
                  className={styles.removeButton}
                  onClick={() => removeAttachment(index)}
                  aria-label="حذف الرابط"
                >
                  <Trash2 size={17} />
                </button>
              )}
            </div>
          ))}
        </div>

        {/* التعليمات */}
        <Field label={t("task.instructions")}>
          <Textarea
            name="instructions"
            value={form.instructions}
            onChange={(e) => updateField("instructions", e.target.value)}
            placeholder="..."
            required
            maxLength={5000}
          />
        </Field>

        {/* الأولوية */}
        <Field label={t("task.priority")}>
          <Select
            name="priority"
            value={form.priority}
            onChange={(e) => updateField("priority", e.target.value)}
            required
          >
            <option value="1">- 1 - </option>
            <option value="2">- 2 - </option>
            <option value="3">- 3 - </option>
          </Select>
        </Field>

        {/* رسالة الخطأ */}
        {error && (
          <p className={styles.error} role="alert">
            {error}
          </p>
        )}

        {/* أزرار النموذج */}
        <div className={styles.actions}>
          <Button type="button" variant="" onClick={onClose} disabled={loading}>
            {t("action.cancel", { ns: "common" })}
          </Button>

          <Button type="submit" disabled={loading} loading={loading}>
            {isEditMode
              ? t("action.update", { ns: "common" })
              : t("action.create", { ns: "common" })}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
