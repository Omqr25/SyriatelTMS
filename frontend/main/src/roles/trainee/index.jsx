import { Navigate } from "react-router-dom";
import { TraineeAttendancePage } from "../../features/attendance/AttendancePage";
import CertificatesPage from '../../features/certificates/CertificatesPage';
import { accountRoutes } from '../../features/account/accountRoutes';
import {
  BellRing,
  Megaphone,CalendarCheck,Star,CalendarX,LayoutDashboard
} from 'lucide-react';
 
import ProfilePage from"./pages/profile/ProfilePage";
import AnnouncementPage from "./pages/announcement/AnnouncmentPage";
import TrainingProgram from  "./pages/training_program/TrainingProgram"
import EvaluationPage from "./pages/evaluation/EvaluationPage";
import Dashboard from "./pages/dashboard/DashboardPage";
import TaskDetailsPage from "./pages/training_program/TrainingProgramTaskDetials";
import NotificationsPage from "../../components/notification/NotificationsPage";


export const traineeModule = {
  role: 'TRAINEE',
  basePath: '/trainee',
  nav: [
    { path: 'dashboard', 
      icon: LayoutDashboard, 
      labelKey: 'hr:nav.dashboard', 
      Component: Dashboard },
    { path: 'training-programs', 
      icon: CalendarCheck, 
      labelKey: 'hr:nav.trainingPrograms', 
      Component: TrainingProgram },
    { path: 'announcements', 
      icon: Megaphone, 
      labelKey: 'hr:nav.announcements', 
      Component: AnnouncementPage },
    { path: 'evaluation', 
      icon: Star, 
      labelKey: 'hr:nav.evaluation', 
      Component: EvaluationPage },
    { path: 'attendance',
      icon: CalendarX,
      labelKey: 'attendance:title',
      Component: TraineeAttendancePage },
    {
      path: "notification",
      icon: BellRing,
      labelKey: "hr:nav.notification",
      Component: NotificationsPage,
    },
    { path: 'certificates', icon: Star, labelKey: 'certificates:title', Component: CertificatesPage },
  ],

  accountNav: accountRoutes(ProfilePage, 'trainee:profile.menuDescription'),

  extraRoutes:[
    { path: "absences", Component: () => <Navigate to="/trainee/attendance" replace /> },
    { path: "vacations", Component: () => <Navigate to="/trainee/attendance?tab=leave" replace /> },
        {
        path: "tasks/details/:taskId",
        Component: TaskDetailsPage,
      },
  ]
};
