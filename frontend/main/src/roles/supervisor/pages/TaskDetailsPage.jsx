import { useEffect, useMemo, useState } from "react";
import {
  CalendarDays,
  UserRound,
  FileText,
  ExternalLink,
  ClipboardCheck,
  RotateCcw,
  Trash,
  SquarePen,
  AlertCircle,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { useNavigate, useParams } from "react-router-dom";
import { toast } from "react-toastify";

import Card from "../../../components/ui/Card";
import Button from "../../../components/ui/Button";
import Modal from "../../../components/ui/Modal";
import Loading from "../../../components/ui/Loading";

import TaskFeedbackModal from "../components/TaskFeedbackModal";
import CreateTaskModal from "../components/CreateTaskModal";

import useAssigned from "../../../hooks/SupervisorHooks/useassigned";

import {
  createFeedbackApi,
  deleteTaskApi,
} from "../../../Api/SupervisorApi/supervisorApi";

import styles from "./TaskDetailsPage.module.css";

const STATUS_MAP = {
  pending: "PENDING",
  in_progress: "IN_PROGRESS",
  "in progress": "IN_PROGRESS",
  submitted: "SUBMITTED",
  completed: "COMPLETED",
  cancelled: "CANCELLED",
  for_review: "FOR_REVIEW",
  "for review": "FOR_REVIEW",
  need_fix: "NEED_FIX",
  "need fix": "NEED_FIX",
  revision_required: "REVISION_REQUIRED",
  overdue: "OVERDUE",
};

const normalizeStatus = (status) => {
  if (!status) return "PENDING";

  const normalized = String(status).trim().toLowerCase();

  return STATUS_MAP[normalized] || "UNKNOWN";
};

const getErrorMessage = (error, fallback) => {
  const message = error?.response?.data?.message || error?.message || fallback;

  return Array.isArray(message) ? message.join("، ") : message;
};

export default function TaskDetailsPage() {
  const { t, i18n } = useTranslation();
  const { taskId } = useParams();
  const navigate = useNavigate();

  const { task, getTask, loading } = useAssigned();

  const [feedbackAction, setFeedbackAction] = useState(null);
  const [loadingAction, setLoadingAction] = useState(false);

  const [deleteModel, setDeleteModel] = useState(false);
  const [loadingDelete, setLoadingDelete] = useState(false);

  const [showUpdateTask, setShowUpdateTask] = useState(false);

  const evidences = useMemo(
    () => (Array.isArray(task?.evidences) ? task.evidences : []),
    [task?.evidences],
  );

  const attachments = useMemo(
    () => (Array.isArray(task?.attachments) ? task.attachments : []),
    [task?.attachments],
  );

  const latestEvidence = evidences.length
    ? evidences[evidences.length - 1]
    : null;

  const normalizedStatus = normalizeStatus(task?.status);

  const statusLabels = {
    PENDING: t("task.status.pending"),
    IN_PROGRESS: t("task.status.inProgress"),
    SUBMITTED: t("task.status.submitted"),
    COMPLETED: t("task.status.completed"),
    CANCELLED: t("task.status.cancelled"),
    FOR_REVIEW: t("task.status.forReview"),
    NEED_FIX: t("task.status.needFix"),
    REVISION_REQUIRED: t("task.status.revisionRequired"),
    OVERDUE: t("task.status.overdue"),
    UNKNOWN: t("task.status.unknown"),
  };

  const traineeName =
    task?.trainee?.fullname ||
    task?.trainee?.full_name ||
    task?.trainee?.name ||
    task?.trainee_name ||
    "—";

  const formatDate = (date) => {
    if (!date) return "—";

    const parsedDate = new Date(date);

    if (Number.isNaN(parsedDate.getTime())) return "—";

    return new Intl.DateTimeFormat(
      i18n.language?.startsWith("ar") ? "ar" : "en",
      {
        year: "numeric",
        month: "long",
        day: "numeric",
      },
    ).format(parsedDate);
  };

  const getAttachmentUrl = (attachment) => {
    if (typeof attachment === "string") return attachment;

    return (
      attachment?.url ||
      attachment?.path ||
      attachment?.file_path ||
      attachment?.file ||
      ""
    );
  };

  const getAttachmentName = (attachment, index) => {
    if (typeof attachment === "string") {
      return `${t("task.resource")} ${index + 1}`;
    }

    return (
      attachment?.original_name ||
      attachment?.file_name ||
      attachment?.name ||
      `${t("task.resource")} ${index + 1}`
    );
  };

  const getEvidenceUrl = (evidence) => {
    return (
      evidence?.url ||
      evidence?.evidence ||
      evidence?.path ||
      evidence?.file_path ||
      ""
    );
  };

  const getEvidenceName = (evidence, index) => {
    return (
      evidence?.original_name ||
      evidence?.file_name ||
      evidence?.fileName ||
      evidence?.name ||
      `${t("task.submittedFile")} ${index + 1}`
    );
  };

  const getFeedbackText = (evidence) => {
    if (!evidence?.feedback) return "";

    if (typeof evidence.feedback === "string") {
      return evidence.feedback;
    }

    return (
      evidence.feedback.feedback ||
      evidence.feedback.comment ||
      evidence.feedback.message ||
      ""
    );
  };

  const priority = Number(task?.priority);
  const hasValidPriority =
    Number.isInteger(priority) && priority >= 1 && priority <= 10;

  const refreshTask = () => {
    if (taskId) {
      getTask(taskId, true);
    }
  };

  const handleFeedbackSubmit = async (feedback) => {
    if (!task?.id || !latestEvidence?.id) {
      toast.error(t("task.errors.noEvidence"));
      return;
    }

    setLoadingAction(true);

    const body = {
      evidence_id: latestEvidence.id,
      feedback,
      // Keep these values aligned with the backend's accepted task statuses.
      status: feedbackAction === "evaluate" ? "completed" : "need fix",
    };

    try {
      const response = await createFeedbackApi(task.id, body);

      toast.success(response?.message || t("task.success.feedbackSaved"));

      setFeedbackAction(null);
      refreshTask();
    } catch (error) {
      toast.error(getErrorMessage(error, t("task.errors.feedbackFailed")));
    } finally {
      setLoadingAction(false);
    }
  };

  const handleDeleteTask = async () => {
    if (!task?.id) return;

    setLoadingDelete(true);

    try {
      const response = await deleteTaskApi(task.id);

      toast.success(response?.message || t("task.success.deleted"));

      setDeleteModel(false);
      navigate(-1);
    } catch (error) {
      toast.error(getErrorMessage(error, t("task.errors.deleteFailed")));
    } finally {
      setLoadingDelete(false);
    }
  };

  useEffect(() => {
    if (taskId) {
      getTask(taskId);
    }
  }, [taskId]);

  if (loading) {
    return <Loading />;
  }

  if (!task) {
    return (
      <div className={styles.page}>
        <Card>
          <div className={styles.emptyState}>
            <AlertCircle size={32} />
            <h2>{t("task.notFound.title")}</h2>
            <p>{t("task.notFound.description")}</p>
            <Button onClick={() => navigate(-1)}>{t("task.back")}</Button>
          </div>
        </Card>
      </div>
    );
  }

  return (
    <>
      <div className={styles.page}>
        <header className={styles.pageHeader}>
          <div className={styles.headingContent}>
            <h1 className={styles.title}>{task.name || t("task.untitled")}</h1>
            <p className={styles.subtitle}>{t("task.details")}</p>
          </div>

          <span
            className={`${styles.status} ${
              styles[`status_${normalizedStatus}`] || ""
            }`}
          >
            {statusLabels[normalizedStatus] || t("task.status.unknown")}
          </span>
        </header>

        <Card>
          <section className={styles.section}>
            <div className={styles.sectionHeader}>
              <h2>{t("task.basicInfo")}</h2>

              <div className={styles.headerActions}>
                <Button
                  onClick={() => setShowUpdateTask(true)}
                  size="sm"
                  icon={SquarePen}
                  variant="secondary"
                  aria-label={t("task.edit")}
                >
                  {t("task.edit")}
                </Button>

                <Button
                  onClick={() => setDeleteModel(true)}
                  size="sm"
                  icon={Trash}
                  variant="danger"
                  aria-label={t("task.delete")}
                >
                  {t("task.delete")}
                </Button>
              </div>
            </div>

            <div className={styles.detailsGrid}>
              <div className={styles.detail}>
                <span>{t("task.name")}</span>
                <strong>{task.name || "—"}</strong>
              </div>

              <div className={styles.detail}>
                <span>{t("task.trainee")}</span>
                <strong className={styles.iconValue}>
                  <UserRound size={17} />
                  {traineeName}
                </strong>
              </div>

              <div className={styles.detail}>
                <span>{t("task.deadline")}</span>
                <strong className={styles.iconValue}>
                  <CalendarDays size={17} />
                  {formatDate(task.deadline)}
                </strong>
              </div>

              <div className={styles.detail}>
                <span>{t("task.completeDate")}</span>
                <strong className={styles.iconValue}>
                  <CalendarDays size={17} />
                  {formatDate(task.completed_at)}
                </strong>
              </div>

              <div className={styles.detail}>
                <span>{t("task.startDate")}</span>
                <strong className={styles.iconValue}>
                  <CalendarDays size={17} />
                  {formatDate(task.started_at)}
                </strong>
              </div>

              <div className={styles.detail}>
                <span>{t("task.priority")}</span>
                <strong className={styles.priorityValue}>
                  {hasValidPriority
                    ? t("task.priorityValue", { value: priority })
                    : "—"}
                </strong>
              </div>
            </div>
          </section>

          <section className={styles.section}>
            <h2>{t("task.description")}</h2>
            <p className={styles.paragraph}>
              {task.description || t("task.noDescription")}
            </p>
          </section>

          <section className={styles.section}>
            <h2>{t("task.instructions")}</h2>
            <p className={styles.paragraph}>
              {task.instructions || t("task.noInstructions")}
            </p>
          </section>

          <section className={styles.section}>
            <h2>{t("task.attachments")}</h2>

            {attachments.length > 0 ? (
              <div className={styles.attachments}>
                {attachments.map((attachment, index) => {
                  const url = getAttachmentUrl(attachment);

                  if (!url) return null;

                  return (
                    <a
                      key={attachment?.id || `${url}-${index}`}
                      href={url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className={styles.attachment}
                    >
                      <FileText size={18} />
                      <span>{getAttachmentName(attachment, index)}</span>
                      <ExternalLink size={15} />
                    </a>
                  );
                })}
              </div>
            ) : (
              <p className={styles.muted}>{t("task.noAttachments")}</p>
            )}
          </section>

          <section className={styles.section}>
            <h2>{t("task.submission")}</h2>

            {evidences.length > 0 ? (
              <div className={styles.submissions}>
                {evidences.map((evidence, index) => {
                  const url = getEvidenceUrl(evidence);
                  const feedback = getFeedbackText(evidence);

                  return (
                    <div
                      className={styles.submission}
                      key={evidence.id || index}
                    >
                      <div className={styles.submissionHeader}>
                        <span className={styles.submissionNumber}>
                          {t("task.submissionNumber", {
                            number: index + 1,
                          })}
                        </span>

                        {evidence.created_at && (
                          <span className={styles.submissionDate}>
                            {formatDate(evidence.created_at)}
                          </span>
                        )}
                      </div>

                      {url ? (
                        <a
                          href={url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className={styles.submissionFile}
                        >
                          <FileText size={19} />

                          <span className={styles.fileName}>
                            {getEvidenceName(evidence, index)}
                          </span>

                          <ExternalLink size={16} />
                        </a>
                      ) : (
                        <p className={styles.muted}>
                          {t("task.noSubmissionFile")}
                        </p>
                      )}

                      {feedback && (
                        <div className={styles.feedback}>
                          <strong>{t("task.feedback")}</strong>
                          <p>{feedback}</p>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className={styles.muted}>{t("task.noSubmission")}</p>
            )}
          </section>

          <footer className={styles.actions}>
            <Button
              variant="primary"
              icon={ClipboardCheck}
              disabled={!latestEvidence?.id || loadingAction}
              loading={loadingAction && feedbackAction === "evaluate"}
              onClick={() => setFeedbackAction("evaluate")}
            >
              {t("task.evaluate")}
            </Button>

            <Button
              variant="secondary"
              icon={RotateCcw}
              disabled={!latestEvidence?.id || loadingAction}
              loading={loadingAction && feedbackAction === "revision"}
              onClick={() => setFeedbackAction("revision")}
            >
              {t("task.requestRevision")}
            </Button>
          </footer>

          {!latestEvidence?.id && (
            <p className={styles.actionHint}>
              {t("task.feedbackRequiresSubmission")}
            </p>
          )}
        </Card>

        {feedbackAction && (
          <TaskFeedbackModal
            action={feedbackAction}
            loading={loadingAction}
            onClose={() => {
              if (!loadingAction) setFeedbackAction(null);
            }}
            onSubmit={handleFeedbackSubmit}
          />
        )}
      </div>

      {deleteModel && (
        <Modal
          onClose={() => {
            if (!loadingDelete) setDeleteModel(false);
          }}
          title={t("modal.deleteTitle", { ns: "common" })}
        >
          <div className={styles.deleteContent}>
            <p>{t("task.confirmDelete")}</p>

            <div className={styles.modalActions}>
              <Button
                onClick={() => setDeleteModel(false)}
                disabled={loadingDelete}
                size="sm"
                variant="secondary"
              >
                {t("action.cancel", { ns: "common" })}
              </Button>

              <Button
                loading={loadingDelete}
                disabled={loadingDelete}
                onClick={handleDeleteTask}
                size="sm"
                variant="danger"
              >
                {t("modal.delete", { ns: "common" })}
              </Button>
            </div>
          </div>
        </Modal>
      )}

      {showUpdateTask && (
        <CreateTaskModal
          task={task}
          onClose={() => setShowUpdateTask(false)}
          onUpdated={() => {
            setShowUpdateTask(false);
            refreshTask();
          }}
        />
      )}
    </>
  );
}
