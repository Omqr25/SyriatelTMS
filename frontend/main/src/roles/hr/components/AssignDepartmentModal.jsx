import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import { Field, Select } from "../../../components/ui/Field";
import Modal from "../../../components/ui/Modal";
import form from "../../../components/ui/Form.module.css";
import { useToast } from "../../../context/ToastContext";
import { useData } from "../../../data/DataContext";
import useBusy from "../../../hooks/useBusy";
import useDepartments from "../../../hooks/HrHooks/useDepartments";
import useSupervisors from "../../../hooks/HrHooks/useSupervisors";
import { useEffect, useState } from "react";
import useApplicants from "../../../hooks/HrHooks/useApplicants";
import { toast } from "react-toastify";
export default function AssignDepartmentModal({ applicant, onClose }) {
  const { t } = useTranslation("hr");
  const { departments, getDepartment, assignApplicant } = useData();
  const { Departments } = useDepartments();
  const { supervisors, loading } = useSupervisors();
  // const { toast } = useToast();
  const { run, isBusy } = useBusy();
  const [departmentId, setDepartmentId] = useState("");

  const filteredSupervisors = supervisors.filter(
    (s) => String(s.department.id) == String(departmentId),
  );
  console.log("deId", departmentId);
  console.log(filteredSupervisors);
  const {assign,actionLoading}=useApplicants()

  const handleSubmit = async(e) => {
    e.preventDefault();
    try {
      const formData = new FormData(e.currentTarget);
      const data=await assign(applicant.id,formData.get("supervisorId"))
      console.log(data)
      toast.success(t("applicants.toast.assigned"))
    } catch (error) {
      console.log(error)
    }finally{
      onClose();
    }
    // const data = new FormData(e.currentTarget);
    // run("submit", async () => {
    //   await assignApplicant(applicant.id, {
    //     departmentId: Number(data.get("departmentId")),
    //     supervisorId: Number(data.get("supervisorId")),
    //   });
    //   toast(t("applicants.toast.assigned"));
    //   onClose();
    // });
  };

  useEffect(() => {
  if (Departments.length === 0) return;

  
   else {
    setDepartmentId(String(Departments[0].id));
  }
}, [Departments]);

  return (
    <Modal
      title={t("applicants.assignTitle", { name: applicant.name })}
      onClose={onClose}
    >
      <form className={form.form} onSubmit={handleSubmit}>
        <Field label={t("applicants.chooseDept")}>
          <Select
            onChange={(e) => setDepartmentId(e.target.value)}
            name="departmentId"
            required
            defaultValue={departmentId}
          >
            {Departments.map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}-{d.location}
              </option>
            ))}
          </Select>
        </Field>
        <Field label={t("applicants.chooseSupervisor")}>
          <Select
            name="supervisorId"
            required
            defaultValue={applicant.supervisorId}
          >
            <option value="">
              {!departmentId
                ? t("applicants.chooseDepartment")
                : loading
                  ?  t("applicants.loadingSupervisors") 
                  : filteredSupervisors.length === 0
                    ? t("applicants.emptyDepartment")
                    : t("applicants.chooseSupervisor") }
            </option>
            {filteredSupervisors.map((s) => (
              <option key={s.id} value={s.id}>
                {" "}
                {s.name} ({s.department.name})
              </option>
            ))}
          </Select>
        </Field>
        <Button type="submit" loading={actionLoading}>
          {t("action.save", { ns: "common" })}
        </Button>
      </form>
    </Modal>
  );
}
