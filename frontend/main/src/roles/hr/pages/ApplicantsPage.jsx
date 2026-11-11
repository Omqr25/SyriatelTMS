import { useState } from "react";
import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import Card from "../../../components/ui/Card";
import { Input, Select } from "../../../components/ui/Field";
import Table from "../../../components/ui/Table";
import {
  ACADEMIC_YEAR_KEYS,
  APPLICANT_STATUS,
  CITY_KEYS,
  MAJOR_KEYS,
  APPLICANT_STATUS_Arr,
} from "../../../constants";
import ApplicantDetailsModal from "../components/ApplicantDetailsModal";
import AssignDepartmentModal from "../components/AssignDepartmentModal";
import ScheduleInterviewModal from "../components/ScheduleInterviewModal";
import StatusBadge from "../components/StatusBadge";
import styles from "./ApplicantsPage.module.css";
import useApplicants from "../../../hooks/HrHooks/useApplicants";
import Loading from "../../../components/ui/Loading";
import Pagination from "../../../components/ui/Pagination";

export default function ApplicantsPage() {
  const { t } = useTranslation("hr");
  const c = (key, opts) => t(key, { ns: "common", ...opts });

  const {
    applicants,
    loading,
    // Applicant
    loadApplicant,
    applicant,
    // Loading
    actionLoading,
    setActionLoading,
    rejectLOading,
    // Normal pagination
    page,
    pagination,
    setPage,
    // Search
    filters,
    updateFilter,
    applyFilters,
    resetFilters,
    isSearchMode,
    // Search pagination
    searchPage,
    searchPagination,
    changeSearchPage,
    // reject
    reject,
  } = useApplicants();

  const handleViewDetails = async (id) => {
    setActionLoading(id);

    const data = await loadApplicant(id);
    setActionLoading(null);
    if (!data) {
      return;
    }
    setModal({
      type: "details",
    });
  };

  const [modal, setModal] = useState(null); // { type: 'details' | 'assign' | 'schedule', applicant }

  const openModal = (type) => {
    setModal({ type });
  };

  const closeModal = () => {
    setModal(null);
  };
  const columns = [
    {
      key: "name",
      header: c("label.name"),
      render: (a) => (
        <button
          type="button"
          className={styles.nameBtn}
          onClick={() => handleViewDetails(a._id)}
        >
          {a.full_name}
        </button>
      ),
    },
    { key: "email", header: c("label.email") },
    { key: "gpa", header: c("label.gpa"), render: (a) => `${a.gpa}%` },
    {
      key: "status",
      header: c("label.status"),
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
            loading={actionLoading === a.id}
            disabled={actionLoading === a.id}
          >
            {c("action.viewDetails")}
          </Button>
          <Button
            size="sm"
            variant="soft"
            loading={rejectLOading == a.id}
            disabled={
              a.status === APPLICANT_STATUS.REJECTED ||
              a.status === APPLICANT_STATUS.ACCEPTED
            }
            onClick={() => reject(a.id)}
          >
            {t("applicants.reject")}
          </Button>
        </div>
      ),
    },
  ];

  // console.log("applicants", applicants);
  return loading ? (
    <Loading />
  ) : (
    <>
      <Card className={styles.filters}>
        <Input
          placeholder={c("label.search")}
          value={filters.search}
          onChange={(e) => updateFilter("search", e.target.value)}
        />

        <Input
          placeholder="GPA"
          value={filters.gpa}
          onChange={(e) => updateFilter("gpa", e.target.value)}
          type="number"
        />

        <Select
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
        <Button variant="secondary" onClick={resetFilters} loading={loading}>
          {c("action.reset")}
        </Button>

        <Button
          variant="primary"
          onClick={applyFilters}
          loading={loading}
          disabled={loading}
        >
          {c("action.search")}
        </Button>
      </Card>

      <Table
        columns={columns}
        rows={applicants}
        emptyText={t("applicants.empty")}
      />

      {isSearchMode ? (
        <Pagination
          currentPage={searchPage}
          totalPages={searchPagination.totalPages}
          onPageChange={changeSearchPage}
        />
      ) : (
        <Pagination
          currentPage={page}
          totalPages={pagination.totalPages}
          onPageChange={setPage}
        />
      )}

      {modal?.type === "details" && applicant && (
        <ApplicantDetailsModal
          // applicant={modal.applicant}
          applicant={applicant}
          onClose={closeModal}
          // onAssign={() => open("assign", modal.applicant)}
          // onSchedule={() => open("schedule", modal.applicant)}
          onAssign={() => openModal("assign")}
          onSchedule={() => openModal("schedule")}
        />
      )}
      {modal?.type === "assign" && applicant && (
        <AssignDepartmentModal applicant={applicant} onClose={closeModal} />
      )}
      {modal?.type === "schedule" && applicant && (
        <ScheduleInterviewModal applicant={applicant} onClose={closeModal} />
      )}
      
    </>
  );
}
