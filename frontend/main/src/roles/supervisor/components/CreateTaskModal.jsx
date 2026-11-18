import { useEffect, useState } from "react";
import { Plus, Trash2 } from "lucide-react";
import { useTranslation } from "react-i18next";

import Modal from "../../../components/ui/Modal";
import { Field, Input, Select, Textarea } from "../../../components/ui/Field";
import Button from "../../../components/ui/Button";

import styles from "./CreateTaskModal.module.css";
import api from "../../../Api/axios";
import {
  createTaskApi,
  updateTaskApi,
} from "../../../Api/SupervisorApi/supervisorApi";
import { toast } from "react-toastify";

function localDeadline(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
  return date.toISOString().slice(0, 16);
}

const initialForm = {
  name: "",
  description: "",
  deadline: "",
  trainee_id: "",
  trainee_ids: [],
  instructions: "",
  attachments: [""],
  subtasks: [],
  priority: "2",
};

export default function CreateTaskModal({
  onClose,
  phase,
  task = null,
  onUpdated,
  programId,
  onStage,
}) {
  const isEditMode = Boolean(task);
  const { t } = useTranslation("common");

  const [myTrainees, setMyTrainees] = useState([]);
  const [candidateError, setCandidateError] = useState("");
  const [loadingCandidates, setLoadingCandidates] = useState(true);
  const [candidateAttempt, setCandidateAttempt] = useState(0);
  const [form, setForm] = useState(() => ({
    ...initialForm,
    ...(task
      ? {
          name: task.name ?? "",
          description: task.description ?? "",

          deadline: localDeadline(task.deadline_iso),

          trainee_id: String(task.trainee?.id ?? ""),

          instructions: task.instructions ?? "",
          subtasks: task.subtasks ?? [],

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
    if (loading) return;
    setError("");
    setLoading(true);

    const payload = {
      name: form.name.trim(),
      description: form.description.trim(),
      deadline: form.deadline,
      ...(isEditMode
        ? { trainee_id: form.trainee_id ? Number(form.trainee_id) : null }
        : { trainee_ids: form.trainee_ids }),
      instructions: form.instructions.trim(),
      attachments: form.attachments.map((url) => url.trim()).filter(Boolean),
      priority: Number(form.priority),
      subtasks: form.subtasks.map((item) => ({
        ...item,
        title: item.title.trim(),
      })),
    };

    if (isEditMode) {
      const previous = {
        name: task.name,
        description: task.description || "",
        instructions: task.instructions || "",
        deadline: localDeadline(task.deadline_iso),
        priority: task.priority,
        trainee_id: task.trainee?.id ?? null,
        attachments: (task.attachments || []).map(item => item.path),
        subtasks: task.subtasks || [],
      };
      for (const key of Object.keys(payload)) {
        if (JSON.stringify(payload[key]) === JSON.stringify(previous[key])) delete payload[key];
      }
      if (Object.keys(payload).length === 0) {
        setError(t("noChanges", { ns: "supervisorTask" }));
        setLoading(false);
        return;
      }
    }

    if (payload.deadline) payload.deadline = new Date(payload.deadline).toISOString();

    try {
      if (onStage) {
        onStage(payload);
      } else if (isEditMode) {
        const data = await updateTaskApi(task.id, payload);
        toast.success(data.message);
      } else {
        const data = await createTaskApi(phase?.id, payload);
        toast.success(data.message);
      }

      await onUpdated?.();
      onClose();
    } catch (err) {
      setError(
        Object.values(err?.response?.data?.errors || {}).flat().join(" ") ||
          err?.response?.data?.message ||
          err?.message ||
          "تعذر إنشاء المهمة، يرجى المحاولة مجدداً.",
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isEditMode && !task.can_assign) {
      setLoadingCandidates(false);
      return;
    }
    const controller = new AbortController();
    setLoadingCandidates(true);
    setCandidateError("");
    api.get(programId ? `program/${programId}/task-candidates` : "program/task-candidates", {
      signal: controller.signal,
    }).then(({ data }) => setMyTrainees(data.data)).catch(() => {
      if (!controller.signal.aborted) setCandidateError(t("candidateError", { ns: "supervisorTask" }));
    }).finally(() => {
      if (!controller.signal.aborted) setLoadingCandidates(false);
    });
    return () => controller.abort();
  }, [programId, candidateAttempt, isEditMode, task?.can_assign, t]);

  return (
    <Modal title={t(isEditMode ? "task.edit" : "task.create", { ns: "common" })} onClose={() => { if (!loading) onClose(); }}>
      <form className={styles.form} onSubmit={handleSubmit}>
        {/* اسم المهمة */}
        <Field label={t("task.name", { ns: "common" })}>
          <Input
            name="name"
            value={form.name}
            onChange={(e) => updateField("name", e.target.value)}
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
            maxLength={2000}
          />
        </Field>

        {/* تاريخ الانتهاء */}
        <Field label={t("task.deadline")}>
          <Input
            type="datetime-local"
            name="deadline"
            value={form.deadline}
            onChange={(e) => updateField("deadline", e.target.value)}
            required
          />
        </Field>

        {isEditMode ? (
          <Field label={t("task.trainee")}>
            <Select
              name="trainee_id"
              value={form.trainee_id}
              disabled={!task.can_assign || loadingCandidates || Boolean(candidateError)}
              onChange={e => updateField("trainee_id", e.target.value)}
            >
              {!task.trainee && <option value="">{t("draft", { ns: "supervisorTask" })}</option>}
              {task.trainee && !myTrainees.some(item => item.id === task.trainee.id) &&
                <option value={task.trainee.id}>{task.trainee.name}</option>}
              {myTrainees.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}
            </Select>
          </Field>
        ) : (
          <fieldset className={styles.subtasks}>
            <legend>{t("assignees", { ns: "supervisorTask" })}</legend>
            <p>{t("draftHint", { ns: "supervisorTask" })}</p>
            {loadingCandidates && <p role="status">{t("loadingCandidates", { ns: "supervisorTask" })}</p>}
            {!loadingCandidates && !candidateError && myTrainees.length === 0 &&
              <p>{t("noCandidates", { ns: "supervisorTask" })}</p>}
            <div className={styles.assignees}>
              {myTrainees.map(item => (
                <label key={item.id}>
                  <input type="checkbox" checked={form.trainee_ids.includes(item.id)} onChange={event => {
                    updateField("trainee_ids", event.target.checked
                      ? [...form.trainee_ids, item.id]
                      : form.trainee_ids.filter(id => id !== item.id));
                  }} />
                  {item.name}
                </label>
              ))}
            </div>
          </fieldset>
        )}
        {candidateError && <p role="alert">{candidateError}{" "}
          <Button onClick={() => setCandidateAttempt(value => value + 1)} size="sm">{t("retry", { ns: "taskWorkspace" })}</Button>
        </p>}

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
            maxLength={5000}
          />
        </Field>

        <fieldset className={styles.subtasks}>
          <legend>{t("subtasks", { ns: "taskWorkspace" })}</legend>
          {form.subtasks.map((item, index) => (
            <div className={styles.attachmentRow} key={index}>
              <input
                type="checkbox"
                aria-label={t("subtaskDone", { ns: "taskWorkspace" })}
                checked={item.completed}
                onChange={(e) =>
                  updateField(
                    "subtasks",
                    form.subtasks.map((row, i) =>
                      i === index
                        ? { ...row, completed: e.target.checked }
                        : row,
                    ),
                  )
                }
              />
              <Input
                aria-label={t("subtaskTitle", { ns: "taskWorkspace" })}
                value={item.title}
                required
                maxLength={255}
                onChange={(e) =>
                  updateField(
                    "subtasks",
                    form.subtasks.map((row, i) =>
                      i === index ? { ...row, title: e.target.value } : row,
                    ),
                  )
                }
              />
              <Button
                type="button"
                variant="soft"
                onClick={() =>
                  updateField(
                    "subtasks",
                    form.subtasks.filter((_, i) => i !== index),
                  )
                }
              >
                {t("removeSubtask", { ns: "taskWorkspace" })}
              </Button>
            </div>
          ))}
          <Button
            type="button"
            variant="soft"
            disabled={form.subtasks.length >= 50}
            onClick={() =>
              updateField("subtasks", [
                ...form.subtasks,
                { title: "", completed: false },
              ])
            }
          >
            {t("addSubtask", { ns: "taskWorkspace" })}
          </Button>
        </fieldset>

        {/* الأولوية */}
        <Field label={t("task.priority")}>
          <Select
            name="priority"
            value={form.priority}
            onChange={(e) => updateField("priority", e.target.value)}
            required
          >
            {Number(form.priority) > 3 && <option value={form.priority}>{form.priority}</option>}
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

          <Button type="submit" disabled={loading || (!isEditMode && loadingCandidates)} loading={loading}>
            {isEditMode
              ? t("action.update", { ns: "common" })
              : t(form.trainee_ids.length ? "saveAssignment" : "saveDraft", { ns: "supervisorTask" })}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
