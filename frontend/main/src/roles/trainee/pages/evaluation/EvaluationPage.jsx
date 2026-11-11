import PieChart from "../../../../components/ui/PieChart.jsx"
import ProgressBar from "../../../../components/ui/PorgressBar.jsx"
import BlocArea from "../../../../components/ui/Bloc_Area.jsx"
import Button from "../../../../components/ui/Button.jsx"
import Modal from "../../../../components/ui/Modal.jsx"
import {Field,Input} from "../../../../components/ui/Field.jsx"
import styles from "./EvaluationPage.module.css";
import { useEffect } from "react";
import Loading from "../../../../components/ui/Loading";
import { useTranslation } from 'react-i18next';
import evaluationHooks from "../../../../hooks/TraineeHooks/evaluationHooks.js"
export default function EvaluationPage(){
    const {t}=useTranslation('trainee')
    
    const {reEvaluationForm,
        loadingAction,
        reEvaluationState,
        reEvaluationAvaliability,
        reEvaluationReason,
        evaluationScore,
        evaluationIndicators,
        fetchEvaluation,
        reEvaluationFunction,
        setRevaluationForm,
        closeTheForm,} = evaluationHooks()
    useEffect(() => {
    fetchEvaluation();
  }, []);

    const reEvaluationFormComponent = (
  <Modal
    title={t("evaluation.reEvaluationRequest")}
    onClose={() => closeTheForm()}
  >
    <form
      className={styles.modalContent}
      onSubmit={()=>reEvaluationFunction}
    >
      <Field
        label={t("evaluation.reason")}
        children={
          <Input
            required
            value={reEvaluationReason}
            onChange={(reason) => setReEvaluationReason(reason.target.value)}
          />
        }
      />
      <Button
        type="submit"
        loading={reEvaluationState}
        disabled={reEvaluationState}
      >
        {t("evaluation.send")}
      </Button>
    </form>
  </Modal>
);
    if(loadingAction) return <Loading />;
    return (
            <>
            <div className={styles.evaluation}>
                <section className={styles.finalEvaluation}>
                  <div className={styles.blocArea}>
                    <BlocArea
                      title={t("evaluation.generalEvaluation")}
                      children={[
                        <div className={styles.scoreSection} key="score">
                          <div className={styles.scoreHeader}>
                            <strong>{evaluationScore.score}%</strong>
                          </div>
                          <ProgressBar value={evaluationScore.score} />
                        </div>,
                        <p className={styles.feedback} key="feedback">
                          {evaluationScore.notice}.
                        </p>,
                      ]}
                    />
                  </div>
                </section>
                {evaluationIndicators.length >0 && <section className={styles.indicatorsSection}>
                <div className={styles.sectionHeader}>
                  <h3>{t("evaluation.performance")}</h3>
                </div>
                <div className={styles.pieCharts}>
                  {evaluationIndicators.map((indicator)=>(
                      <div key={indicator.id+indicator.name} className={styles.chartCard} >
                        <PieChart
                          data={{
                            name: t(`evaluation.${indicator.name}`),
                            value: indicator.score,
                            notice:
                              indicator.notice
                          }}
                        />
                  </div>
                  ))}
                </div>
              </section>}
              {reEvaluationAvaliability && <div className={styles.actions}>
                <Button
                  className={styles.reEvaluateRequest}
                  onClick={() => setRevaluationForm(true)}
                >
                  {t("evaluation.reEvaluate")}
                </Button>
              </div>}
              {reEvaluationForm && 
              reEvaluationFormComponent 
        }
  </div>
  </>
);
}