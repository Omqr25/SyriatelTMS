import { useTranslation } from "react-i18next";
import Table from "../../../components/ui/Table";
import styles from "./Pages.module.css";

import Button from "../../../components/ui/Button";
import Loading from "../../../components/ui/Loading";
import { useEffect, useState } from "react";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";
import EvaluationModal from "../components/EvaluationModal";
export default function TraineesPage() {
  const { t } = useTranslation("hr");

  const[evaluationModal,setEvaluationModal ]=useState(false)
  const { myTrainees, loading, getMyTrainees,createEvaluation ,loadingAction} = useAssigned();
  const [traineeId,setTraineeId]=useState("")

  const handelCreateEvaluation=(id)=>{
    setTraineeId(id)
    setEvaluationModal(true)
  }
  console.log("my", myTrainees);
  useEffect(() => {
    getMyTrainees();
  }, []);
  const columns = [
    {
      key: "id",
      header: t("trainees.traineeId"),
      render: (tr) => <span className={styles.id}>#{tr.id}</span>,
    },
    {
      key: "name",
      header: t("label.name", { ns: "common" }),
      render: (tr) => <strong className={styles.strong}>{tr.name}</strong>,
    },
    { key: "email", header: t("label.email", { ns: "common" }) },
    {
      key: "department",
      header: t("label.department", { ns: "common" }),
      render: (tr) => tr.department ?? "—",
    },
    {
      key: "supervisor",
      header: t("interviews.supervisor"),
      render: (tr) => tr.supervisor,
    },
      {
      key: "action",
      header: t("label.actions", { ns: "common" }),
      render: (tr) => (<Button size="sm" onClick={()=>handelCreateEvaluation(tr.id)} > {t("evaluation.evaluation")} </Button>),
    },
 
  ];

  return loading ? (
    <Loading />
  ) : (
    <>

        <Table
      columns={columns}
      rows={myTrainees}
      emptyText={t("trainees.empty")}
    />

    {evaluationModal &&(
      <EvaluationModal loading={loadingAction} trId={traineeId} onSubmit={createEvaluation} onClose={()=> setEvaluationModal(false)}/>
    )}
    
    </>

  
  );
}
