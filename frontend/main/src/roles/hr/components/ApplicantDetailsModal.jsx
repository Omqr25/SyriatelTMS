import { FileText } from "lucide-react";
import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import Modal from "../../../components/ui/Modal";
import styles from "./ApplicantDetailsModal.module.css";
import { Link } from "react-router-dom";
import StatusBadge from "./StatusBadge";
export default function ApplicantDetailsModal({
  applicant,
  onClose,
  onAssign,
  onSchedule,
}) {
  const { t } = useTranslation("hr");
  const c = (key) => t(key, { ns: "common" });
  console.log("Applicant", applicant);
  return (
    <Modal
      title={t("applicants.detailsTitle", { name: applicant.full_name })}
      onClose={onClose}
    > 
      
      <dl className={styles.grid}>

          <div>
          <dt>{c("label.status")}</dt>
          <dd> <StatusBadge status={applicant.status } /> </dd>
        </div>

        <div>
          <dt>{c("label.gpa")}</dt>
          <dd>{applicant.gpa}%</dd>
        </div>
        <div>
          <dt>{c("label.academicYear")}</dt>
          <dd>{applicant.year_of_study}</dd>
        </div>
        <div>
          <dt>{c("label.university")}</dt>
          <dd>{applicant.university|| "empty "}</dd>
        </div>

        <div>
          <dt>{c("label.major")}</dt>
          <dd>{applicant.academic_major || "empty" }</dd>
        </div>

        <div>
          <dt>{c("label.fieldOfStudy")}</dt>
          <dd>{applicant.field_of_study ||"empty " }</dd>
        </div>
        <div>
          <dt>{c("label.city")}</dt>
          <dd>{applicant.city}</dd>
        </div>

        <div>
          <dt>{c("label.address")}</dt>
          <dd>{applicant.home_address|| " empty"}</dd>
        </div>

        <div>
          <dt>{c("label.phoneNumber")}</dt>
          <dd>{applicant.phone_number }</dd>
        </div>

         <div>
          <dt>{c("label.email")}</dt>
          <dd>{applicant.email}</dd>
        </div>

      
         <div>
          <dt>{c("label.age")}</dt>
          <dd>{new Date().getFullYear() - new Date(applicant.birth_date).getFullYear()}</dd>
        </div>

         <div>
          <dt>{c("label.technicalSkills")}</dt>
          <dd>{applicant.technical_skills ||"empty "}</dd>
        </div>

         <div>
          <dt>{c("label.SubmissionDate")}</dt>
          <dd>{ new Date(applicant.submission_date).toISOString().split('T')[0] ||"empty "}</dd>
        </div>

        <div>
          <dt>{c("label.additionalInfo")}</dt>
          <dd>{applicant.additional_info ||"empty "}</dd>
        </div>
      </dl>

      <h5 className={styles.sub}>{t("applicants.attachments")}</h5>
      <div className={styles.files}>
        {/* {applicant.files.map((f) => (
          <span key={f} className={styles.file}><FileText size={14} /> {f}</span>
        ))} */}
        <a href={applicant.cv} rel="noopener noreferrer" target="_blank">

        {console.log("cv:",applicant.cv)}
          <span className={styles.file}>
            <FileText size={14} /> {c("label.cv")}
          </span>
        </a>

        <a href={applicant.academic_qualification} rel="noopener noreferrer"  target="_blank">
          
          <span className={styles.file}>
            <FileText size={14} /> {c("label.academicQualification")}
          </span>
        </a>

        <a href={applicant.national_id} rel="noopener noreferrer" target="_blank">
          <span className={styles.file}>
            <FileText size={14} /> {c("label.nationalID")}
          </span>
        </a>

        <a href={applicant.personal_photo} rel="noopener noreferrer" target="_blank">
          <span className={styles.file}>
            <FileText size={14} /> {c("label.personalPhoto")}
          </span>
        </a>
      </div>

      <div className={styles.actions}>
        {applicant.status=="pending" && <Button onClick={onAssign}>{t("applicants.assignToDept")}</Button>}

        {applicant.status=="qualified" && <Button variant="dark" onClick={onSchedule}>
          {t("applicants.scheduleInterview")}
        </Button> }
        

      </div>
    </Modal>
  );
}
