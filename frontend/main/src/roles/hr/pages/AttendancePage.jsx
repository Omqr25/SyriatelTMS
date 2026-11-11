import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import Card from "../../../components/ui/Card";
import { Input } from "../../../components/ui/Field";
import Table from "../../../components/ui/Table";

import StatusBadge from "../components/StatusBadge";
import styles from "./Pages.module.css";
import { getAbsentAndSearchApi } from "../../../Api/HrApi/hrApi";
import Loading from "../../../components/ui/Loading";
import Button from "../../../components/ui/Button";

export default function AttendancePage() {
  const { t } = useTranslation("hr");
 
  const [search, setSearch] = useState("");
  const [loading, setLoading] = useState(true);
  const [searchLoading, setSearchLoading] = useState(false);
  const [absent, SetAbsent] = useState([]);


  const searchAbsent = async () => {
    setSearchLoading(true);
    try {
      const data = await getAbsentAndSearchApi(search);
      SetAbsent(data.data);
      console.log("data search:", data.data);
    } catch (error) {
      console.log(error);
      const message =
        error.response?.data?.message || error.message || "Add Absent Error";
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      setSearchLoading(false);
    }
  };

  const getAbsent = async () => {
    setLoading(true);
    try {
      const data = await getAbsentAndSearchApi();
      SetAbsent(data.data);
    } catch (error) {
      console.log(error);
      const message =
        error.response?.data?.message || error.message || "Add Absent Error";
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    getAbsent();
  }, []);

  const columns = [
    {
      key: "trainee",
      header: t("attendance.trainee"),
      render: (a) => (
        <strong className={styles.strong}>{a.trainee_name}</strong>
      ),
    },
    { key: "date", header: t("attendance.absenceDate") },
    {
      key: "status",
      header: t("label.status", { ns: "common" }),
      render: (a) => <StatusBadge kind="attendance" status={a.status} />,
    },
    {
      key: "reason",
      header: t("attendance.reason"),
      render: (a) => a.reason,
    },
  ];

  return loading ? (
    <Loading />
  ) : (
    <>
      <Card className={`${styles.searchCard} flexCenter gap8`}>
        <Input
          placeholder={t("attendance.searchPlaceholder")}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />

        <Button onClick={searchAbsent} loading={searchLoading}>
          {t("action.search", { ns: "common" })}
        </Button>
      </Card>
      <Table
        columns={columns}
        rows={absent}
        emptyText={t("attendance.empty")}
      />
    </>
  );
}
