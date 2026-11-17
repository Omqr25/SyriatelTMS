import { useTranslation } from "react-i18next";
import Table from "../../../components/ui/Table";

import styles from "./Pages.module.css";
import useTrainees from "../../../hooks/HrHooks/useTrainees";
import Button from "../../../components/ui/Button";
import { useState } from "react";
import { toast } from "react-toastify";
import Modal from "../../../components/ui/Modal";
import { Field, Input } from "../../../components/ui/Field";
import form from "../../../components/ui/Form.module.css";
import Pagination from "../../../components/ui/Pagination";
import Card from "../../../components/ui/Card";

export default function TraineesPage() {
  const { t } = useTranslation("hr");
  const c = (key, opts) => t(key, { ns: "common", ...opts });
  // const {  getDepartment } = useData();
  const { trainees, loading, fireTrainee,page,pagination ,setPage,
    filters,
    updateFilter,
    applyFilters,
    resetFilters,
    isSearchMode,
    searchPage,
    searchPagination,
    changeSearchPage
  } = useTrainees();
  const [trainee, setTrainee] = useState("");
  const [fireTraineeModel, setFireTraineeModel] = useState(false);
  const [loadingFire, setLoadingFire] = useState("");

  const handelFire = (tr) => {
    setTrainee(tr);
    setFireTraineeModel(true);
  };

  const closeModal=()=>{
     setTrainee("");
    setFireTraineeModel(false);
  }

  const handleSubmit = async (e) => {
     e.preventDefault();
    setLoadingFire(true);
    console.log("tr", trainee);
    try {
      const formData = new FormData(e.currentTarget);

      const reason = formData.get("reason");
      console.log(reason);
      const data = await fireTrainee(trainee.id, reason);
      console.log("fireData", data);
      toast.success(data.message);
      setFireTraineeModel(false);
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "error in interview";
      console.log(message);
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      setLoadingFire(false);
    }
  };

  const columns = [
    {
      key: "id",
      header: t("trainees.traineeId"),
      render: (tr) => <span className={styles.id}>#{tr.id}</span>,
    },
    {
      key: "name",
      header: t("label.name", { ns: "common" }),
      render: (tr) => <strong className={styles.strong}>{tr.name}</strong>,
    },
    { key: "email", header: t("label.email", { ns: "common" }) },
    {
      key: "department",
      header: t("label.department", { ns: "common" }),
      render: (tr) => tr.department ?? "—",
    },
    {
      key: "supervisor",
      header: t("interviews.supervisor"),
      render: (tr) => tr.supervisor,
    },
    {
      key: "actions",
      header: t("label.actions", { ns: "common" }),
      render: (tr) => (
        <Button size="sm" loading={""} onClick={() => handelFire(tr)}>
          x
        </Button>
      ),
    },
  ];

  return (
    <>
      <Card className={styles.filters}>
        <Input
          placeholder={c("label.search")}
          value={filters.search}
          onChange={(e) => updateFilter("search", e.target.value)}
        />

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
          rows={trainees}
          emptyText={t("trainees.empty")}
        />

      </div>

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


      {fireTraineeModel && (
        <Modal  title={t("label.fire", { ns: "common" })+"-"+trainee.name} onClose={closeModal}>
          <form className={form.form} onSubmit={handleSubmit}>
            <Field label={t("label.reason", { ns: "common" })}>
              <Input type="textarea" row={3} name="reason" max={100} />
            </Field>

            <Button type="submit" loading={loadingFire}>
              {t("action.save", { ns: "common" })}
            </Button>
          </form>
        </Modal>
      )}
    </>
  );
}
