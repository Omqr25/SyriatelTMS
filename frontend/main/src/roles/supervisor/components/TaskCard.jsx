import { Link } from "react-router-dom";
import {
  CalendarDays,
  UserRound,
  Paperclip,
  ArrowUpLeft,
} from "lucide-react";

import { useTranslation } from "react-i18next";
import styles from "./TaskCard.module.css";

export default function TaskCard({ task, returnTo, showAssignee = true }) {
  const { t, i18n } = useTranslation();

  if (!task) return null;

  const statusMap = {
    draft: "DRAFT",
    pending: "PENDING",
    "in progress": "IN_PROGRESS",
    completed: "COMPLETED",
    cancelled: "CANCELLED",
    "for review": "FOR_REVIEW",
    "need fix": "NEED_FIX",
    overdue: "OVERDUE",
  };

  const rawStatus = task?.status?.trim().toLowerCase();
  const normalizedStatus = statusMap[rawStatus] || "UNKNOWN";

  const formatDate = (date) => {
    if (!date) return t("task.noDeadline");

    const parsedDate = new Date(date);

    if (Number.isNaN(parsedDate.getTime())) return date;

    return new Intl.DateTimeFormat(i18n.language, {
      year: "numeric",
      month: "short",
      day: "numeric",
    }).format(parsedDate);
  };

  return (
    <Link
      to={`/supervisor/tasks/details/${task.id}`}
      state={returnTo ? { returnTo } : undefined}
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
            {t("task.deadline")}: {formatDate(task.deadline_iso || task.deadline)}
          </span>
        </div>

        {showAssignee && task.trainee && (
          <div className={styles.infoItem}>
            <UserRound size={16} aria-hidden="true" />
            <span>{task.trainee.name}</span>
          </div>
        )}
      </div>

      <div className={`${styles.footer}  ${
            styles[`status_${normalizedStatus}`] || styles.status_UNKNOWN
          }`}>
        <span
          className={`${styles.deadlineBadge} ${
            styles[`status_${normalizedStatus}`] || styles.status_UNKNOWN
          }`}
        >
          {normalizedStatus === "DRAFT" ? t("draft", { ns: "supervisorTask" }) : normalizedStatus === "PENDING" ? t("statuses.pending", { ns: "taskWorkspace" }) : t(`task.status.${normalizedStatus}`, {
            defaultValue: task?.status || t("task.status.UNKNOWN"),
          })}
        </span>

        <div className={styles.stats}>
          <span title={t("task.attachments")}>
            <Paperclip size={15} />
            {task.attachments?.length ?? 0}
          </span>

        </div>
      </div>
    </Link>
  );
}
