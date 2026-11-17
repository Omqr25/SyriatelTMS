import { useTranslation } from "react-i18next";
import { ClipboardList, CalendarDays } from "lucide-react";
import styles from "./TrainingProgram.module.css";
import { useState } from "react";
import Loading from "../../../../components/ui/Loading";
import TrainingProgramTaskCard from "./TrainingProgramTaskCard";
import TrainingProgramHooks from "../../../../hooks/TraineeHooks/trainingProgramHooks";
export default function TrainingProgram() {
  const { t } = useTranslation("common");
  const { t: tw } = useTranslation("taskWorkspace");
  const [scope, setScope] = useState("mine");

  const {
    loadingAction,
    trainingProgram,
    trainingProgramPhases,
    notFoundMessage,
    getTrainingProgramInfo,
    error,
  } = TrainingProgramHooks();

  if (loadingAction) {
    return <Loading />;
  }

  if (error)
    return (
      <main className={styles.page}>
        <p role="alert">{error}</p>
        <button onClick={getTrainingProgramInfo}>{tw("retry")}</button>
      </main>
    );

  if (notFoundMessage) {
    return (
      <main className={styles.page} dir="auto">
        <header className={styles.programHeader}>
          <div className={styles.programIcon}>
            <ClipboardList size={28} />
          </div>
          <div className={styles.programInfo}>
            <h1>{t("program.noProgram")}</h1>
            <p>{t("program.noProgramYet")}</p>
          </div>
        </header>
      </main>
    );
  }

  return (
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
        <div className={styles.filters} aria-label={tw("taskScope")}>
          {["mine", "team"].map((value) => (
            <button
              key={value}
              type="button"
              aria-pressed={scope === value}
              onClick={() => setScope(value)}
            >
              {tw(value === "mine" ? "myTasks" : "teamTasks")}
            </button>
          ))}
          <p>{tw("programHint")}</p>
        </div>
        <section className={styles.phases}>
          <div className={styles.sectionHeader}>
            <h2>{t("program.phasesTitle")}</h2>
            <span className={styles.count}>{trainingProgramPhases.length}</span>
          </div>
          {trainingProgramPhases.length === 0 ? (
            <div className={styles.empty}>{t("program.noPhases")}</div>
          ) : (
            trainingProgramPhases.map((phase, index) => {
              const tasks = (phase.tasks || []).filter(
                (task) => scope === "team" || task.is_mine,
              );
              const completed = tasks.filter(
                (task) => task.status === "completed",
              ).length;
              return (
                <details
                  open
                  className={styles.phase}
                  key={phase._id || phase.id || index}
                >
                  <summary className={styles.phaseHeader}>
                    <div className={styles.phaseInfo}>
                      <span className={styles.phaseNumber}>{index + 1}</span>
                      <div>
                        <h3>{phase.name}</h3>
                        <p>{phase.description || t("program.noDescription")}</p>
                      </div>
                    </div>
                    <span className={styles.phaseProgress}>
                      {tw("phaseProgress", { completed, total: tasks.length })}
                      <progress
                        aria-label={tw("progress")}
                        value={completed}
                        max={tasks.length || 1}
                      />
                    </span>
                  </summary>
                  <div className={styles.tasks}>
                    {tasks.length === 0 ? (
                      <p className={styles.noTasks}>{t("task.noTasks")}</p>
                    ) : (
                      tasks.map((task) => (
                        <TrainingProgramTaskCard
                          task={task}
                          key={task.id}
                          available={true}
                        />
                      ))
                    )}
                  </div>
                </details>
              );
            })
          )}
        </section>
      </main>
    </>
  );
}
