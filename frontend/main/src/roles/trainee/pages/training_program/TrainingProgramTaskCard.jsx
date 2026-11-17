import { Link } from "react-router-dom";
import {
  CalendarDays,
  UserRound,
  Building2,
  Paperclip,
  FileCheck2,
  ArrowUpLeft,
} from "lucide-react";

import { useTranslation } from "react-i18next";
import styles from "./TrainingProgramTaskCard.module.css";

export default function TrainingProgramTaskCard({ task, available }) {
  const { t } = useTranslation();
  if (!task) return null;

  const statusMap = {
    pending: "PENDING",
    "in progress": "IN_PROGRESS",
    completed: "COMPLETED",
    cancelled: "CANCELLED",
    "for review": "FOR_REVIEW",
    "need fix": "NEED_FIX",
    overdue: "OVERDUE",
  };

  const late =
    task.deadline_iso &&
    new Date(task.deadline_iso) <= new Date() &&
    ["pending", "in progress", "need fix"].includes(task.status);
  const rawStatus = late ? "overdue" : task?.status?.trim().toLowerCase();
  const normalizedStatus = statusMap[rawStatus] || "UNKNOWN";

  return (
    <Link
      to={available ? `/trainee/tasks/details/${task.id}` : "#"}
      className={styles.card}
      aria-label={`${t("task.viewDetails")}: ${task.name}`}
    >
      <div className={styles.header}>
        <div className={styles.titleWrapper}>
          <h3 className={styles.title}>{task.name}</h3>

          <p className={styles.description}>
            {task.description || t("task.noDescription")}
          </p>
        </div>

        <ArrowUpLeft size={18} className={styles.arrow} aria-hidden="true" />
      </div>

      <div className={styles.info}>
        <div className={styles.infoItem}>
          <CalendarDays size={16} />
          <span>
            {t("task.deadline")}: {task.deadline?.split(" ")[0] || "—"}
          </span>
        </div>

        <div className={styles.infoItem}>
          <UserRound size={16} />
          <span>{task.trainee?.name || t("task.unknownTrainee")}</span>
        </div>

        <div className={styles.infoItem}>
          <Building2 size={16} />
          <span>
            {task.trainee?.department?.name || t("task.unknownDepartment")}
          </span>
        </div>
      </div>

      <div
        className={`${styles.footer}  ${
          styles[`status_${normalizedStatus}`] || styles.status_UNKNOWN
        }`}
      >
        <span
          className={`${styles.deadlineBadge} ${
            styles[`status_${normalizedStatus}`] || styles.status_UNKNOWN
          }`}
        >
          {t(`task.status.${normalizedStatus}`, {
            defaultValue: task?.status || t("task.status.UNKNOWN"),
          })}
        </span>

        <div className={styles.stats}>
          <span title={t("task.attachments")}>
            <Paperclip size={15} />
            {task.attachments?.length ?? 0}
          </span>

          <span>
            <FileCheck2 size={15} />
            {task.is_mine
              ? t("myTask", { ns: "taskWorkspace" })
              : t("teamTask", { ns: "taskWorkspace" })}
          </span>
        </div>
      </div>
    </Link>
  );
}
