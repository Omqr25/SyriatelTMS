import { Plus, NotepadTextDashed, Trash, SquarePen } from "lucide-react";
import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import Card from "../../../components/ui/Card";
import Modal from "../../../components/ui/Modal";
import PageHeader from "../../../components/ui/PageHeader";
import { useNavigate } from "react-router-dom";
import styles from "./ProgramsPage.module.css";
import Loading from "../../../components/ui/Loading";
import { toast } from "react-toastify";
import CreateProgramModal from "../components/createProgramModel";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";
import CreateTaskModal from "../components/CreateTaskModal";
import TaskCard from "../components/TaskCard";

export default function ProgramsPage() {
  const { t } = useTranslation("hr");

  const [showAdd, setShowAdd] = useState(false);
  const [deleteModel, setDeleteModel] = useState(false);
  const [deleteID, setDeleteId] = useState("");
  const [hanselModel, setHandelModel] = useState(false);

  const [taskPhase, setTaskPhase] = useState(null);

  const navigate = useNavigate();
  const {
    getMyPrograms,
    myPrograms,
    error,
    loading,
    loadingAction,
    getProgram,
    program,
    deleteProgram,
  } = useAssigned();

  const firstProgramId = myPrograms?.[0]?.id;
  useEffect(() => { getMyPrograms().catch(() => {}); }, [getMyPrograms]);
  useEffect(() => {
    if (firstProgramId) getProgram(firstProgramId);
  }, [firstProgramId, getProgram]);

  useEffect(() => {
    if (!error) return;
    const message = error.response?.data?.message || error.message || "error";
    toast.error(Array.isArray(message) ? message.join("، ") : message);
  }, [error]);

  const handelProgram = async (id) => {
    await getProgram(id);
    setHandelModel(true);
  };

  const handelDelete = async (id) => {
    setDeleteModel(true);
    setDeleteId(id);
  };

  const currentProgram = firstProgramId
    ? (program?.id === firstProgramId ? program : myPrograms[0])
    : null;

  return loading ? (
    <Loading />
  ) : (
    <>
      <PageHeader
        title={currentProgram ? currentProgram.title : t("program.listTitle")}
        action={
          !currentProgram && (
            <Button icon={Plus} onClick={() => setShowAdd(true)}>
              {t("program.add")}
            </Button>
          )
        }
      />

      {currentProgram ? (
        <div className={styles.programDetailsContainer}>
          {/* Program Details Card */}
          <Card
            className={styles.card}
            style={{ marginBottom: "20px", padding: "20px" }}
          >
            <div
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
              }}
            >
              <div
                style={{ display: "flex", alignItems: "center", gap: "10px" }}
              >
                <NotepadTextDashed color="#e60000" size={28} />
                <h2>{currentProgram.title}</h2>
              </div>
              <div style={{ display: "flex", gap: "10px" }}>
                <Button
                  onClick={() => handelProgram(currentProgram.id)}
                  icon={SquarePen}
                  size="sm"
                  variant=""
                >
                  {t("action.edit", { ns: "common", defaultValue: "Edit" })}
                </Button>
                <Button
                  onClick={() => handelDelete(currentProgram.id)}
                  icon={Trash}
                  size="sm"
                >
                  {t("action.delete", { ns: "common", defaultValue: "Delete" })}
                </Button>
              </div>
            </div>
            <p
              style={{ fontSize: "14px", color: "#4b5563", marginTop: "10px" }}
            >
              {currentProgram.description}
            </p>
            <p style={{ fontSize: "13px", color: "#6b7280", marginTop: "5px" }}>
              <strong>
                {t("program.deadline", { defaultValue: "Deadline" })}:
              </strong>{" "}
              {currentProgram.deadline}
            </p>
          </Card>

          {/* Milestones & Tasks Section */}
          <div className={styles.milestonesSection}>
            <h3 style={{ marginBottom: "15px" }}>
              {t("program.milestones", { defaultValue: "Milestones & Tasks" })}
            </h3>
            {currentProgram.phases && currentProgram.phases.length > 0 ? (
              currentProgram.phases.map((milestone) => (
                <Card
                  key={milestone.id}
                  style={{ marginBottom: "15px", padding: "15px" }}
                >
                  <div
                    style={{
                      display: "flex",
                      justifyContent: "space-between",
                      alignItems: "center",
                    }}
                  >
                    <h4>{milestone.name}</h4>
                    <Button
                      icon={Plus}
                      size="sm"
                      onClick={() => setTaskPhase(milestone)}
                    >
                      {t("task.add", { defaultValue: "Add Task" })}
                    </Button>
                  </div>
                  <p
                    style={{
                      fontSize: "13px",
                      color: "#6b7280",
                      margin: "5px 0 15px 0",
                    }}
                  >
                    {milestone.description}
                  </p>

                  {/* Tasks List for this Milestone */}
                  <div
                    style={{
                      marginTop: "10px",
                      paddingLeft: "15px",
                      borderLeft: "2px solid #e5e7eb",
                    }}
                  >
                    <h5
                      style={{
                        fontSize: "13px",
                        color: "#374151",
                        marginBottom: "8px",
                      }}
                    >
                      {t("task.list", { defaultValue: "Tasks" })}
                    </h5>
                    {milestone.tasks?.length > 0 ? (
                      milestone.tasks.map(task => <TaskCard key={task.id} task={task} />)
                    ) : (
                      <p style={{ fontSize: "12px", color: "#9ca3af" }}>
                        {t("task.noTasks", {
                          defaultValue: "No tasks found for this milestone.",
                        })}
                      </p>
                    )}
                  </div>
                </Card>
              ))
            ) : (
              <p>
                {t("program.noMilestones", {
                  defaultValue: "No milestones available for this program.",
                })}
              </p>
            )}
          </div>
        </div>
      ) : (
        <div style={{ textAlign: "center", padding: "40px" }}>
          <p>
            {t("program.noProgram", {
              defaultValue: "No active program found.",
            })}
          </p>
          <Button
            icon={Plus}
            onClick={() => setShowAdd(true)}
            style={{ marginTop: "15px" }}
          >
            {t("program.add")}
          </Button>
        </div>
      )}

      {taskPhase && (
        <CreateTaskModal phase={taskPhase} programId={currentProgram.id}
          onClose={() => setTaskPhase(null)}
          onUpdated={() => getProgram(currentProgram.id, true)} />
      )}

      {showAdd && (
        <CreateProgramModal
          onClose={() => setShowAdd(false)}
          onSubmit={""}
          onSuccess={() => getMyPrograms(true)}
        />
      )}

      {hanselModel && (
        <CreateProgramModal
          onClose={() => setHandelModel(false)}
          onSubmit={""}
          loading={""}
          program2={program}
          onSuccess={() => getProgram(program.id, true)}
        />
      )}

      {deleteModel && (
        <Modal
          onClose={() => setDeleteModel(false)}
          title={t("modal.deleteTitle", { ns: "common" })}
        >
          <p>{t("modal.description", { ns: "common" })}</p>

          <div
            style={{
              display: "flex",
              alignItems: "center",
              gap: "10px",
              justifyContent: "end",
              marginTop: "20px",
            }}
          >
            <Button onClick={() => setDeleteModel(false)} size="sm" variant="">
              {t("action.cancel", { ns: "common" })}
            </Button>

            <Button
              onClick={async () => {
                try {
                  toast.success(await deleteProgram(deleteID));
                } catch { return; }
                setDeleteModel(false);
                getMyPrograms().catch(() => {});
              }}
              loading={loadingAction == deleteID}
              size="sm"
            >
              {t("modal.delete", { ns: "common" })}
            </Button>
          </div>
        </Modal>
      )}
    </>
  );
}
