import { useTranslation } from "react-i18next";
import {  ClipboardList, CalendarDays } from "lucide-react";
import styles from "./TrainingProgram.module.css";
import { useEffect } from "react";
import Loading from "../../../../components/ui/Loading"
import TrainingProgramTaskCard from "./TrainingProgramTaskCard"
import TrainingProgramHooks from "../../../../hooks/TraineeHooks/trainingProgramHooks";
export default function TrainingProgram() {
  const { t } = useTranslation("common");

const {
        loadingAction,
        trainingProgram,
        trainingProgramPhases,
        controller,
        getTrainingProgramInfo,
        checkIsCurrentTrainee,
    } = TrainingProgramHooks();
 useEffect(() => {
  getTrainingProgramInfo();
  return () => {
    controller.abort();
  };
}, []);

  return loadingAction ? (
    <Loading />
  ) : (
    <>
      <main className={styles.page} dir="auto">
        <header className={styles.programHeader}>
          <div className={styles.programIcon}>
            <ClipboardList size={28} />
          </div>
          <div className={styles.programInfo}>
            <h1>{trainingProgram.title}</h1>
            <p>{trainingProgram.description}</p>
            {trainingProgram.deadline && (
              <div className={styles.deadline}>
                <CalendarDays size={16} />
                <span>
                  {t("program.deadline")}: {trainingProgram.deadline}
                </span>
              </div>
            )}
          </div>
        </header>
        <section className={styles.phases}>
          <div className={styles.sectionHeader}>
            <h2>{t("program.phasesTitle")}</h2>
            <span className={styles.count}>{trainingProgramPhases.length}</span>
          </div>
          {trainingProgramPhases.length === 0 ? (
            <div className={styles.empty}>{t("program.noPhases")}</div>
          ) : (
            trainingProgramPhases.map((phase, index) => (
              <article
                className={styles.phase}
                key={phase._id || phase.id || index}
              >
                <div className={styles.phaseHeader}>
                  <div className={styles.phaseInfo}>
                    <span className={styles.phaseNumber}>{index + 1}</span>
                    <div>
                      <h3>{phase.name}</h3>
                      <p>{phase.description || t("program.noDescription")}</p>
                    </div>
                  </div>
                </div>
                <div className={styles.tasks}>
                  {(phase.tasks || []).length === 0 ? (
                    <p className={styles.noTasks}>{t("task.noTasks")}</p>
                  ) : (
                    phase.tasks.map((task) => (
                      <TrainingProgramTaskCard task={task} key={task.id} available={true} />
                    ))
                  )}
                </div>
              </article>
            ))
          )}
        </section>
      </main>
    </>
  );
}
