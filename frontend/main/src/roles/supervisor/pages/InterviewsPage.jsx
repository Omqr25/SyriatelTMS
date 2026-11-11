import React, { useState, useEffect } from "react";

import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import Table from "../../../components/ui/Table";
import styles from "./Pages.module.css";
import { toast } from "react-toastify";
import Loading from "../../../components/ui/Loading";
import Modal from "../../../components/ui/Modal";
import PageHeader from "../../../components/ui/PageHeader.jsx";
import {
  getMyInterview,
  SetInterviewResult,
} from "../../../Api/SupervisorApi/supervisorApi.js";
import { Check } from "lucide-react";
import { Field, Input } from "../../../components/ui/Field.jsx";
import form from "../../../components/ui/Form.module.css";

export default function InterviewsPage() {
  const { t } = useTranslation("hr");

  const [loading, setLoading] = useState(true);
  const [interviews, setInterviews] = useState([]);
  const [interview, setInterview] = useState(null);

  const [viewNote, setViewNote] = useState(false);
  const [note, setNote] = useState("");
  const [loadingAccept, setLoadingAccept] = useState("");
  const [refreshing, setRefreshing] = useState(false);

  const getAllInterview = async (silent = false) => {
    if (silent) {
      setRefreshing(true);
    } else {
      setLoading(true);
    }
    try {
      const data = await getMyInterview();
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

  const handelViewNote = (i) => {
    setViewNote(true);
    setInterview(i);
  };

  const closeModal = () => {
    setViewNote(false);
  };

  useEffect(() => {
    getAllInterview();
  }, []);

  const handleSubmit = async (e) => {
    setLoadingAccept(interview.id);
    e.preventDefault();
    try {
      const formData = new FormData(e.currentTarget);
      // console.log(formData.get("evaluation"));
      const body = {
        evaluation: +formData.get("evaluation"),
        interview_note: formData.get("interview_note"),
      };
      // console.log("body",formData);
      // console.log(interview)
      const data = await SetInterviewResult(interview.id, body);
      console.log(data);
      toast.success(data.message || "success");
      getAllInterview(true)
      setViewNote(false);
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "error in interview";
      // console.log(message)
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      setLoadingAccept("");
    }
  };

  const columns = [
    {
      key: "applicant",
      header: t("interviews.applicant"),
      render: (i) => (
        <strong className={styles.strong}>{i.application.full_name}</strong>
      ),
    },

    {
      key: "dateTime",
      header: t("interviews.dateTime"),
      render: (i) => i.time,
    },
    { key: "location", header: t("interviews.location") },

    {
      key: "actions",
      header: t("label.actions", { ns: "common" }),
      render: (i) =>
        i.application.status == "for interview" ? (
          <Button
            size="sm"
            variant="success"
            onClick={() => handelViewNote(i)}
            icon={Check}
          ></Button>
        ) : (
          t("status.applicant.interviewed", { ns: "common" })
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
          <form className={form.form} onSubmit={handleSubmit}>
            <Field label={t("label.rating", { ns: "common" })}>
              <Input type="number" name="evaluation" max={100} required />
            </Field>

            <Field label={t("label.note", { ns: "common" })}>
              <Input type="textArea" row={3} name="interview_note" max={100} />
            </Field>

            <Button type="submit" loading={loadingAccept == interview.id}>
              {t("action.save", { ns: "common" })}
            </Button>
          </form>
        </Modal>
      )}
    </>
  );
}
