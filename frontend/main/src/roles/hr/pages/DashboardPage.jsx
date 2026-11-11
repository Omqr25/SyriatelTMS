import {
  Building2,
  CalendarCheck,
  Clock,
  GraduationCap,
  Users,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import Card from "../../../components/ui/Card";
import StatusBadge from "../components/StatusBadge";
import styles from "./DashboardPage.module.css";
import { useEffect, useState } from "react";
import { getDashboardDataApi } from "../../../Api/HrApi/hrApi";
import Loading from "../../../components/ui/Loading";
export default function DashboardPage() {

  const { t } = useTranslation("hr");

  const [loading, setLoading] = useState(false);
  const [Data, setData] = useState({});

  const getDashboardData = async () => {
    setLoading(true);
    try {
      const data = await getDashboardDataApi();
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
            <p className={styles.statLabel}>{t("nav.applicants")}</p>
            <p className={styles.statValue}>
              {Data?.statistics?.total_applicants}
            </p>
          </div>
          <span className={styles.statIcon}>
            <Users size={22} />
          </span>
        </Card>

        <Card className={styles.stat}>
          <div>
            <p className={styles.statLabel}>{t("nav.trainees")}</p>
            <p className={styles.statValue}>{Data?.statistics?.trainees}</p>
          </div>
          <span className={styles.statIcon}>
            <GraduationCap size={22} />
          </span>
        </Card>

        <Card className={styles.stat}>
          <div>
            <p className={styles.statLabel}>{t("nav.interviews")}</p>
            <p className={styles.statValue}>{Data?.statistics?.interviews}</p>
          </div>
          <span className={styles.statIcon}>
            <CalendarCheck size={22} />
          </span>
        </Card>

        <Card className={styles.stat}>
          <div>
            <p className={styles.statLabel}>{t("nav.departments")}</p>
            <p className={styles.statValue}>{Data?.statistics?.departments}</p>
          </div>
          <span className={styles.statIcon}>
            <Building2 size={22} />
          </span>
        </Card>
      </div>

      <div className={styles.columns}>
        <Card>
          <h4 className={styles.heading}>
            <Clock size={18} /> {t("dashboard.latestApplicants")}
          </h4>
          <ul className={styles.list}>
            {Data?.recent_applicants?.map((a) => (
              <li key={a.id} className={styles.item}>
                <d iv>
                  <p className={styles.itemTitle}>{a.name}</p>
                  {/* <p className={styles.itemSub}>{t(`major.${a.major}`, { ns: 'common' })} · {t(`city.${a.city}`, { ns: 'common' })}</p> */}
                  <p className={styles.itemSub}>{a.application_date} </p>
                </d>
                <StatusBadge status={a.status} />
              </li>
            ))}
          </ul>
        </Card>

        <Card>
          <h4 className={styles.heading}>
            <CalendarCheck size={18} /> {t("dashboard.upcomingInterviews")}
          </h4>
          <ul className={styles.list}>
            {Data?.upcoming_interviews?.map((i) => (
              <li key={i.id} className={styles.item}>
                <div>
                  <p className={styles.itemTitle}>{i.applicant_name}</p>
                  <p className={styles.itemSub}>
                    {t("dashboard.supervisorLine", { name: i.supervisor })}
                  </p>
                </div>
                <div className={styles.when}>
                  <p>{i.date}</p>
                  <p className={styles.itemSub}>{i.time}</p>
                </div>
              </li>
            ))}
            {Data?.upcoming_interviews?.length === 0 && (
              <li className={styles.empty}>{t("dashboard.noUpcoming")}</li>
            )}
          </ul>
        </Card>
      </div>
    </>
  );
}
