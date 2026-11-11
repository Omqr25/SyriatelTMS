import { useEffect } from "react";
import {
    CalendarDays,
    Clock,
    ListTodo,
    AlertCircle,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import styles from "./Dashboard.module.css";
import Card from "../../../../components/ui/Card";
import Loading from "../../../../components/ui/Loading";
import DashboardHooks from "../../../../hooks/TraineeHooks/dashboardHooks";

const formatDeadline = (deadline) => {
    if (!deadline) return "No deadline";

    const date = new Date(deadline);

    if (Number.isNaN(date.getTime())) return deadline;

    return new Intl.DateTimeFormat(undefined, {
        day: "numeric",
        month: "short",
        year: "numeric",
    }).format(date);
};

const getPriorityClass = (priority) => {
    switch (String(priority ?? "").toLowerCase()) {
        case "high":
            return styles.priorityHigh;
        case "medium":
            return styles.priorityMedium;
        case "low":
            return styles.priorityLow;
        default:
            return styles.priorityDefault;
    }
};

export default function DashboardPage() {
    const { t } = useTranslation("trainee");
    const {
        loadingData, taskDeadline, taskStatistics, error, fetchDashboard
    } = DashboardHooks();
    useEffect(() => {
        fetchDashboard();
    console.log(`===========${loadingData}`)

    }, []);
    if (loadingData) return <Loading />;

    return (
        <>
            <div className={styles.stats}>
                    {taskStatistics.map(({ label, value, icon:Icon }) => (
                    <Card key={label} >
                        <span className={styles.headerIcon}><Icon size={22} /></span>
                        <div>
                        <p className={styles.panelTitle}>{t(`dashboard.${label}`)}</p>
                        <p >{value}</p>
                        </div>
                    </Card>
                    ))}
                </div>
                <div className={styles.dashboard}>
            <Card>
                <section className={styles.taskPanel}>
                    <header className={styles.panelHeader}>
                        <div className={styles.headerIcon}>
                            <ListTodo size={21} />
                        </div>

                        <div className={styles.headerContent}>
                            <h2 className={styles.panelTitle}>
                                {t("dashboard.upcomingTasks", "Upcoming Tasks")}
                            </h2>
                            <p className={styles.panelSubtitle}>
                                Tasks approaching their deadlines
                            </p>
                        </div>

                        <span className={styles.taskCount}>
                            {taskDeadline.length}
                        </span>
                    </header>

                    <div className={styles.panelBody}>
                        {error ? (
                            <div className={styles.emptyState}>
                                <AlertCircle size={28} />
                                <p>Unable to load upcoming tasks.</p>
                            </div>
                        ) : taskDeadline.length === 0 ? (
                            <div className={styles.emptyState}>
                                <CalendarDays size={30} />
                                <p>No upcoming tasks</p>
                                <span>
                                    Your upcoming tasks will appear here.
                                </span>
                            </div>
                        ) : (
                            <ul className={styles.taskList}>
                                {taskDeadline.map((task) => (
                                    <li
                                        key={task.id}
                                        className={styles.taskItem}
                                    >
                                        <div className={styles.taskIcon}>
                                            <Clock size={18} />
                                        </div>

                                        <div className={styles.taskInfo}>
                                            <h3 className={styles.taskName}>
                                                {task.name || "Untitled task"}
                                            </h3>

                                            <div className={styles.taskMeta}>
                                                <span
                                                    className={`${styles.priorityBadge} ${getPriorityClass(task.priority)}`}
                                                >
                                                    {task.priority || "Unspecified"}
                                                </span>
                                            </div>
                                        </div>

                                        <div className={styles.deadline}>
                                            <span className={styles.deadlineLabel}>
                                                Deadline
                                            </span>
                                            <span className={styles.deadlineDate}>
                                                {formatDeadline(task.deadline)}
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </Card>
        </div>
        </>
        
    );
}