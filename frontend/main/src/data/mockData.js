// بيانات تجريبية — عند ربط الـ Backend تُستبدل بطلبات API داخل DataContext.
import { APPLICANT_STATUS as S, INTERVIEW_STATUS as I, AUDIENCE } from "../constants";

export const initialApplicants = [
  { id: 1, name: "عمر خالد الأحمد", email: "omar.ahmed@example.com", gpa: 88.5, academicYear: "graduated", major: "itSoftware", city: "damascus", status: S.NEW, files: ["CV_Omar.pdf", "Transcript.pdf"] },
  { id: 2, name: "ريم محمد الحمصي", email: "reem.homsi@example.com", gpa: 79.2, academicYear: "final", major: "telecom", city: "homs", status: S.ASSIGNED, departmentId: 1, supervisorId: 101, files: ["CV_Reem.pdf"] },
  { id: 3, name: "حسان يوسف العلي", email: "hassan.ali@example.com", gpa: 91.0, academicYear: "graduated", major: "aiIot", city: "aleppo", status: S.ACCEPTED, departmentId: 2, supervisorId: 102, files: ["CV_Hassan.pdf"] },
  { id: 4, name: "سارة عبد الرحيم", email: "sara.ab@example.com", gpa: 82.4, academicYear: "final", major: "graphicDesign", city: "latakia", status: S.REJECTED, files: ["Portfolio_Sara.pdf"] },
];

export const initialDepartments = [
  { id: 1, name: "إدارة البرمجيات والحلول الرقمية", location: "المبنى الرئيسي - الطابق 4" },
  { id: 2, name: "قسم الاتصالات والشبكات", location: "المبنى الفني - الطابق 2" },
  { id: 3, name: "التسويق وتجربة المشتركين", location: "المبنى الإداري - الطابق 1" },
];

export const initialSupervisors = [
  { id: 101, name: "م. طارق النابلسي", email: "tarek.n@syriatel.com.sy", departmentId: 1 },
  { id: 102, name: "م. ليلى السعدي", email: "laila.s@syriatel.com.sy", departmentId: 2 },
  { id: 103, name: "أ. سامر العبد الله", email: "samer.a@syriatel.com.sy", departmentId: 3 },
];

export const initialInterviews = [
  { id: 1, applicantId: 2, supervisorId: 101, date: "2026-10-05", time: "10:30", location: "قاعة الاجتماعات A", status: I.SCHEDULED, rating: null },
  { id: 2, applicantId: 3, supervisorId: 102, date: "2026-09-20", time: "13:00", location: "Online - Teams", status: I.ACCEPTED, rating: 4.8 },
];

export const initialTrainees = [
  { id: 1001, applicantId: 3, name: "حسان يوسف العلي", email: "hassan.ali@example.com", departmentId: 2, startDate: "2026-09-01" },
  { id: 1002, applicantId: null, name: "نور الهدى قاسم", email: "nour.qassim@example.com", departmentId: 3, startDate: "2026-08-15" },
];

export const initialAttendance = [
  { id: 1, traineeId: 1001, date: "2026-09-29", status: "absent", reason: "sick" },
  { id: 2, traineeId: 1002, date: "2026-09-29", status: "absent", reason: "noExcuse" },
];

export const initialAnnouncements = [
  { id: 1, title: "بدء الدورة التدريبية لمهارات البرمجة", details: "يرجى من جميع المتدربين التواجد في مدرج الشركة الساعة 9 صباحاً يوم الأحد.", target: AUDIENCE.TRAINEES, date: "2026-09-28" },
  { id: 2, title: "اجتماع المشرفين الدوري", details: "مناقشة تقارير الأداء الشهرية للمتدربين.", target: AUDIENCE.SUPERVISORS, date: "2026-09-25" },
];
