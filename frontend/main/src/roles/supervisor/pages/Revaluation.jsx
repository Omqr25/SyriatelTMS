import { useTranslation } from "react-i18next";
import Table from "../../../components/ui/Table";
import styles from "./Pages.module.css";

import Button from "../../../components/ui/Button";
import Loading from "../../../components/ui/Loading";
import { useEffect, useState } from "react";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";
import EvaluationModal from "../components/EvaluationModal";
export default function Revaluation () {
  const { t } = useTranslation("hr");

  const[evaluationModal,setEvaluationModal ]=useState(false)
  const { getReevaluationRequests, ReevaluationRequests,loading ,loadingAction,createReEvaluation} = useAssigned();
  const [request,setRequest]=useState({})

  const handelCreateEvaluation=(req)=>{
    setRequest(req)
    setEvaluationModal(true)
  }
  // console.log("my", ReevaluationRequests);
  useEffect(() => {
    getReevaluationRequests()
  }, []);
  const columns = [
    {
      key: "id",
      header: t("trainees.traineeId"),
      render: (tr) => <span className={styles.id}>#{tr.trainee.id}</span>,
    },
    {
      key: "name",
      header: t("label.name", { ns: "common" }),
      render: (tr) => <strong className={styles.strong}>{tr.trainee.name}</strong>,
    },
    { key: "status", header: t("label.status", { ns: "common" }) },
    {
      key: "reason",
      header: t("label.reason", { ns: "common" }),
      render: (tr) => tr.reason ?? "—",
    },
    {
      key: "score",
      header: t("evaluation.score", { ns: "hr" }),
      render: (tr) => tr?.evaluation?.score ?? "—",
    },
    
      {
      key: "action",
      header: t("label.actions", { ns: "common" }),
      render: (tr) => (<Button size="sm" onClick={()=>handelCreateEvaluation(tr)} > {t("evaluation.viewAndREvaluation")} </Button>),
    },
 
  ];

  return loading ? (
    <Loading />
  ) : (
    <>

        <Table
      columns={columns}
      rows={ReevaluationRequests}
      emptyText={t("trainees.empty")}
    />

    {evaluationModal &&(
      <EvaluationModal evaluation={request.evaluation}  loading={loadingAction} trId={request.id} onSubmit={createReEvaluation} onClose={()=> setEvaluationModal(false)}/>
    )}
    
    </>

  
  );
}
