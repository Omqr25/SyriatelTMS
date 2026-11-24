import CertificatesPage from '../../features/certificates/CertificatesPage';
import {
  BellRing,
  UserRound,Megaphone,CalendarCheck,CalendarOff,Star,CalendarX,LayoutDashboard,Settings
} from 'lucide-react';
 
import ProfilePage from"./pages/profile/ProfilePage";
import AnnouncementPage from "./pages/announcement/AnnouncmentPage";
import TrainingProgram from  "./pages/training_program/TrainingProgram"
import VacationPage from "./pages/vacation/VacationPage";
import EvaluationPage from "./pages/evaluation/EvaluationPage";
import AbsencePage from "./pages/absence/AbsencePage"
import Dashboard from "./pages/dashboard/DashboardPage";
import TaskDetailsPage from "./pages/training_program/TrainingProgramTaskDetials";
import NotificationsPage from "../../components/notification/NotificationsPage";
import SettingsPage from "./pages/settings/SettingsPage"


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
    { path: 'vacations', 
      icon: CalendarOff, 
      labelKey: 'hr:nav.vacations', 
      Component: VacationPage },
    { path: 'evaluation', 
      icon: Star, 
      labelKey: 'hr:nav.evaluation', 
      Component: EvaluationPage },
    { path: 'absences', 
      icon: CalendarX, 
      labelKey: 'hr:nav.attendance', 
      Component: AbsencePage },
    {
      path: "notification",
      icon: BellRing,
      labelKey: "hr:nav.notification",
      Component: NotificationsPage,
    },
    { path: 'certificates', icon: Star, labelKey: 'certificates:title', Component: CertificatesPage },
  ],

  accountNav: [
    { path: 'profile', icon: UserRound, labelKey: 'trainee:profile.title', descriptionKey: 'trainee:profile.menuDescription', Component: ProfilePage },
    { path: 'settings', icon: Settings, labelKey: 'trainee:settings.title', descriptionKey: 'trainee:settings.menuDescription', Component: SettingsPage },
  ],

  extraRoutes:[
        {
        path: "tasks/details/:taskId",
        Component: TaskDetailsPage,
      },
  ]
};
