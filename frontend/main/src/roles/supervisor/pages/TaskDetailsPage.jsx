import { useEffect, useState } from "react";
import {
  CalendarDays,
  UserRound,
  FileText,
  ExternalLink,
  ClipboardCheck,
  RotateCcw,
  Trash,
} from "lucide-react";
import { useTranslation } from "react-i18next";

import Card from "../../../components/ui/Card";
import Button from "../../../components/ui/Button";
import TaskFeedbackModal from "../components/TaskFeedbackModal";
import styles from "./TaskDetailsPage.module.css";
import { useParams } from "react-router-dom";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";
import Loading from "../../../components/ui/Loading";
import {
  createFeedbackApi,
  deleteTaskApi,
} from "../../../Api/SupervisorApi/supervisorApi";
import { toast } from "react-toastify";
import { SquarePen } from "lucide-react";
import Modal from "../../../components/ui/Modal";
import CreateTaskModal from "../components/CreateTaskModal";
import { useNavigate } from "react-router-dom";   
export default function TaskDetailsPage({}) {
  const { t, i18n } = useTranslation();
  const { taskId } = useParams();
const navigate = useNavigate();
  const { task, getTask, loading } = useAssigned();
  const [feedbackAction, setFeedbackAction] = useState(null);
  const [loadingAction, setLoadingAction] = useState();
  const [deleteModel, setDeleteModel] = useState(false);
  const [loadingDelete, setLoadingDelete] = useState(false);
  const [showUpdateTask,setShowUpdateTask]=useState(false)

  const normalizeStatus = (status) => {
    const statusMap = {
      pending: "PENDING",
      "in progress": "IN_PROGRESS",
      completed: "COMPLETED",
      cancelled: "CANCELLED",
      "for review": "FOR_REVIEW",
      "need fix": "NEED_FIX",
      overdue: "OVERDUE",
    };

    return statusMap[status?.trim().toLowerCase()] || "UNKNOWN";
  };

  const status = task.status || "PENDING";
  const normalizedStatus = normalizeStatus(status);

  const statusLabels = {
    PENDING: t("task.status.pending", "قيد الانتظار"),
    IN_PROGRESS: t("task.status.inProgress", "قيد التنفيذ"),
    SUBMITTED: t("task.status.submitted", "تم التسليم"),
    COMPLETED: t("task.status.completed", "مكتملة"),
    REVISION_REQUIRED: t("task.status.revisionRequired", "تحتاج إلى تعديل"),
  };

  const priorityLabels = {
    1: t("task.priorityLow", "منخفضة"),
    2: t("task.priorityMedium", "متوسطة"),
    3: t("task.priorityHigh", "مرتفعة"),
  };

  const formatDate = (date) => {
    if (!date || date == null) return "—";

    return new Intl.DateTimeFormat(i18n.language === "ar" ? "ar" : "en", {
      year: "numeric",
      month: "long",
      day: "numeric",
    }).format(new Date(date));
  };

  const traineeName =
    task.trainee?.fullname || task.trainee?.name || task.trainee_name || "—";

  const submittedFileName =
    task.submission?.fileName ||
    task.submissionFileName ||
    t("task.submittedFile", "ملف المهمة المرفوع");

  const attachments = Array.isArray(task.attachments) ? task.attachments : [];

  const handleFeedbackSubmit = async (feedback) => {
    if (!feedbackAction) return;
    setLoadingAction(true);
    const body = {
      evidence_id: task?.evidences?.[task.evidences.length - 1]?.id,
      feedback,
      status: feedbackAction === "evaluate" ? "completed" : "need fix",
    };
    console.log("body:", body);
    try {
      const data = await createFeedbackApi(task.id, body);
      toast.success(data.message);
      setFeedbackAction(null);
    } catch (error) {
      console.log(error);
      const message =
        error.response?.data?.message || error.message || "error in interview";
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      setLoadingAction(false);
    }
  };

  const deleteTask = async () => {
    setLoadingDelete(true);
    try {
      const data = await deleteTaskApi(task.id);
      toast.success(data.message);
      setDeleteModel(false);
      navigate(-1);
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "error in interview";
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      setLoadingDelete(false);
    }
  };

  useEffect(() => {
    getTask(taskId);
  }, []);


  return loading ? (
    <Loading />
  ) : (
    <>
      <div className={styles.page}>
        <div className={styles.pageHeader}>
          <div>
            <h1 className={styles.title}>{task.name}</h1>

            <p className={styles.subtitle}>
              {t("task.details", "تفاصيل المهمة")}
            </p>
          </div>

          <span
            className={`${styles.status} ${
              styles[`status_${normalizedStatus}`] || ""
            }`}
          >
            {statusLabels[status] || status}
          </span>
        </div>

        <Card>
          <section className={styles.section}>
            <div className="flex-s-p">
              <h2>{t("task.basicInfo", "المعلومات الأساسية")}</h2>
              <div className="flexCenter gap8">
                <Button onClick={()=>setShowUpdateTask(true)} size="sm" icon={SquarePen} variant=""></Button>

                <Button
                  onClick={() => setDeleteModel(true)}
                  size="sm"
                  icon={Trash}
                ></Button>
              </div>
            </div>

            <div className={styles.detailsGrid}>
              <div className={styles.detail}>
                <span>{t("task.name", "اسم المهمة")}</span>
                <strong>{task.name || "—"}</strong>
              </div>

              <div className={styles.detail}>
                <span>{t("task.trainee", "المتدرب المعني")}</span>
                <strong className={styles.iconValue}>
                  <UserRound size={17} />
                  {traineeName}
                </strong>
              </div>

              <div className={styles.detail}>
                <span>{t("task.deadline")}</span>
                <strong className={styles.iconValue}>
                  <CalendarDays size={17} />
                  {task?.deadline?.split(" ")[0]}
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
                <span>{t("task.priority", "الأهمية")}</span>
                <strong>
                  {priorityLabels[task.priority] || task.priority || "—"}
                </strong>
              </div>
            </div>
          </section>

          <section className={styles.section}>
            <h2>{t("task.description", "وصف المهمة")}</h2>
            <p className={styles.paragraph}>{task.description || "—"}</p>
          </section>

          <section className={styles.section}>
            <h2>{t("task.instructions", "التعليمات")}</h2>
            <p className={styles.paragraph}>{task.instructions || "—"}</p>
          </section>

          <section className={styles.section}>
            <h2>{t("task.attachments", "المصادر والمرفقات")}</h2>

            {attachments.length > 0 ? (
              <div className={styles.attachments}>
                {attachments.map((a, index) => (
                  <a
                    key={`${a.path}-${index}`}
                    href={a.path}
                    target="_blank"
                    rel="noopener noreferrer"
                    className={styles.attachment}
                  >
                    <ExternalLink size={16} />
                    {t("task.resource", "المصدر")} {index + 1}
                  </a>
                ))}
              </div>
            ) : (
              <p className={styles.muted}>
                {t("task.noAttachments", "لا توجد مرفقات")}
              </p>
            )}
          </section>

          <section className={styles.section}>
            <h2>{t("task.submission", "تسليم المتدرب")}</h2>

            <div>
              {task?.evidences?.length != 0 &&
                task?.evidences?.map((e) => {
                  return (
                    <div className={styles.flex} key={e.id}>
                      <p>{e?.feedback?.feedback}</p>
                      <a
                        href={e.evidence}
                        target="_blank"
                        rel="noopener noreferrer"
                        className={styles.submissionFile}
                      >
                        <FileText size={20} />

                        <span className={styles.fileName}>
                          {submittedFileName}
                        </span>

                        <ExternalLink size={16} />
                      </a>
                    </div>
                  );
                })}
            </div>
          </section>

          <footer className={styles.actions}>
            <Button
              variant="primary"
              icon={ClipboardCheck}
              disabled={""}
              loading={""}
              onClick={() => setFeedbackAction("evaluate")}
            >
              {t("task.evaluate", "تقييم المهمة")}
            </Button>

            <Button
              variant="secondary"
              icon={RotateCcw}
              disabled={""}
              loading={""}
              onClick={() => setFeedbackAction("revision")}
            >
              {t("task.requestRevision", "طلب إصلاح المهمة")}
            </Button>
          </footer>
        </Card>

        {feedbackAction && (
          <TaskFeedbackModal
            action={feedbackAction}
            loading={loadingAction}
            onClose={() => setFeedbackAction(null)}
            onSubmit={handleFeedbackSubmit}
          />
        )}
      </div>

      {deleteModel && (
        <Modal
          onClose={() => setDeleteModel(false)}
          title={t("modal.deleteTitle", { ns: "common" })}
        >
          <p>{t("modal.description", { ns: "common" })}</p>

          <div
            style={{
              display: "flex",
              alignItems: "center",
              gap: "10px",
              justifyContent: "end",
            }}
          >
            <Button
              onClick={() => setDeleteModel(false)}
              loading={""}
              size="sm"
              variant=""
            >
              {t("action.cancel", { ns: "common" })}
            </Button>

            <Button loading={loadingDelete} onClick={deleteTask} size="sm">
              {t("modal.delete", { ns: "common" })}
            </Button>
          </div>
        </Modal>
      )}

      {showUpdateTask&&(
        <CreateTaskModal task={task} onClose={()=>setShowUpdateTask(false)} onUpdated={() => getTask(taskId,true)}/>
      )}
    </>
  );
}


