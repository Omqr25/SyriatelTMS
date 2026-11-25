import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import Card from "../../../components/ui/Card";
import { Input } from "../../../components/ui/Field";
import Table from "../../../components/ui/Table";
import Pagination from "../../../components/ui/Pagination";
import useAttendanceList from "../../../hooks/useAttendanceList";

import StatusBadge from "../components/StatusBadge";
import styles from "./Pages.module.css";
import { getAbsentAndSearchApi } from "../../../Api/HrApi/hrApi";
import Loading from "../../../components/ui/Loading";
import Button from "../../../components/ui/Button";

export default function AttendancePage() {
  const { t } = useTranslation("hr");
 
  const [search, setSearch] = useState("");
  const [appliedSearch, setAppliedSearch] = useState("");
  const { load, loading, rows, paging } = useAttendanceList(getAbsentAndSearchApi);

  useEffect(() => {
    load();
  }, [load]);

  const searchAbsent = async () => {
    const name = search.trim();
    if (await load(1, name)) setAppliedSearch(name);
  };

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

  return (
    <>
      <Card className={`${styles.searchCard} flexCenter gap8`}>
        <Input
          placeholder={t("attendance.searchPlaceholder")}
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />

        <Button onClick={searchAbsent} disabled={loading}>
          {t("action.search", { ns: "common" })}
        </Button>
      </Card>
      {loading ? <Loading /> : (
        <>
          <Table columns={columns} rows={rows} emptyText={t("attendance.empty")} />
          <Pagination {...paging} onPageChange={(page) => load(page, appliedSearch)} />
        </>
      )}
    </>
  );
}
