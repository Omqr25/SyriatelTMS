import { createContext, useContext, useEffect, useState } from 'react';
import { APPLICANT_STATUS, INTERVIEW_STATUS } from '../constants';
import {
  initialAnnouncements,
  initialApplicants,
  initialAttendance,
  initialDepartments,
  initialInterviews,
  initialSupervisors,
  initialTrainees,
} from './mockData';


import {
  getApplicants,
  rejectApplicant,
} from "../Api/HrApi/applicantsApi";
/**
 * مصدر البيانات المشترك بين كل الأدوار (HR / مشرف / متدرب).
 * كل دالة async تحاكي طلب API؛ عند ربط الـ Backend يُستبدل جسم الدالة فقط
 * وتبقى الصفحات كما هي.
 */
const DataContext = createContext(null);
const sleep = (ms = 700) => new Promise((r) => setTimeout(r, ms));
const today = () => new Date().toISOString().slice(0, 10);

export function DataProvider({ children }) {
  const [applicants, setApplicants] = useState([]);
  const [departments, setDepartments] = useState(initialDepartments);
  const [supervisors, setSupervisors] = useState(initialSupervisors);
  const [interviews, setInterviews] = useState(initialInterviews);
  const [trainees, setTrainees] = useState(initialTrainees);
  const [attendance] = useState(initialAttendance);
  const [announcements, setAnnouncements] = useState(initialAnnouncements);

  // ---------- Loading ----------
  const [applicantsLoading, setApplicantsLoading] = useState(false);

  // ---------- Selectors ----------
  const getApplicant = (id) => applicants.find((a) => a.id === id);
  const getDepartment = (id) => departments.find((d) => d.id === id);
  const getSupervisor = (id) => supervisors.find((s) => s.id === id);
  const getTrainee = (id) => trainees.find((t) => t.id === id);

  // ---------- Actions ----------
    async function loadApplicants() {
    try {
      setApplicantsLoading(true);

      const data = await getApplicants();

      setApplicants(data.applicants ?? []);
    } catch (error) {
      console.error("Failed to load applicants:", error);
    } finally {
      setApplicantsLoading(false);
    }
  }

  // يتم تنفيذها عند تحميل DataProvider
  // useEffect(() => {
  //   loadApplicants();
  // }, []);


  async function rejectApplicant(id) {
    await sleep();
    setApplicants((list) =>
      list.map((a) => (a.id === id ? { ...a, status: APPLICANT_STATUS.REJECTED } : a)),
    );
  }

  async function assignApplicant(id, { departmentId, supervisorId }) {
    await sleep();
    setApplicants((list) =>
      list.map((a) =>
        a.id === id
          ? { ...a, status: APPLICANT_STATUS.ASSIGNED, departmentId, supervisorId }
          : a,
      ),
    );
  }

  async function scheduleInterview(applicantId, { supervisorId, date, time, location }) {
    await sleep();
    setInterviews((list) => [
      ...list,
      {
        id: Date.now(),
        applicantId,
        supervisorId,
        date,
        time,
        location,
        status: INTERVIEW_STATUS.SCHEDULED,
        rating: null,
      },
    ]);
    setApplicants((list) =>
      list.map((a) =>
        a.id === applicantId ? { ...a, status: APPLICANT_STATUS.INTERVIEW_SCHEDULED } : a,
      ),
    );
  }

  async function finalAccept(interviewId) {
    const interview = interviews.find((i) => i.id === interviewId);
    const applicant = getApplicant(interview?.applicantId);
    await sleep();
    setInterviews((list) =>
      list.map((i) => (i.id === interviewId ? { ...i, status: INTERVIEW_STATUS.ACCEPTED } : i)),
    );
    if (!applicant) return;
    setApplicants((list) =>
      list.map((a) => (a.id === applicant.id ? { ...a, status: APPLICANT_STATUS.ACCEPTED } : a)),
    );
    setTrainees((list) =>
      list.some((t) => t.applicantId === applicant.id)
        ? list
        : [
            ...list,
            {
              id: Date.now(),
              applicantId: applicant.id,
              name: applicant.name,
              email: applicant.email,
              departmentId: applicant.departmentId ?? null,
              startDate: today(),
            },
          ],
    );
  }

  async function addDepartment({ name, location }) {
    await sleep();
    setDepartments((list) => [...list, { id: Date.now(), name, location }]);
  }

  async function addSupervisor({ name, email, departmentId }) {
    await sleep();
    setSupervisors((list) => [...list, { id: Date.now(), name, email, departmentId }]);
  }

  async function addAnnouncement({ title, details, target }) {
    await sleep();
    setAnnouncements((list) => [
      { id: Date.now(), title, details, target, date: today() },
      ...list,
    ]);
  }

  const value = {
    applicants,applicantsLoading, departments, supervisors, interviews, trainees, attendance, announcements,
    getApplicant, getDepartment, getSupervisor, getTrainee,
    rejectApplicant, assignApplicant, scheduleInterview, finalAccept,
    addDepartment, addSupervisor, addAnnouncement,
  };

  return <DataContext.Provider value={value}>{children}</DataContext.Provider>;
}

export const useData = () => useContext(DataContext);
