import { useTranslation } from "react-i18next";
import Table from "../../../components/ui/Table";

import styles from "./Pages.module.css";

import Button from "../../../components/ui/Button";
import Loading from "../../../components/ui/Loading";
import { useEffect, useState } from "react";
import useAttendanceList from "../../../hooks/useAttendanceList";
import Pagination from "../../../components/ui/Pagination";
import { Check, X } from "lucide-react";
import { AcceptOrRejectVacationRequestApi, getVacationRequestsApi } from "../../../Api/SupervisorApi/supervisorApi";
import { toast } from "react-toastify";
import StatusBadge from "../../hr/components/StatusBadge";
export default function VacationRequest() {
  const { t } = useTranslation(["hr", "trainee"]);
  const { load, loading, rows, paging } = useAttendanceList(getVacationRequestsApi);
  const [decision, setDecision] = useState(null);

  const decide = async (id, status) => {
    setDecision({ id, status });

    try {
      const data = await AcceptOrRejectVacationRequestApi(id, status);
      toast.success(data.message);
    } catch (error) {
      toast.error(error.response?.data?.message || error.message);
    } finally {
      await load(paging.currentPage);
      setDecision(null);
    }
  };

  useEffect(() => {
    load();
  }, [load]);

  const columns = [
    {
      key: "name",
      header: t("label.name", { ns: "common" }),
      render: (tr) => (
        <strong className={styles.strong}>{tr.trainee.name}</strong>
      ),
    },

    {
      key: "status",
      header: t("label.status", { ns: "common" }),
      render: (tr) => (
        <StatusBadge kind="vacation" status={tr.status} />
      ),
    },

    {
      key: "reason",
      header: t("label.reason", { ns: "common" }),
      render: (tr) => tr.reason,
    },

    {
      key: "startDate",
      header: t("task.startDate", { ns: "common" }),
      render: (tr) => tr.start_date,
    },

    {
      key: "deadline",
      header: t("task.deadline", { ns: "common" }),
      render: (tr) => tr.end_date,
    },
    {
      key: "action",
      header: t("label.actions", { ns: "common" }),
      render: (tr) => tr.can_decide ? (
        <div className="flexCenter gap8">
          <Button
            variant="success"
            icon={Check}
            size="sm"
            onClick={() => decide(tr.id, "accepted")}
            aria-label={t("vacation.approve", { ns: "trainee" })}
            disabled={decision !== null}
            loading={decision?.id === tr.id && decision.status === "accepted"}
          ></Button>

          <Button
            icon={X}
            size="sm"
            onClick={() => decide(tr.id, "rejected")}
            aria-label={t("vacation.reject", { ns: "trainee" })}
            disabled={decision !== null}
            loading={decision?.id === tr.id && decision.status === "rejected"}
          ></Button>
        </div>
      ) : "----",
    },
  ];

  return loading ? (
    <Loading />
  ) : (
    <>
      <Table
        columns={columns}
        rows={rows}
        emptyText={t("trainees.empty")}
      />
      <Pagination {...paging} onPageChange={load} />
    </>
  );
}
