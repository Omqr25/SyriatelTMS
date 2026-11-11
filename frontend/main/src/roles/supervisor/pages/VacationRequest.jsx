import { useTranslation } from "react-i18next";
import Table from "../../../components/ui/Table";

import styles from "./Pages.module.css";

import Button from "../../../components/ui/Button";
import Loading from "../../../components/ui/Loading";
import { useEffect, useState } from "react";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";
import { Check, X } from "lucide-react";
import { AcceptOrRejectVacationRequestApi } from "../../../Api/SupervisorApi/supervisorApi";
import { toast } from "react-toastify";
import StatusBadge from "../../hr/components/StatusBadge";
export default function VacationRequest() {
  const { t } = useTranslation("hr");

  const { loading, loadingAction, getVacationRequests, VacationRequests } =
    useAssigned();

  const [traineeId, setTraineeId] = useState("");

  const handelCreateEvaluation = async(id,decision) => {
    setTraineeId(`${id}-${decision}`);
    
    try {
        const data =await AcceptOrRejectVacationRequestApi(id,decision);
        toast.success(data.message)
        await getVacationRequests(true)
    } catch (error) {

      const message =
        error.response?.data?.message || error.message || "error in interview";
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    }finally{
        setTraineeId(" ");
    }
      
    

  };
 
  console.log(VacationRequests)
  useEffect(() => {
    getVacationRequests();
  }, []);
  const columns = [
    {
      key: "name",
      header: t("label.name", { ns: "common" }),
      render: (tr) => (
        <strong className={styles.strong}>{tr.trainee.name}</strong>
      ),
    },

        {
      key: "status",
     header: t("label.status", { ns: "common" }),
      render: (tr) => (
        <StatusBadge kind="applicant" status={tr.status} />
      ),
    },

    {
      key: "reason",
      header: t("label.reason", { ns: "common" }),
      render: (tr) => tr.reason,
    },

    {
      key: "startDate",
      header: t("task.startDate", { ns: "common" }),
      render: (tr) => tr.start_date,
    },

    {
      key: "deadline",
      header: t("task.deadline", { ns: "common" }),
      render: (tr) => tr.end_date,
    },
    {
      key: "action",
      header: t("label.actions", { ns: "common" }),
      render: (tr) => tr.status =="pending" ? (
        <div className="flexCenter gap8">
          <Button
            variant="success"
            icon={Check}
            size="sm"
            onClick={() => handelCreateEvaluation(tr.id,"accepted")}
            loading ={traineeId==`${tr.id}-accepted`}
          ></Button>

          <Button
            icon={X}
            size="sm"
            onClick={() => handelCreateEvaluation(tr.id,"rejected")}
            loading ={traineeId==`${tr.id}-rejected`}
          ></Button>
        </div>
      ) : "----",
    },
  ];

  return loading ? (
    <Loading />
  ) : (
    <>
      <Table
        columns={columns}
        rows={VacationRequests}
        emptyText={t("trainees.empty")}
      />
    </>
  );
}
