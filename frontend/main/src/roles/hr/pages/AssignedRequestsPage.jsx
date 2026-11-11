import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import Table from "../../../components/ui/Table";
import styles from "./Pages.module.css";
import useAssign from "../../../hooks/HrHooks/useAssigned";
import Loading from "../../../components/ui/Loading";
import { toast } from "react-toastify";
import { useEffect, useState } from "react";
import ScheduleInterviewModal from "../components/ScheduleInterviewModal";

export default function AssignedRequestsPage() {
  const { t } = useTranslation("hr");

  const [applicant, setApplicant] = useState([]);
  const [actionLoading, setActionLoading] = useState("");
  const { loading, assigned, error, getAssigned } = useAssign();
  const [modal, setModal] = useState(null); // { type: 'details' | 'assign' | 'schedule', applicant }

  const closeModal = () => {
    setModal(null);
  };

  const rows = assigned;

  const handleScheduleInterview = async (a) => {
    setActionLoading(a.id);
    setApplicant(a);
    setActionLoading(null);
    if (!a) {
      return;
    }
    setModal({
      type: "schedule",
    });

    if (error) {
      console.log("error:", error);
      const message =
        error.response?.data?.message || error.message || "error in interview";
      toast.error(message);
    }
  };

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
          {a.supervisors.name ?? t("assigned.unassigned")}
        </span>
      ),
    },
    {
      key: "actions",
      header: t("label.actions", { ns: "common" }),
      render: (a) =>
        a.status === "qualified" && (
          <Button
            size="sm"
            loading={a.id == actionLoading}
            onClick={() => handleScheduleInterview(a)}
          >
            {t("applicants.scheduleInterview")}
          </Button>
        ),
    },
  ];

  useEffect(() => {
    getAssigned();
  }, []);

  return loading ? (
    <Loading />
  ) : (
    <>
      <Table columns={columns} rows={rows} emptyText={t("assigned.empty")} />

      {modal?.type === "schedule" && applicant && (
        <ScheduleInterviewModal
          applicant={applicant}
          onClose={closeModal}
          update={getAssigned}
        />
      )}
    </>
  );
}
