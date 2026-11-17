import { useTranslation } from "react-i18next";
import Table from "../../../components/ui/Table";
import styles from "./Pages.module.css";

import Button from "../../../components/ui/Button";
import Loading from "../../../components/ui/Loading";
import { useEffect, useState } from "react";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";
import EvaluationModal from "../components/EvaluationModal";
import { getTraineeEvaluationsApi } from "../../../Api/SupervisorApi/supervisorApi";
export default function TraineesPage() {
  const { t } = useTranslation("hr");

  const[evaluationModal,setEvaluationModal ]=useState(false)
  const { myTrainees, loading, getMyTrainees,createEvaluation ,loadingAction} = useAssigned();
  const [traineeId,setTraineeId]=useState("")
  const [evaluated, setEvaluated] = useState({});

  useEffect(() => {
    let active = true;
    myTrainees.forEach(async (trainee) => {
      try {
        const result = await getTraineeEvaluationsApi(trainee.id);
        if (active) {
          setEvaluated((previous) => ({
            ...previous,
            [trainee.id]: previous[trainee.id] === true || result.data.length > 0,
          }));
        }
      } catch (error) {
        // Keep this button disabled when its evaluation status cannot be verified.
        console.error("Failed to load trainee evaluation status:", error);
      }
    });
    return () => { active = false; };
  }, [myTrainees]);

  const handleSaveEvaluation = async (id, body) => {
    const result = await createEvaluation(id, body);
    if (result) {
      setEvaluated((previous) => ({ ...previous, [id]: true }));
    }
    return result;
  };


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
      render: (tr) => (
        <Button
          size="sm"
          disabled={evaluated[tr.id] !== false}
          onClick={() => handelCreateEvaluation(tr.id)}
        >
          {t(evaluated[tr.id] === true ? "evaluation.evaluated" : "evaluation.evaluation")}
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
      rows={myTrainees}
      emptyText={t("trainees.empty")}
    />

    {evaluationModal &&(
      <EvaluationModal loading={loadingAction} trId={traineeId} onSubmit={handleSaveEvaluation} onClose={()=> setEvaluationModal(false)}/>
    )}
    
    </>

  
  );
}
