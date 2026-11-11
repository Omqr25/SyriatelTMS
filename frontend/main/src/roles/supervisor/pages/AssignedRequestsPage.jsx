import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import Table from "../../../components/ui/Table";

import styles from "./Pages.module.css";
import Loading from "../../../components/ui/Loading";
import { toast } from "react-toastify";
import { useEffect, useState } from "react";
import ApplicantDetailsModal from "../../hr/components/ApplicantDetailsModal";
import { Check } from "lucide-react";
import { getMyAssignApi } from "../../../Api/SupervisorApi/assignedApi";
import { X } from "lucide-react";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";
export default function AssignedRequestsPage() {
  const { t } = useTranslation("hr");

  const [applicant, setApplicant] = useState([]);
  const [actionLoading, setActionLoading] = useState("");
  const [assigned, setAssigned] = useState([]);
  const [loading, setLoading] = useState(true);
  const [modal, setModal] = useState(null); // { type: 'details' | 'assign' | 'schedule', applicant }

  const { error, loadingAction, TrainerAcceptOrRejectApplication } =
    useAssigned();

  const handelAcceptOrReject = async (id, type) => {
    try {
      const data = await TrainerAcceptOrRejectApplication(id, type);
      toast.success(data.message);
      await getMyApplicants(true)
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "error in interview";
      toast.error(message);
    }
  };

  const closeModal = () => {
    setModal(null);
  };

  const getMyApplicants = async (silent=false) => {
    if (silent) {

    }else{
    setLoading(true);
    }
    try {
      const data = await getMyAssignApi();
      console.log(data);
      setAssigned(data.data);
    } catch (error) {
      console.log(error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    getMyApplicants();
  }, []);

  const rows = assigned;

  const handleViewDetails = async (a) => {
    setActionLoading(a.id);
    // console.log(id)

    // const data =await getApplicant (id);
    setApplicant(a);
    // console.log(data)
    setActionLoading(null);
    if (!a) {
      return;
    }

    setModal({
      type: "details",
    });
  };

  const columns = [
    {
      key: "name",
      header: t("assigned.applicant"),
      render: (a) => <strong className={styles.strong}>{a.full_name}</strong>,
    },
    {
      key: "status",
      header: t("label.status", { ns: "common" }),
      render: (a) => t(`status.applicant.${a.status}`, { ns: "common" }),
    },
    {
      key: "gpa",
      header: t("label.gpa", { ns: "common" }),
      render: (a) => <span className={styles.accent}>{a.gpa}</span>,
    },
    {
      key: "actions",
      header: t("label.actions", { ns: "common" }),
      render: (a) => (
        <div className="flexCenter gap8 ">
          <Button
            size="sm"
            loading={a.id == actionLoading}
            onClick={() => handleViewDetails(a)}
            variant=""
          >
            {t("action.viewDetails", { ns: "common" })}
          </Button>

          <Button
            size="sm"
            loading={loadingAction==`accepted-${a.id}`}
            variant="success"
            onClick={() => { handelAcceptOrReject(a.id,"accepted") }}
            icon={Check}
          ></Button>

          <Button
            icon={X}
            size="sm"
            onClick={() => {handelAcceptOrReject(a.id,"rejected")}}
            loading={loadingAction==`rejected-${a.id}`}
          ></Button>
        </div>
      ),
    },
  ];

  return loading ? (
    <Loading />
  ) : (
    <>
      <Table columns={columns} rows={rows} emptyText={t("assigned.empty")} />

      {/* {modal?.type === "schedule" && applicant && (
            <ScheduleInterviewModal applicant={applicant} onClose={closeModal} />
          )} */}

      {modal?.type === "details" && applicant && (
        <ApplicantDetailsModal
          // applicant={modal.applicant}
          applicant={applicant}
          onClose={closeModal}
          // onAssign={() => open("assign", modal.applicant)}
          // onSchedule={() => open("schedule", modal.applicant)}
          // onAssign={() => openModal("assign")}
          onSchedule={() => openModal("schedule")}
        />
      )}
    </>
  );
}
