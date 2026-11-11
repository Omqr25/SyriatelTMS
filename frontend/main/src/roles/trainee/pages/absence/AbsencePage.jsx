import Loading from "../../../../components/ui/Loading";
import { useTranslation } from "react-i18next";
import Table from "../../../../components/ui/Table";

import styles from "./AbsencePage.module.css";
import AbsenceHooks from  "../../../../hooks/TraineeHooks/absenceHooks.js"
import { useEffect, } from "react";

export default function AbsencePage(){
    const { t } = useTranslation("hr");
    const {absenceList, loadingAction, getAbsence}=AbsenceHooks();
    useEffect(()=>{
getAbsence()
    },[])
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
   
     return loadingAction ? (
       <Loading />
     ) : (
       <>
         <Table
           columns={columns}
           rows={absenceList}
           emptyText={t("attendance.empty")}
         />
       </>
     );
}