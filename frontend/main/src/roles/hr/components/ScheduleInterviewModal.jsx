import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import { Field, Input, Select } from "../../../components/ui/Field";
import Modal from "../../../components/ui/Modal";
import form from "../../../components/ui/Form.module.css";

import { useState } from "react";
import { createInterviewApi } from "../../../Api/HrApi/interviewApi";
import { toast } from "react-toastify";
export default function ScheduleInterviewModal({ applicant, onClose ,update }) {
  console.log("applicants", applicant);
  const { t } = useTranslation("hr");
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e) => {
    setLoading(true);
    e.preventDefault();
    try {
      const formData = new FormData(e.currentTarget);
      const body = {
        trainer_id: formData.get("trainer_id"),
        time: formData.get("date") + " " + formData.get("time"),
        location: formData.get("location"),
      };

      // console.log("body",body)
      const data = await createInterviewApi(applicant.id, body);
      // console.log(data)
      toast.success(data.message || "success");
      if (update) {
        await update(true)
      }
       onClose();
    } catch (error) {
      const message =
        error.response?.data?.message || error.message || "error in interview";

      toast.error(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      setLoading(false);
     
    }
  };

  // console.log("ScheduleInterviewModal",applicant.supervisors)
  return (
    <Modal
      title={t("applicants.interviewTitle", { name: applicant.name })}
      onClose={onClose}
    >
      <form className={form.form} onSubmit={handleSubmit}>
        <Field label={t("applicants.supervisorName")}>
          <Select
            name="trainer_id"
            required
            defaultValue={applicant.supervisorId}
          >
            <option
              key={
                applicant?.supervisors?.name || applicant?.supervisors[0]?.name
              }
              value={applicant.supervisors.id || applicant.supervisors[0].id}
            >
              {applicant.supervisors.name || applicant.supervisors[0].name}
            </option>
          </Select>
        </Field>
        <div className={form.row}>
          <Field label={t("applicants.interviewDate")}>
            <Input type="date" name="date" required />
          </Field>
          <Field label={t("applicants.interviewTime")}>
            <Input type="time" name="time" required />
          </Field>
        </div>
        <Field label={t("applicants.interviewLocation")}>
          <Input
            type="text"
            name="location"
            required
            defaultValue={t("applicants.defaultLocation")}
          />
        </Field>
        <Button type="submit" loading={loading}>
          {t("action.save", { ns: "common" })}
        </Button>
      </form>
    </Modal>
  );
}
