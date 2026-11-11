import React, { useState, useEffect } from "react";

import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import Table from "../../../components/ui/Table";
import StatusBadge from "../components/StatusBadge";
import styles from "./Pages.module.css";
import { getAllInterviewApi } from "../../../Api/HrApi/interviewApi.js";
import { toast } from "react-toastify";
import Loading from "../../../components/ui/Loading";
import { Notebook, UserPlus } from "lucide-react";
import Modal from "../../../components/ui/Modal";
import { acceptApplicant } from "../../../Api/HrApi/applicantsApi.js";
import PageHeader from "../../../components/ui/PageHeader.jsx";
import { activeTraineeAccountApi } from "../../../Api/HrApi/traineesApi.js";

export default function InterviewsPage() {
  const { t } = useTranslation("hr");

  const [loading, setLoading] = useState(true);
  const [interviews, setInterviews] = useState([]);
  const [viewNote, setViewNote] = useState(false);
  const [note, setNote] = useState("");
  const [loadingAccept, setLoadingAccept] = useState("");
  const [refreshing, setRefreshing] = useState(false);
  const [loadingActive, setLoadingActive] = useState(false);

  const getAllInterview = async (silent = false) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }
    try {
      const data = await getAllInterviewApi();
      setInterviews(data.data);
      // consol.log(data);
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "error in interview";
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      if (silent) {
        setRefreshing(false);
      } else {
        setLoading(false);
      }
    }
  };

  const activeAccount = async (id) => {
    setLoadingActive(id);
    try {
      const data = await activeTraineeAccountApi(id);
      toast.success(data.message);
      await getAllInterview(true);
    } catch (error) {
      const message= error.response?.data?.message || error.message || "error in interview";
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      setLoadingActive(false);
    }
  };
  const handelViewNote = (i) => {
    setViewNote(true);
    setNote(i.interview_note);
  };

  const closeModal = () => {
    setViewNote(false);
  };

  useEffect(() => {
    getAllInterview();
  }, []);

  const handleAccept = async (id) => {
    setLoadingAccept(id);
    try {
      const data = await acceptApplicant(id);
      console.log(data.message);
      toast.success(data.message);
      setLoadingAccept("");
      await getAllInterview(true);
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "error in interview";
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      setLoadingAccept("");
    }
  };

  const columns = [
    {
      key: "applicant",
      header: t("interviews.applicant"),
      render: (i) => <strong className={styles.strong}>{i.name}</strong>,
    },
    {
      key: "supervisor",
      header: t("interviews.supervisor"),
      render: (i) => i.supervisor.name,
    },
    {
      key: "dateTime",
      header: t("interviews.dateTime"),
      render: (i) => i.time,
    },
    { key: "location", header: t("interviews.location") },
    {
      key: "status",
      header: t(`label.status`, { ns: "common" }),
      render: (i) => <StatusBadge kind="applicant" status={i.status} />,
    },
    {
      key: "rating",
      header: t("interviews.rating"),
      render: (i) =>
        i.evaluation ? (
          <span className={styles.good}>{i.evaluation} %</span>
        ) : (
          <span className={styles.muted}>{t("interviews.pendingRating")}</span>
        ),
    },
    {
      key: "actions",
      header: t("label.actions", { ns: "common" }),
      render: (i) =>
        i.status == "interviewed" ? (
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              gap: "8px",
            }}
          >
            <Button
              size="sm"
              variant="success"
              loading={loadingAccept == i.id}
              onClick={() => handleAccept(i.id)}
            >
              {t("interviews.finalAccept")}
            </Button>

            {i.interview_note !== null && (
              <Button
                size="sm"
                variant=""
                icon={Notebook}
                // loading={isBusy(`accept-${i.id}`)}
                onClick={() => handelViewNote(i)}
              ></Button>
            )}
          </div>
        ) :  i.status == "accepted" && (
          <div
            style={{
              display: "flex",
              alignItems: "center",
              justifyContent: "center",
              gap: "8px",
            }}
          >
            <Button
              size="sm"
              variant="success"
              icon={UserPlus}
              loading={loadingActive==i.id}
              onClick={() => activeAccount(i.id)}
            ></Button>
          </div>
        ),
    },
  ];

  return loading ? (
    <Loading />
  ) : (
    <>
      <PageHeader title={t("nav.interviews")} update={refreshing} />

      <Table
        columns={columns}
        rows={interviews}
        emptyText={t("interviews.empty")}
      />

      {viewNote && (
        <Modal title={t("label.note", { ns: "common" })} onClose={closeModal}>
          <p>{note}</p>
        </Modal>
      )}
    </>
  );
}
