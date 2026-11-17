import { useTranslation } from "react-i18next";
import { useSearchParams } from "react-router-dom";
import { useEffect, useRef, useState } from "react";
import Button from "../../../components/ui/Button";
import Table from "../../../components/ui/Table";
import styles from "./Pages.module.css";
import useAssign from "../../../hooks/HrHooks/useAssigned";
import Loading from "../../../components/ui/Loading";
import { toast } from "react-toastify";
import ScheduleInterviewModal from "../components/ScheduleInterviewModal";

export default function AssignedRequestsPage() {
  const { t } = useTranslation("hr");
  const [searchParams, setSearchParams] = useSearchParams();
  const scheduleId = searchParams.get("schedule");
  const consumed = useRef(null);
  const [applicant, setApplicant] = useState(null);
  const { loading, assigned, error, getAssigned } = useAssign();

  useEffect(() => {
    getAssigned();
  }, [getAssigned]);

  useEffect(() => {
    if (error) {
      const message = error.response?.data?.message || error.message;
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    }
  }, [error]);

  useEffect(() => {
    if (!scheduleId) {
      consumed.current = null;
      return;
    }
    if (loading || error || consumed.current === scheduleId) return;
    consumed.current = scheduleId;
    const selected = assigned.find((row) => String(row.id) === scheduleId);
    const next = new URLSearchParams(searchParams);
    next.delete("schedule");
    setSearchParams(next, { replace: true });
    if (selected?.status === "qualified") setApplicant(selected);
    else toast.error(t("assigned.scheduleUnavailable"));
  }, [scheduleId, loading, error, assigned, searchParams, setSearchParams, t]);

  const columns = [
    {
      key: "name",
      header: t("assigned.applicant"),
      render: (a) => <strong className={styles.strong}>{a.name}</strong>,
    },
    {
      key: "status",
      header: t("label.status", { ns: "common" }),
      render: (a) => t(`status.applicant.${a.status}`, { ns: "common" }),
    },
    {
      key: "supervisor",
      header: t("assigned.supervisor"),
      render: (a) => (
        <span className={styles.accent}>
          {a.supervisors?.name ?? t("assigned.unassigned")}
        </span>
      ),
    },
    {
      key: "actions",
      header: t("label.actions", { ns: "common" }),
      render: (a) =>
        a.status === "qualified" && (
          <Button size="sm" onClick={() => setApplicant(a)}>
            {t("applicants.scheduleInterview")}
          </Button>
        ),
    },
  ];

  return loading ? (
    <Loading />
  ) : (
    <>
      <Table
        columns={columns}
        rows={assigned}
        emptyText={t("assigned.empty")}
      />
      {applicant && (
        <ScheduleInterviewModal
          applicant={applicant}
          onClose={() => setApplicant(null)}
          update={getAssigned}
        />
      )}
    </>
  );
}
