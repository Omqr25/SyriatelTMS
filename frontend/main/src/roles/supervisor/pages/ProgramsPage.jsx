import { Plus, NotepadTextDashed, Trash, SquarePen } from "lucide-react";
import { useEffect, useState, useCallback } from "react";
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
import api from "../../../Api/axios";

export default function ProgramsPage() {
  const { t } = useTranslation("hr");

  const [showAdd, setShowAdd] = useState(false);
  const [deleteModel, setDeleteModel] = useState(false);
  const [deleteID, setDeleteId] = useState("");
  const [hanselModel, setHandelModel] = useState(false);

  // Task Management States
  const [showAddTaskModal, setShowAddTaskModal] = useState(false);
  const [selectedMilestoneId, setSelectedMilestoneId] = useState(null);
  const [milestoneTasks, setMilestoneTasks] = useState({}); // { [milestoneId]: [tasks] }
  const [taskLoading, setTaskLoading] = useState(false);

  // Form state for creating a task
  const [taskForm, setTaskForm] = useState({
    name: "",
    description: "",
    trainee_id: "",
    deadline: "",
    priority: "medium", // Add default priority
    attachments: [],
  });

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

  // Fetch tasks for each milestone when program data is available
  const fetchTasksForMilestones = useCallback(async (milestones) => {
    if (!milestones || milestones.length === 0) return;
    const tasksMap = {};
    try {
      for (const milestone of milestones) {
        const response = await api.get(`/task/${milestone.id}/tasks`);
        tasksMap[milestone.id] = response.data.data || response.data;
      }
      setMilestoneTasks(tasksMap);
    } catch (err) {
      console.error("Failed to fetch milestone tasks", err);
    }
  }, []);

  useEffect(() => {
    getMyPrograms();
  }, [getMyPrograms]);

  useEffect(() => {
    if (myPrograms && myPrograms.length > 0) {
      const activeProgram = myPrograms[0]; // Displaying the existing program
      getProgram(activeProgram.id).then((res) => {
        if (res?.phases) {
          fetchTasksForMilestones(res.phases);
        }
      });
    }
  }, [myPrograms, getProgram, fetchTasksForMilestones]);

  if (error) {
    const message = error.response?.data?.message || error.message || "error";
    toast.error(Array.isArray(message) ? message.join("، ") : message);
  }

  const handelProgram = async (id) => {
    await getProgram(id);
    setHandelModel(true);
  };

  const handelDelete = async (id) => {
    setDeleteModel(true);
    setDeleteId(id);
  };

  const handleOpenAddTask = (milestoneId) => {
    setSelectedMilestoneId(milestoneId);
    setTaskForm({
      name: "",
      description: "",
      trainee_id: "",
      deadline: "",
      priority: 5,
      attachments: [],
    });
    setShowAddTaskModal(true);
  };

  const handleCreateTask = async (e) => {
    e.preventDefault();
    if (!selectedMilestoneId) return;
    setTaskLoading(true);
    try {
      await api.post(`/task/${selectedMilestoneId}/tasks`, taskForm);
      toast.success(
        t("task.createdSuccessfully", {
          defaultValue: "Task created successfully",
        }),
      );
      setShowAddTaskModal(false);
      // Refresh tasks for this milestone
      const response = await api.get(`/task/${selectedMilestoneId}/tasks`);
      setMilestoneTasks((prev) => ({
        ...prev,
        [selectedMilestoneId]: response.data.data || response.data,
      }));
    } catch (err) {
      const msg = err.response?.data?.message || err.message;
      toast.error(msg);
    } finally {
      setTaskLoading(false);
    }
  };

  const currentProgram =
    program || (myPrograms && myPrograms.length > 0 ? myPrograms[0] : null);

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
                      onClick={() => handleOpenAddTask(milestone.id)}
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
                    {milestoneTasks[milestone.id] &&
                    milestoneTasks[milestone.id].length > 0 ? (
                      milestoneTasks[milestone.id].map((task) => (
                        <div
                          key={task.id}
                          style={{
                            background: "#f9fafb",
                            padding: "10px",
                            borderRadius: "6px",
                            marginBottom: "8px",
                            display: "flex",
                            justifyContent: "space-between",
                            alignItems: "center",
                          }}
                        >
                          <div>
                            <strong style={{ fontSize: "13px" }}>
                              {task.name}
                            </strong>
                            <p
                              style={{
                                fontSize: "12px",
                                color: "#6b7280",
                                margin: "2px 0 0 0",
                              }}
                            >
                              {task.description}
                            </p>
                            {task.trainee && (
                              <span
                                style={{ fontSize: "11px", color: "#2563eb" }}
                              >
                                Assignee:{" "}
                                {task.trainee.user?.name || task.trainee.id}
                              </span>
                            )}
                          </div>
                          <div
                            style={{
                              display: "flex",
                              alignItems: "center",
                              gap: "8px",
                            }}
                          >
                            <span
                              style={{
                                fontSize: "11px",
                                padding: "2px 8px",
                                borderRadius: "12px",
                                background:
                                  task.status === "completed"
                                    ? "#d1fae5"
                                    : "#fee2e2",
                                color:
                                  task.status === "completed"
                                    ? "#065f46"
                                    : "#991b1b",
                              }}
                            >
                              {task.status}
                            </span>
                          </div>
                        </div>
                      ))
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

      {/* Add Task Modal */}
      {showAddTaskModal && (
        <Modal
          onClose={() => setShowAddTaskModal(false)}
          title={t("task.addTitle", { defaultValue: "Add New Task" })}
        >
          <form onSubmit={handleCreateTask}>
            <div style={{ marginBottom: "12px" }}>
              <label
                style={{
                  display: "block",
                  fontSize: "13px",
                  marginBottom: "4px",
                }}
              >
                {t("task.title", { defaultValue: "Title" })}
              </label>
              <input
                type="text"
                required
                value={taskForm.name}
                onChange={(e) =>
                  setTaskForm({ ...taskForm, name: e.target.value })
                }
                style={{
                  width: "100%",
                  padding: "8px",
                  borderRadius: "4px",
                  border: "1px solid #d1d5db",
                }}
              />
            </div>
            <div style={{ marginBottom: "12px" }}>
              <label
                style={{
                  display: "block",
                  fontSize: "13px",
                  marginBottom: "4px",
                }}
              >
                {t("task.description", { defaultValue: "Description" })}
              </label>
              <textarea
                value={taskForm.description}
                onChange={(e) =>
                  setTaskForm({ ...taskForm, description: e.target.value })
                }
                style={{
                  width: "100%",
                  padding: "8px",
                  borderRadius: "4px",
                  border: "1px solid #d1d5db",
                }}
              />
            </div>
            <div style={{ marginBottom: "12px" }}>
              <label
                style={{
                  display: "block",
                  fontSize: "13px",
                  marginBottom: "4px",
                }}
              >
                {t("task.traineeId", { defaultValue: "Trainee ID" })}
              </label>
              <input
                type="number"
                required
                value={taskForm.trainee_id}
                onChange={(e) =>
                  setTaskForm({ ...taskForm, trainee_id: e.target.value })
                }
                style={{
                  width: "100%",
                  padding: "8px",
                  borderRadius: "4px",
                  border: "1px solid #d1d5db",
                }}
              />
            </div>

            <div style={{ marginBottom: "12px" }}>
              <label
                style={{
                  display: "block",
                  fontSize: "13px",
                  marginBottom: "4px",
                }}
              >
                Priority (1-10)
              </label>

              <input
                type="number"
                min="1"
                max="10"
                step="1"
                required
                value={taskForm.priority}
                onChange={(e) =>
                  setTaskForm({
                    ...taskForm,
                    priority: e.target.value,
                  })
                }
                style={{
                  width: "100%",
                  padding: "8px",
                  borderRadius: "4px",
                  border: "1px solid #d1d5db",
                }}
              />
            </div>

            <div style={{ marginBottom: "15px" }}>
              <label
                style={{
                  display: "block",
                  fontSize: "13px",
                  marginBottom: "4px",
                }}
              >
                {t("task.deadline", { defaultValue: "Deadline" })}
              </label>
              <input
                type="datetime-local"
                required
                value={taskForm.deadline}
                onChange={(e) =>
                  setTaskForm({ ...taskForm, deadline: e.target.value })
                }
                style={{
                  width: "100%",
                  padding: "8px",
                  borderRadius: "4px",
                  border: "1px solid #d1d5db",
                }}
              />
            </div>
            <div
              style={{ display: "flex", justifyContent: "end", gap: "10px" }}
            >
              <Button
                type="button"
                variant=""
                onClick={() => setShowAddTaskModal(false)}
              >
                {t("action.cancel", { ns: "common" })}
              </Button>
              <Button type="submit" loading={taskLoading}>
                {t("action.save", { ns: "common", defaultValue: "Save" })}
              </Button>
            </div>
          </form>
        </Modal>
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
          onSuccess={() => getMyPrograms(true)}
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
                toast.success(await deleteProgram(deleteID));
                setDeleteModel(false);
                getMyPrograms();
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
