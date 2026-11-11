import { Plus } from "lucide-react";
import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import Card from "../../../components/ui/Card";

import Modal from "../../../components/ui/Modal";
import PageHeader from "../../../components/ui/PageHeader";
import { useNavigate } from "react-router-dom";
import styles from "./createProgramPage.module.css";
import Loading from "../../../components/ui/Loading";
import { toast } from "react-toastify";
import CreateProgramModal from "../components/createProgramModel";
import { NotepadTextDashed, Trash, Eye ,SquarePen } from "lucide-react";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";

export default function createProgramPage() {
  const { t } = useTranslation("hr");

  const [showAdd, setShowAdd] = useState(false);
  const [deleteModel, setDeleteModel] = useState(false);
  const [deleteID, setDeleteId] = useState("");
  const [hanselModel, setHandelModel] = useState(false);

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

  const openProgram = (id) => {
  navigate(`/supervisor/program/details/${id}`);
};
  // console.log(myPrograms)
  if (error) {
    const message = error.response?.data?.message || error.message || "error";

    toast.error(Array.isArray(message) ? message.join("، ") : message);
  }

  const handelProgram = async (id) => {
    await getProgram(id);
    // console.log(program)
    setHandelModel(true);
  };

  const handelDelete = async (id) => {
    setDeleteModel(true);
    setDeleteId(id);
  };

  useEffect(() => {
    getMyPrograms();
  }, [getMyPrograms]);
  return loading ? (
    <Loading />
  ) : (
    <>
      <PageHeader
        title={t("program.listTitle")}
        action={
          <Button icon={Plus} onClick={() => setShowAdd(true)}>
            {t("program.add")}
          </Button>
        }
      />

      <div className={styles.grid}>
        {myPrograms.map((p) => (
          <Card key={p.id} className={styles.card}>
            <div onClick={()=>openProgram(p.id)} style={{ display: "flex", alignItems: "center", gap: "10px" ,cursor:"pointer" }}>
              <NotepadTextDashed color="#e60000" />
              <h3>{p.title}</h3>
            </div>
            <p style={{ fontSize: "12px", color: "#6b7280" }}>
              {p.description}
            </p>

            <p style={{ fontSize: "12px", color: "#6b7280" }}>{p.deadline}</p>
            <hr />

            <div
              style={{
                display: "flex",
                alignItems: "center",
                gap: "10px",
                justifyContent: "end",
                marginTop: "auto",
              }}
            >
              <Button
                onClick={() => handelProgram(p.id)}
                loading={loadingAction == p.id}
                icon={SquarePen}
                size="sm"
                variant=""
              ></Button>

              <Button
                onClick={() => handelDelete(p.id)}
                loading={""}
                icon={Trash}
                size="sm"
              ></Button>
            </div>
          </Card>
        ))}
      </div>

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
            }}
          >
            <Button
              onClick={() => setDeleteModel(false)}
              loading={""}
              size="sm"
              variant=""
            >
              {t("action.cancel", { ns: "common" })}
            </Button>

            <Button
              onClick={async () => {
                toast.success(await deleteProgram(deleteID));
                setDeleteModel(false);
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

