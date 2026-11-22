import { useEffect, useState } from "react";
import { ChevronDown, ChevronUp, ChevronsUpDown, RotateCcw } from "lucide-react";
import { ENGLISH_SCORES_CHANGED } from "../../../Api/HrApi/englishScoreImportsApi";
import { useNavigate } from "react-router-dom";
import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import Card from "../../../components/ui/Card";
import { Input, Select } from "../../../components/ui/Field";
import Table from "../../../components/ui/Table";
import {
  ACADEMIC_YEAR_KEYS,
  CITY_KEYS,
  MAJOR_KEYS,
  APPLICANT_STATUS_Arr,
} from "../../../constants";
import AdmissionFilters from "../components/AdmissionFilters";
import ApplicantDetailsModal from "../components/ApplicantDetailsModal";
import ApplicantScore from "../components/ApplicantScore";
import AssignDepartmentModal from "../components/AssignDepartmentModal";
import StatusBadge from "../components/StatusBadge";
import styles from "./ApplicantsPage.module.css";
import useApplicants from "../../../hooks/HrHooks/useApplicants";
import Modal from "../../../components/ui/Modal";
import { toast } from "react-toastify";
import Pagination from "../../../components/ui/Pagination";

const ADMISSION_MINIMUMS = { gpa: 70, english: 45 };

export default function ApplicantsPage() {
  const { t } = useTranslation("hr");
  const navigate = useNavigate();
  const c = (key, opts) => t(key, { ns: "common", ...opts });

  const {
    applicants,
    loading,
    loadApplicants,
    // Applicant
    loadApplicant,
    applicant,
    // Loading
    actionLoading,
    applicantLoading,
    clearApplicant,
    assign,
    rejectLOading,
    // Normal pagination
    page,
    pagination,
    setPage,
    // Search
    filters,
    updateFilter,
    sort,
    toggleSort,
    resetFilters,
    // reject
    reject,
  } = useApplicants();

  useEffect(() => {
    const refreshScores = (event) => {
      if (event.key === ENGLISH_SCORES_CHANGED) loadApplicants();
    };
    window.addEventListener("storage", refreshScores);
    return () => window.removeEventListener("storage", refreshScores);
  }, [loadApplicants]);

  const handleViewDetails = async (id) => {
    const data = await loadApplicant(id);

    if (!data) {
      return;
    }
    setModal({
      type: "details",
    });
  };

  const [modal, setModal] = useState(null);

  const openModal = (type) => {
    setModal({ type });
  };

  const closeModal = () => {
    setModal(null);
    clearApplicant();
  };
  const sortableHeader = (key, label) => {
    const direction = sort?.key === key ? sort.direction : null;
    const Icon = direction === "asc" ? ChevronUp : direction === "desc" ? ChevronDown : ChevronsUpDown;

    return {
      ariaSort: direction === "asc" ? "ascending" : direction === "desc" ? "descending" : undefined,
      header: (
        <button type="button" className={styles.sortHeader} onClick={() => toggleSort(key)}>
          {label}
          <Icon size={14} className={direction ? styles.activeSort : undefined} aria-hidden="true" />
        </button>
      ),
    };
  };

  const columns = [
    {
      key: "name",
      ...sortableHeader("name", c("label.name")),
      render: (a) => (
        <button
          type="button"
          className={styles.nameBtn}
          onClick={() => handleViewDetails(a.id)}
          disabled={!!applicantLoading || actionLoading}
        >
          {a.full_name}
        </button>
      ),
    },
    { key: "email", header: c("label.email") },
    {
      key: "gpa",
      ...sortableHeader("gpa", c("label.gpa")),
      render: (a) => (
        <ApplicantScore
          value={a.gpa}
          formattedValue={`${a.gpa}%`}
          minimum={ADMISSION_MINIMUMS.gpa}
          warning={t("applicants.admission.lowGpa", { minimum: ADMISSION_MINIMUMS.gpa })}
          iconLabel={t("applicants.admission.scoreDetails", { score: c("label.gpa") })}
        />
      ),
    },
    {
      key: "english_test_score",
      ...sortableHeader("english_test_score", t("englishScores:score")),
      render: (a) => (
        <ApplicantScore
          value={a.english_test_score}
          formattedValue={a.english_test_score}
          minimum={ADMISSION_MINIMUMS.english}
          warning={t("applicants.admission.lowEnglish", { minimum: ADMISSION_MINIMUMS.english })}
          pending={t("applicants.admission.missingEnglish")}
          iconLabel={t("applicants.admission.scoreDetails", { score: t("englishScores:score") })}
        />
      ),
    },
    {
      key: "status",
      ...sortableHeader("status", c("label.status")),
      render: (a) => <StatusBadge status={a.status} />,
    },
    {
      key: "actions",
      header: c("label.actions"),
      render: (a) => (
        <div className={styles.actions}>
          <Button
            size="sm"
            variant="secondary"
            // onClick={() => open("details", a)}
            onClick={() => handleViewDetails(a.id)}
            loading={applicantLoading === a.id}
            disabled={!!applicantLoading || actionLoading}
          >
            {c("action.viewDetails")}
          </Button>
          {[
            "pending",
            "assigned",
            "qualified",
            "for interview",
            "interviewed",
          ].includes(a.status) && (
            <Button
              size="sm"
              variant="soft"
              loading={rejectLOading == a.id}
              disabled={actionLoading || !!applicantLoading}
              onClick={() => setModal({ type: "reject", applicant: a })}
            >
              {t("applicants.reject")}
            </Button>
          )}
        </div>
      ),
    },
  ];

  // console.log("applicants", applicants);
  return (
    <>
      <Card className={styles.filters}>
        <Input
          placeholder={c("label.search")}
          aria-label={c("label.search")}
          value={filters.search}
          onChange={(e) => updateFilter("search", e.target.value)}
        />

        <Select
          aria-label={c("label.status")}
          value={filters.status}
          onChange={(e) => updateFilter("status", e.target.value)}
        >
          <option value="">
            {c("label.status")}: {c("label.all")}
          </option>
          {APPLICANT_STATUS_Arr.map((k) => (
            <option key={k} value={k}>
              {c(`status.applicant.${k}`)}
            </option>
          ))}
        </Select>

        <Select
          aria-label={c("label.academicYear")}
          value={filters.year}
          onChange={(e) => updateFilter("year", e.target.value)}
        >
          <option value="">
            {c("label.academicYear")}: {c("label.all")}
          </option>
          {ACADEMIC_YEAR_KEYS.map((k) => (
            <option key={k} value={k}>
              {c(`academicYear.${k}`)}
            </option>
          ))}
        </Select>
        <Select
          aria-label={c("label.major")}
          value={filters.major}
          onChange={(e) => updateFilter("major", e.target.value)}
        >
          <option value="">
            {c("label.major")}: {c("label.all")}
          </option>
          {MAJOR_KEYS.map((k) => (
            <option key={k} value={k}>
              {c(`major.${k}`)}
            </option>
          ))}
        </Select>
        <Select
          aria-label={c("label.city")}
          value={filters.city}
          onChange={(e) => updateFilter("city", e.target.value)}
        >
          <option value="">
            {c("label.city")}: {c("label.all")}
          </option>
          {CITY_KEYS.map((k) => (
            <option key={k} value={k}>
              {c(`city.${k}`)}
            </option>
          ))}
        </Select>
        <div className={styles.filterActions}>
          <AdmissionFilters filters={filters} updateFilter={updateFilter} minimums={ADMISSION_MINIMUMS} />
          <Button
            size="sm"
            variant="secondary"
            className={styles.resetFilters}
            onClick={resetFilters}
            aria-label={c("action.reset")}
            title={c("action.reset")}
          >
            <RotateCcw size={16} aria-hidden="true" />
          </Button>
        </div>
      </Card>

      <div aria-busy={loading} style={{ opacity: loading ? 0.5 : 1 }}>
        <Table
          columns={columns}
          rows={applicants}
          emptyText={t("applicants.empty")}
        />
      </div>

      <Pagination
        currentPage={page}
        totalPages={pagination.totalPages}
        onPageChange={setPage}
      />

      {modal?.type === "reject" && (
        <Modal
          title={t("applicants.reject")}
          onClose={() => !actionLoading && closeModal()}
        >
          <p>
            {t("applicants.confirmReject", { name: modal.applicant.full_name })}
          </p>
          <Button
            loading={actionLoading}
            onClick={async () => {
              const result = await reject(modal.applicant.id);
              if (result) {
                toast.success(t("applicants.toast.rejected"));
                closeModal();
              }
            }}
          >
            {t("applicants.reject")}
          </Button>
        </Modal>
      )}
      {modal?.type === "details" && applicant && (
        <ApplicantDetailsModal
          // applicant={modal.applicant}
          applicant={applicant}
          onClose={closeModal}
          // onAssign={() => open("assign", modal.applicant)}
          // onSchedule={() => open("schedule", modal.applicant)}
          onAssign={() => openModal("assign")}
          onSchedule={() => {
            if (applicant.status !== "qualified") return;
            navigate(
              `/hr/assigned-requests?schedule=${encodeURIComponent(applicant.id)}`,
            );
          }}
        />
      )}
      {modal?.type === "assign" && applicant && (
        <AssignDepartmentModal
          applicant={applicant}
          onClose={closeModal}
          assign={assign}
          actionLoading={actionLoading}
        />
      )}
    </>
  );
}
