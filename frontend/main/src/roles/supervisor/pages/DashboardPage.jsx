import {
  ClipboardList,
  GraduationCap,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import Card from "../../../components/ui/Card";

import styles from "./DashboardPage.module.css";

import { useEffect, useState } from "react";

import Loading from "../../../components/ui/Loading";
import { getDashboardDataSupervisorApi } from "../../../Api/SupervisorApi/supervisorApi";
export default function DashboardPage() {
  const { t } = useTranslation("hr");

  const [loading, setLoading] = useState(false);
  const [Data, setData] = useState({ tasks_count: 0, trainees_count: 0 });

  const getDashboardData = async () => {
    setLoading(true);
    try {
      const data = await getDashboardDataSupervisorApi();
      // console.log(data);
      setData(data.data);
    } catch (error) {
      console.log(error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    getDashboardData();
  }, []);

  return loading ? (
    <Loading />
  ) : (
    <>
      <div className={styles.stats}>
        <Card className={styles.stat}>
          <div>
            <p className={styles.statLabel}>{t("nav.tasks")}</p>
            <p className={styles.statValue}>{Data?.tasks_count}</p>
          </div>
          <span className={styles.statIcon}>
            <ClipboardList size={22} />
          </span>
        </Card>

        <Card className={styles.stat}>
          <div>
            <p className={styles.statLabel}>{t("nav.trainees")}</p>
            <p className={styles.statValue}>{Data?.trainees_count}</p>
          </div>
          <span className={styles.statIcon}>
            <GraduationCap size={22} />
          </span>
        </Card>
      </div>
    </>
  );
}
