import {
  Building2, CalendarCheck, ClipboardList, FileText, GraduationCap,
  LayoutDashboard, ListChecks, Megaphone, UserCog, Users,Star,BookLock ,BellRing
} from 'lucide-react';
import AnnouncementsPage from './pages/AnnouncementsPage';
import ApplicantsPage from './pages/ApplicantsPage';
import AssignedRequestsPage from './pages/AssignedRequestsPage';
import AttendancePage from './pages/AttendancePage';
import DashboardPage from './pages/DashboardPage';
import DepartmentsPage from './pages/DepartmentsPage';
import InterviewsPage from './pages/InterviewsPage';
import ReportsPage from './pages/ReportsPage';
import SupervisorsPage from './pages/SupervisorsPage';
import TraineesPage from './pages/TraineesPage';
 import Registration from './pages/Registration';
import EvaluationsPage from './pages/EvaluationsPage';
import NotificationsPage from '../../components/notification/NotificationsPage';

/**
 * وحدة دور الموارد البشرية.
 * كل عنصر في nav هو في الوقت نفسه: رابط في القائمة الجانبية + Route في الراوتر.
 */
export const hrModule = {
  role: 'HR',
  basePath: '/hr',
  nav: [
    { path: 'dashboard', icon: LayoutDashboard, labelKey: 'hr:nav.dashboard', Component: DashboardPage },
    { path: 'applicants', icon: Users, labelKey: 'hr:nav.applicants', Component: ApplicantsPage },
    { path: 'assigned-requests', icon: ListChecks, labelKey: 'hr:nav.assignedRequests', Component: AssignedRequestsPage },
    { path: 'interviews', icon: CalendarCheck, labelKey: 'hr:nav.interviews', Component: InterviewsPage },
    { path: 'trainees', icon: GraduationCap, labelKey: 'hr:nav.trainees', Component: TraineesPage },
    { path: 'departments', icon: Building2, labelKey: 'hr:nav.departments', Component: DepartmentsPage },
    { path: 'supervisors', icon: UserCog, labelKey: 'hr:nav.supervisors', Component: SupervisorsPage },
    { path: 'attendance', icon: ClipboardList, labelKey: 'hr:nav.attendance', Component: AttendancePage },
    { path: 'announcements', icon: Megaphone, labelKey: 'hr:nav.announcements', Component: AnnouncementsPage },
    { path: 'reports', icon: FileText, labelKey: 'hr:nav.reports', Component: ReportsPage },
    { path: 'evaluations', icon: Star, labelKey: 'hr:nav.evaluations', Component: EvaluationsPage },
    { path: 'registration', icon: BookLock, labelKey: 'hr:nav.registration', Component: Registration },
    { path: 'notification', icon: BellRing, labelKey: 'hr:nav.notification', Component: NotificationsPage },


  ],
};


// export const supervisorModule = {
//   role: 'SUPERVISOR',
//   basePath: '/supervisor',
//   nav: [
//     { path: 'dashboard', icon: LayoutDashboard, labelKey: 'hr:nav.dashboard', Component: DashboardPage },
//     { path: 'applicants', icon: Users, labelKey: 'hr:nav.applicants', Component: ApplicantsPage },
//     { path: 'assigned-requests', icon: ListChecks, labelKey: 'hr:nav.assignedRequests', Component: AssignedRequestsPage },
//     { path: 'interviews', icon: CalendarCheck, labelKey: 'hr:nav.interviews', Component: InterviewsPage },
//     { path: 'trainees', icon: GraduationCap, labelKey: 'hr:nav.trainees', Component: TraineesPage },
//     { path: 'departments', icon: Building2, labelKey: 'hr:nav.departments', Component: DepartmentsPage },
//     { path: 'supervisors', icon: UserCog, labelKey: 'hr:nav.supervisors', Component: SupervisorsPage },
//     { path: 'attendance', icon: ClipboardList, labelKey: 'hr:nav.attendance', Component: AttendancePage },
//     { path: 'announcements', icon: Megaphone, labelKey: 'hr:nav.announcements', Component: AnnouncementsPage },
//     { path: 'reports', icon: FileText, labelKey: 'hr:nav.reports', Component: ReportsPage },
//     { path: 'evaluations', icon: Star, labelKey: 'hr:nav.evaluations', Component: EvaluationsPage },
//     { path: 'registration', icon: BookLock, labelKey: 'hr:nav.registration', Component: Registration },

//   ],
// };
