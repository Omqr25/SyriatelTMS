import { Plus } from "lucide-react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import { Field, Input, Select } from "../../../components/ui/Field";
import form from "../../../components/ui/Form.module.css";
import Modal from "../../../components/ui/Modal";
import PageHeader from "../../../components/ui/PageHeader";
import Table from "../../../components/ui/Table";

import styles from "./Pages.module.css";
import useSupervisors from "../../../hooks/HrHooks/useSupervisors";
import Loading from "../../../components/ui/Loading";
import useDepartments from "../../../hooks/HrHooks/useDepartments";
import { toast } from "react-toastify";
export default function SupervisorsPage() {

  const { t } = useTranslation("hr");

  const [showAdd, setShowAdd] = useState(false);

  const { loading, supervisors, loadingCreate, createSupervisors ,refreshing} =
    useSupervisors();

  const { Departments } = useDepartments();

  const handleSubmit = async (e) => {
    e.preventDefault();
    const formData = new FormData(e.currentTarget);
    try {
      const data = await createSupervisors(formData);
      toast.success(data.message);
      setShowAdd(false);
    } catch (error) {
      const message =
        error.response?.data?.message ||
        error.message ||
        " Create department error";
      toast.error(Array.isArray(message) ? message.join("، ") : message);
    }
  };

  const columns = [
    {
      key: "name",
      header: t("label.name", { ns: "common" }),
      render: (s) => <strong className={styles.strong}>{s.name}</strong>,
    },
    { key: "email", header: t("label.email", { ns: "common" }) },
    {
      key: "department",
      header: t("label.department", { ns: "common" }),
      render: (s) => <span className={styles.accent}>{s.department.name}</span>,
    },
  ];

  return loading ? (
    <Loading />
  ) : (
    <>
      <PageHeader
        title={t("supervisors.listTitle")}
        update={refreshing}
        action={
          <Button icon={Plus} onClick={() => setShowAdd(true)}>
            {t("supervisors.add")}
          </Button>
        }
      />
      <Table columns={columns} rows={supervisors} />

      {showAdd && (
        <Modal title={t("supervisors.add")} onClose={() => setShowAdd(false)}>
          <form className={form.form} onSubmit={handleSubmit}>
            <Field label={t("supervisors.name")}>
              <Input name="name" required />
            </Field>
            <Field label={t("label.email", { ns: "common" })}>
              <Input type="email" name="email" required />
            </Field>
            <Field label={t("label.phoneNumber", { ns: "common" })}>
              <Input type="phone" name="phone_number" required />
            </Field>
            <Field label={t("label.department", { ns: "common" })}>
              <Select name="department_id" required>
                {Departments.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.name}-{d.location}
                  </option>
                ))}
              </Select>
            </Field>
            <Button type="submit" loading={loadingCreate}>
              {t("action.save", { ns: "common" })}
            </Button>
          </form>
        </Modal>
      )}
    </>
  );
}
