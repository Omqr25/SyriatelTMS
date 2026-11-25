import StatusBadge from "../../../hr/components/StatusBadge.jsx";
import Loading from "../../../../components/ui/Loading";
import { useTranslation } from "react-i18next";
import Table from "../../../../components/ui/Table";

import Pagination from "../../../../components/ui/Pagination";
import { getAllAbsentApi } from "../../../../Api/SupervisorApi/supervisorApi";
import useAttendanceList from "../../../../hooks/useAttendanceList";
import { useEffect } from "react";

export default function AbsencePage(){
    const { t } = useTranslation("hr");
    const { load, loading, rows, paging } = useAttendanceList(getAllAbsentApi);

    useEffect(() => {
      load();
    }, [load]);

   const columns = [
       
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
         <Table
           columns={columns}
           rows={rows}
           emptyText={t("attendance.empty")}
         />
         <Pagination {...paging} onPageChange={load} />
       </>
     );
}