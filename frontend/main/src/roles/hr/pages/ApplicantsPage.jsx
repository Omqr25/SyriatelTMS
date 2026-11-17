import { useState } from "react";
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
import ApplicantDetailsModal from "../components/ApplicantDetailsModal";
import AssignDepartmentModal from "../components/AssignDepartmentModal";
import ScheduleInterviewModal from "../components/ScheduleInterviewModal";
import StatusBadge from "../components/StatusBadge";
import styles from "./ApplicantsPage.module.css";
import useApplicants from "../../../hooks/HrHooks/useApplicants";
import Modal from "../../../components/ui/Modal";
import { toast } from "react-toastify";
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
    applicantLoading,
    clearApplicant,
    loadApplicants,
    assign,
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
    // reject
    reject,
  } = useApplicants();

  const handleViewDetails = async (id) => {
    const data = await loadApplicant(id);

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
    clearApplicant();
  };
  const columns = [
    {
      key: "name",
      header: c("label.name"),
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
          value={filters.search}
          onChange={(e) => updateFilter("search", e.target.value)}
        />

        <div className={styles.gpaRange}>
          <Input
            placeholder={c("label.minimumGpa")}
            aria-label={c("label.minimumGpa")}
            title={c("label.minimumGpa")}
            value={filters.gpa_min}
            onChange={(e) => updateFilter("gpa_min", e.target.value)}
            type="number"
            min="0"
            max="100"
            step="0.01"
          />
          <Input
            placeholder={c("label.maximumGpa")}
            aria-label={c("label.maximumGpa")}
            title={c("label.maximumGpa")}
            value={filters.gpa_max}
            onChange={(e) => updateFilter("gpa_max", e.target.value)}
            type="number"
            min="0"
            max="100"
            step="0.01"
          />
        </div>

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
          onSchedule={() => openModal("schedule")}
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
      {modal?.type === "schedule" && applicant && (
        <ScheduleInterviewModal
          applicant={applicant}
          onClose={closeModal}
          update={loadApplicants}
        />
      )}
    </>
  );
}
