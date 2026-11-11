import { useTranslation } from "react-i18next";
import Table from "../../../components/ui/Table";

import styles from "./Pages.module.css";
import { useEffect, useState } from "react";
import Loading from "../../../components/ui/Loading";
import { getAllEvaluationApi } from "../../../Api/HrApi/hrApi";
import { toast } from "react-toastify";
import Button from "../../../components/ui/Button";
import EvaluationDetailsModal from "../components/EvaluationDetailsModal";

export default function EvaluationsPage() {
  const { t } = useTranslation("hr");

  const [loading, setLoading] = useState(false);
  const [trainees, setTrainees] = useState([]);
  const [viewDetails, setViewDetails] = useState(false);
  const [evaluation, setEvaluation] = useState(false);

  const handelViewDetails = (tr) => {
    setEvaluation(tr);
    setViewDetails(true)
  };

  const getAllEvaluations = async () => {
    setLoading(true);
    try {
      const data = await getAllEvaluationApi();
      setTrainees(data.data);
    } catch (error) {
      const message = error.response?.data?.message || error.message || "";
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };
  useEffect(() => {
    getAllEvaluations();
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
      key: "supervisor",
      header: t("assigned.supervisor", { ns: "hr" }),
      render: (tr) => tr.trainer.name,
    },
    {
      key: "department",
      header: t("label.department", { ns: "common" }),
      render: (tr) => tr.trainer.department.name,
    },
    { key: "score", header: t("evaluation.score") },
    {
      key: "action",
      header: t("label.actions", { ns: "common" }),
      render: (tr) => (
        <Button size="sm" variant="" onClick={() => handelViewDetails(tr)}>
          {" "}
          {t("action.viewDetails", { ns: "common" })}{" "}
        </Button>
      ),
    },
  ];

  return loading ? (
    <Loading />
  ) : (
    <>
        <Table columns={columns} rows={trainees} emptyText={t("trainees.empty")} />
    {
      viewDetails && (<EvaluationDetailsModal onClose={()=>setViewDetails(false)} evaluation={evaluation} />)
    }
    </>
    
  );
}
