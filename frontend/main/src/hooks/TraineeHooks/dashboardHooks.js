import { CalendarCheck, AlertCircle } from "lucide-react"
import { getDahshboard } from "../../Api/Trainee/dashboardApi";
import { useState } from 'react'
export default function DashboardHooks() {
    const [loadingData, setLoadingData] = useState(false);
    const [taskDeadline, setTaskDeadline] = useState([]);
    const [taskStatistics, setTaskStatistics] = useState([]);
    const [error, setError] = useState(false);
    const fetchDashboard = async () => {
        try {
            setLoadingData(true);
            setError(false);
            const response = await getDahshboard();
            const dashboardData = response?.data;
            const tasks =
                dashboardData?.upcoming_deadline_tasks ?? [];
            setTaskDeadline(Array.isArray(tasks) ? tasks : []);
            setTaskStatistics([
                { label: "completed_tasks_count", value: dashboardData['completed_tasks_count'], icon: CalendarCheck },
                { label: "assigned_tasks_count", value: dashboardData['assigned_tasks_count'], icon: AlertCircle }
            ])
        } catch (err) {
            console.error("Failed to fetch dashboard:", err);
            setError(true);
        } finally {
            setLoadingData(false);
        }
    };

    return {
        loadingData, taskDeadline, taskStatistics, error, fetchDashboard
    }
}