import { Navigate } from "react-router-dom";
import { SupervisorAttendancePage } from "../../features/attendance/AttendancePage";
import {
  Building2,
  CalendarCheck,
  ClipboardList,
  FileText,
  GraduationCap,
  LayoutDashboard,
  ListChecks,
  Megaphone,
  UserCog,
  Users,
  Star,
  BookLock,
  CalendarArrowDown,
  CalendarX,
  BellRing,
} from "lucide-react";
// import AnnouncementsPage from './pages/AnnouncementsPage';
import AssignedRequestsPage from "./pages/AssignedRequestsPage";
// import AttendancePage from './pages/AttendancePage';
import DashboardPage from "./pages/DashboardPage";
import InterviewsPage from "./pages/InterviewsPage";
import TraineesPage from "./pages/TraineesPage";
// import EvaluationsPage from './pages/EvaluationsPage';
import ProgramsPage from "./pages/ProgramsPage";
import MilestoneWorkspace from "./pages/MilestoneWorkspace";
import TaskDetailsPage from "./pages/TaskDetailsPage";
import Revaluation from "./pages/Revaluation";
import AnnouncementsPage from "./pages/AnnouncementsPage";
import NotificationsPage from "../../components/notification/NotificationsPage";
import { accountRoutes } from "../../features/account/accountRoutes";
import ProfilePage from "./pages/ProfilePage";

/**
 * وحدة دور الموارد البشرية.
 * كل عنصر في nav هو في الوقت نفسه: رابط في القائمة الجانبية + Route في الراوتر.
 */

export const supervisorModule = {
  role: "SUPERVISOR",
  basePath: "/supervisor",
  nav: [
    {
      path: "dashboard",
      icon: LayoutDashboard,
      labelKey: "hr:nav.dashboard",
      Component: DashboardPage,
    },
    {
      path: "assigned-requests",
      icon: ListChecks,
      labelKey: "hr:nav.assignedRequests",
      Component: AssignedRequestsPage,
    },
    {
      path: "interviews",
      icon: CalendarCheck,
      labelKey: "hr:nav.interviews",
      Component: InterviewsPage,
    },
    {
      path: "trainees",
      icon: GraduationCap,
      labelKey: "hr:nav.trainees",
      Component: TraineesPage,
    },

    {
      path: "programs",
      icon: ClipboardList,
      labelKey: "hr:nav.programs",
      Component: ProgramsPage,
    },
    {
      path: "re-evaluations",
      icon: CalendarArrowDown,
      labelKey: "hr:nav.re_evaluation",
      Component: Revaluation,
    },
    {
      path: "attendance",
      icon: CalendarX,
      labelKey: "attendance:title",
      Component: SupervisorAttendancePage,
    },
    {
      path: "announcements",
      icon: Megaphone,
      labelKey: "hr:nav.announcements",
      Component: AnnouncementsPage,
    },

    {
      path: "notification",
      icon: BellRing,
      labelKey: "hr:nav.notification",
      Component: NotificationsPage,
    },

    // { path: 'evaluations', icon: Star, labelKey: 'hr:nav.evaluations', Component: EvaluationsPage },
  ],

  accountNav: accountRoutes(ProfilePage),

  extraRoutes: [
    { path: "absent", Component: () => <Navigate to="/supervisor/attendance" replace /> },
    { path: "vacation-request", Component: () => <Navigate to="/supervisor/attendance?tab=leave" replace /> },
    {
      path: "program/details/:programId",
      Component: ProgramsPage,
    },
    {
      path: "programs/:programId/milestones/:milestoneId",
      Component: MilestoneWorkspace,
    },
    {
      path: "tasks/details/:taskId",
      Component: TaskDetailsPage,
    },
  ],
};
