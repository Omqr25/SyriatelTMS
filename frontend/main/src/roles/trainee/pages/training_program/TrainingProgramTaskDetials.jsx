import { useEffect, useState } from "react";
import { Link, useParams } from "react-router-dom";
import { useTranslation } from "react-i18next";
import {
  ArrowLeft,
  Play,
  UploadCloud,
  FileText,
  CheckCircle2,
  Clock,
  Download,
} from "lucide-react";
import api from "../../../../Api/axios";
import { downloadTaskEvidence, evidenceFiles } from "../../../../Api/taskEvidenceApi";
import {
  startTask,
  submitEvidence,
} from "../../../../Api/Trainee/trainingProgramApi";
import Button from "../../../../components/ui/Button";
import Loading from "../../../../components/ui/Loading";
import styles from "./TaskWorkspace.module.css";

export default function TaskDetailsPage() {
  const { taskId } = useParams();
  const { t, i18n } = useTranslation("taskWorkspace");
  const [task, setTask] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const [files, setFiles] = useState([]);
  const [success, setSuccess] = useState("");
  const [retry, setRetry] = useState(0);
  const load = async () =>
    (await api.get(`task/tasks/${taskId}`)).data.data.task;
  useEffect(() => {
    let active = true;
    setLoading(true);
    setTask(null);
    setError("");
    setSuccess("");
    setFiles([]);
    load()
      .then((data) => {
        if (active) setTask(data);
      })
      .catch((err) => {
        if (active) setError(err.response?.data?.message || t("failed"));
      })
      .finally(() => {
        if (active) setLoading(false);
      });
    return () => {
      active = false;
    };
  }, [taskId, retry]);
  const date = (value) => {
    if (!value) return "—";
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime())
      ? value
      : new Intl.DateTimeFormat(i18n.language, {
          dateStyle: "medium",
          timeStyle: "short",
        }).format(parsed);
  };
  async function act(action) {
    if (busy) return;
    setBusy(true);
    setError("");
    setSuccess("");
    try {
      await action();
      setFiles([]);
      // Lock the old actions immediately, even if refreshing details fails.
      setTask((current) => ({
        ...current,
        can_start: false,
        can_submit: false,
      }));
      setTask(await load());
      setSuccess(t("saved"));
    } catch (err) {
      const data = err.response?.data;
      setError(
        err.response?.status === 413
          ? t("uploadTooLarge")
          : data?.errors
          ? Object.values(data.errors).flat().join(" ")
          : data?.message || t("failed"),
      );
    } finally {
      setBusy(false);
    }
  }
  async function download(evidence, file) {
    setBusy(true);
    setError("");
    try {
      await downloadTaskEvidence(taskId, evidence.id, file);
    } catch {
      setError(t("downloadFailed"));
    } finally {
      setBusy(false);
    }
  }
  const safeLink = (path) => {
    try {
      const url = new URL(path);
      return ["https:", "http:"].includes(url.protocol) ? url.href : null;
    } catch {
      return null;
    }
  };
  if (loading) return <Loading />;
  if (!task)
    return (
      <section className={styles.page}>
        <p role="alert">{error}</p>
        <Button onClick={() => setRetry((x) => x + 1)}>{t("retry")}</Button>
        <Link to="/trainee/training-programs">{t("back")}</Link>
      </section>
    );
  const history = [...(task.evidences || [])].sort((a, b) => b.id - a.id);
  const late =
    task.deadline_iso &&
    new Date(task.deadline_iso) <= new Date() &&
    ["pending", "in progress", "need fix", "overdue"].includes(task.status);
  const status = late ? "overdue" : task.status;
  const steps = ["pending", "in progress", "for review", "completed"];
  const step = status === "need fix" ? 2 : steps.indexOf(status);
  return (
    <main className={styles.page}>
      <Link className={styles.back} to="/trainee/training-programs">
        <ArrowLeft size={16} />
        {t("back")}
      </Link>
      <header className={styles.header}>
        <div>
          <p className={styles.eyebrow}>
            {t(task.is_mine ? "myTask" : "teamTask")}
          </p>
          <h1>{task.name}</h1>
          <p>{t("intro")}</p>
        </div>
        <span className={styles.badge} data-status={status}>
          {t(`statuses.${status}`)}
        </span>
      </header>
      <ol className={styles.steps} aria-label={t("progress")}>
        {steps.map((value, index) => (
          <li
            key={value}
            aria-current={index === step ? "step" : undefined}
            data-current={index === step}
            data-done={index < step}
          >
            <span>{index < step ? <CheckCircle2 size={18} /> : index + 1}</span>
            {t(
              `statuses.${value === "for review" && status === "need fix" ? "need fix" : value}`,
            )}
          </li>
        ))}
      </ol>
      {error && (
        <div className={styles.error} role="alert">
          {error}{" "}
          <Button
            size="sm"
            onClick={() => setRetry((x) => x + 1)}
            disabled={busy}
          >
            {t("retry")}
          </Button>
        </div>
      )}
      {success && (
        <p className={styles.success} role="status">
          {success}
        </p>
      )}
      <div className={styles.layout}>
        <div className={styles.content}>
          <section className={styles.panel}>
            <h2>{t("overview")}</h2>
            <p className={styles.prose}>{task.description || t("none")}</p>
            <h3>{t("instructions")}</h3>
            <p className={styles.prose}>{task.instructions || t("none")}</p>
          </section>
          <section className={styles.panel}>
            <h2>{t("subtasks")}</h2>
            {task.subtasks?.length ? (
              <ul className={styles.checklist}>
                {task.subtasks.map((item, index) => (
                  <li key={index}>
                    <span
                      aria-label={t(
                        item.completed ? "subtaskDone" : "subtaskPending",
                      )}
                    >
                      {item.completed ? "✓" : "○"}
                    </span>
                    {item.title}
                  </li>
                ))}
              </ul>
            ) : (
              <p className={styles.muted}>{t("noSubtasks")}</p>
            )}
          </section>
          <section className={styles.panel}>
            <h2>{t("resources")}</h2>
            <div className={styles.resources}>
              {(task.attachments || []).map((item, index) =>
                safeLink(item.path) ? (
                  <a
                    key={item.id || index}
                    href={safeLink(item.path)}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <FileText size={20} />
                    {t("resource")} {index + 1}
                  </a>
                ) : (
                  <span key={item.id || index}>{item.path}</span>
                ),
              )}
            </div>
            {!task.attachments?.length && (
              <p className={styles.muted}>{t("noResources")}</p>
            )}
          </section>
          {task.is_mine && (
            <section className={styles.panel}>
              <h2>
                {t("history")}{" "}
                <span className={styles.count}>{history.length}</span>
              </h2>
              <p className={styles.muted}>{t("historyHint")}</p>
              {!history.length && (
                <div className={styles.empty}>
                  <FileText size={30} />
                  <p>{t("noEvidence")}</p>
                </div>
              )}
              <div className={styles.timeline}>
                {history.map((evidence, index) => (
                  <article key={evidence.id} className={styles.submission}>
                    <header>
                      <strong>
                        {t("submission")} {history.length - index}
                      </strong>
                      <time>{date(evidence.submitted_at)}</time>
                    </header>
                    <div className={styles.evidenceFiles}>
                      {evidenceFiles(evidence).map(file => (
                        <Button
                          key={file.index}
                          size="sm"
                          variant="soft"
                          icon={Download}
                          onClick={() => download(evidence, file)}
                          disabled={busy}
                        >
                          {file.name || t("download")}
                        </Button>
                      ))}
                    </div>
                    {evidence.feedback ? (
                      <div className={styles.feedback}>
                        <span
                          className={styles.badge}
                          data-status={evidence.feedback.status}
                        >
                          {t(`statuses.${evidence.feedback.status}`)}
                        </span>
                        <h3>{t("feedback")}</h3>
                        <p className={styles.prose}>
                          {evidence.feedback.feedback}
                        </p>
                        <small>
                          {evidence.feedback.trainer?.name} ·{" "}
                          {date(evidence.feedback.reviewed_at)}
                        </small>
                      </div>
                    ) : (
                      <p className={styles.muted}>
                        <Clock size={15} /> {t("awaiting")}
                      </p>
                    )}
                  </article>
                ))}
              </div>
            </section>
          )}
        </div>
        <aside className={styles.side}>
          <section className={styles.panel}>
            <h2>{t("details")}</h2>
            <dl className={styles.facts}>
              <dt>{t("assignee")}</dt>
              <dd>{task.trainee?.name || "—"}</dd>
              <dt>{t("deadline")}</dt>
              <dd>{date(task.deadline_iso || task.deadline)}</dd>
              <dt>{t("priority")}</dt>
              <dd>{task.priority ?? "—"}</dd>
              <dt>{t("started")}</dt>
              <dd>{date(task.started_at)}</dd>
              <dt>{t("completed")}</dt>
              <dd>{date(task.completed_at)}</dd>
            </dl>
          </section>
          <section className={`${styles.panel} ${styles.actionPanel}`}>
            <h2>{t("next")}</h2>
            {!task.is_mine ? (
              <p>{t("readOnly")}</p>
            ) : late || status === "overdue" ? (
              <p>{t("overdue")}</p>
            ) : task.can_start ? (
              <>
                <p>{t("startHint")}</p>
                <Button
                  icon={Play}
                  fullWidth
                  loading={busy}
                  onClick={() => act(() => startTask(taskId))}
                >
                  {t("start")}
                </Button>
              </>
            ) : task.can_submit ? (
              <form
                onSubmit={(event) => {
                  event.preventDefault();
                  if (files.length) act(() => submitEvidence(taskId, files));
                }}
              >
                <p>
                  {t(status === "need fix" ? "revisionHint" : "submitHint")}
                </p>
                <label className={styles.upload}>
                  <UploadCloud size={28} />
                  <strong>{t("choose")}</strong>
                  <span>{t("fileHint")}</span>
                  <input
                    aria-label={t("choose")}
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.gif"
                    multiple
                    disabled={busy}
                    onChange={(event) => {
                      const selected = Array.from(event.target.files || []);
                      event.target.value = "";
                      if (files.length + selected.length > 3) {
                        setError(t("tooManyFiles"));
                        return;
                      }
                      if (selected.some(file =>
                        file.size > 10 * 1024 * 1024 ||
                        !/\.(pdf|jpe?g|png|webp|gif)$/i.test(file.name),
                      )) {
                        setError(t("invalidFile"));
                        return;
                      }
                      setError("");
                      setFiles([...files, ...selected]);
                    }}
                  />
                </label>
                {files.length > 0 && (
                  <ul className={styles.filename}>
                    {files.map((file, index) => (
                      <li key={index}>
                        <span>{file.name} · {(file.size / 1024 / 1024).toFixed(2)} MB</span>
                        <Button
                          size="sm"
                          variant="soft"
                          aria-label={t("removeFile", { name: file.name })}
                          disabled={busy}
                          onClick={() => {
                            setFiles(files.filter((_, position) => position !== index));
                            setError("");
                          }}
                        >
                          {t("remove")}
                        </Button>
                      </li>
                    ))}
                  </ul>
                )}
                <Button
                  fullWidth
                  icon={UploadCloud}
                  type="submit"
                  disabled={!files.length}
                  loading={busy}
                >
                  {t(status === "need fix" ? "resubmit" : "submit")}
                </Button>
              </form>
            ) : (
              <p>
                {t(
                  status === "for review"
                    ? "awaiting"
                    : status === "completed"
                      ? "done"
                      : "unavailable",
                )}
              </p>
            )}
          </section>
        </aside>
      </div>
    </main>
  );
}
