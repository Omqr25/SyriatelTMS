import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  CloudUpload,
  Send,
  AlertCircle,
  Loader2,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { toast } from "react-toastify";
import styles from "./Application.module.css";
import axios from "axios";
import NotAvailable from "./NotAvailable";

const initialForm = {
  fullname: "",
  email: "",
  phoneNumber: "",
  DateOfBirth: "",
  City: "",
  address: "",
  UniversityName: "",
  FieldOfStudy: "",
  AcademicMajor: "",
  YearOfStudy: "",
  GPA: "",
  TechnicalSkills: "",
  availability: "",
  previous: "",
  source: "",
  comments: "",
  nationalId: "",
};

const initialFiles = {
  academicQualification: null,
  cv: null,
  nationalID: null,
  personalPhoto: null,
};

export default function Application() {
  const { t, i18n } = useTranslation();

  const [form, setForm] = useState(initialForm);
  const [files, setFiles] = useState(initialFiles);
  const [errors, setErrors] = useState({});
  const [touched, setTouched] = useState({});
  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [pageLoading, setPageLoading] = useState(true);
  const [formAvailable, setFormAvailable] = useState(false);
  const [Group, setGroup] = useState("1");

  const BackArrow = i18n.dir() === "rtl" ? ArrowRight : ArrowLeft;
  const SubmitArrow = i18n.dir() === "rtl" ? ArrowLeft : ArrowRight;

  const validateField = (name, value) => {
    switch (name) {
      case "fullname":
        return !value.trim() ? t("form.errors.required") : "";
      case "email":
        return !value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)
          ? t("form.errors.invalidEmail")
          : "";
      case "phoneNumber":
        return !/^09\d{8}$/.test(value) ? t("form.errors.invalidPhone") : "";
      case "nationalId":
        return !/^\d{11}$/.test(value)
          ? t("form.errors.invalidNationalId")
          : "";
      case "GPA":
        const gpaNum = parseFloat(value);
        return isNaN(gpaNum) || gpaNum < 0 || gpaNum > 100
          ? t("form.errors.invalidGpa")
          : "";
      case "DateOfBirth":
      case "City":
      case "address":
      case "UniversityName":
      case "FieldOfStudy":
      case "AcademicMajor":
      case "YearOfStudy":
      case "TechnicalSkills":
      case "comments":
        return !value ? t("form.errors.required") : "";
      default:
        return "";
    }
  };

  const handleChange = (event) => {
    const { name, value } = event.target;
    setForm((prev) => ({ ...prev, [name]: value }));

    if (touched[name]) {
      setErrors((prev) => ({ ...prev, [name]: validateField(name, value) }));
    }
  };

  const handleBlur = (name) => {
    setTouched((prev) => ({ ...prev, [name]: true }));
    setErrors((prev) => ({ ...prev, [name]: validateField(name, form[name]) }));
  };

  const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
  const handleFileChange = (event, fieldName) => {
    const selectedFile = event.target.files?.[0] || null;
    if (!selectedFile) return;

    if (selectedFile.size > MAX_FILE_SIZE) {
      toast.error(
        t("form.errors.fileSize") || "File size must not exceed 5 MB",
      );
      event.target.value = "";
      return;
    }

    setFiles((prev) => ({ ...prev, [fieldName]: selectedFile }));
    setErrors((prev) => ({ ...prev, [fieldName]: "" }));
    setTouched((prev) => ({ ...prev, [fieldName]: true }));
  };

  const validateAll = () => {
    const newErrors = {};
    Object.keys(form).forEach((key) => {
      const err = validateField(key, form[key]);
      if (err) newErrors[key] = err;
    });

    // Mandatory files validation checks
    if (!files.academicQualification)
      newErrors.academicQualification = t("form.errors.requiredFile");
    if (!files.cv) newErrors.cv = t("form.errors.requiredFile");
    if (!files.nationalID) newErrors.nationalID = t("form.errors.requiredFile");
    if (!files.personalPhoto)
      newErrors.personalPhoto = t("form.errors.requiredFile");

    setErrors(newErrors);
    setTouched(
      Object.keys(form).reduce((acc, k) => ({ ...acc, [k]: true }), {
        academicQualification: true,
        cv: true,
        nationalID: true,
        personalPhoto: true,
      }),
    );
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    if (!validateAll()) {
      toast.error(
        t("form.errors.fixErrors") ||
          "Please fix the errors in the form before submitting.",
      );
      return;
    }

    setLoading(true);
    const formData = new FormData();
    formData.append("academic_qualification", files.academicQualification);
    formData.append("cv", files.cv);
    formData.append("national_id", files.nationalID);
    formData.append("personal_photo", files.personalPhoto);
    formData.append("training_program_id", Group);

    formData.append("full_name", form.fullname);
    formData.append("email", form.email);
    formData.append("phone_number", form.phoneNumber);
    formData.append("birth_date", form.DateOfBirth);
    formData.append("city", form.City);
    formData.append("year_of_study", form.YearOfStudy);
    formData.append("gpa", form.GPA);
    formData.append("commitment", form.availability === "yes" ? 1 : 0);
    formData.append("previous_training", form.previous === "yes" ? 1 : 0);
    formData.append("how_heard", form.source);
    formData.append("additional_info", form.comments);
    formData.append("home_address", form.address);
    formData.append("university", form.UniversityName);
    formData.append("field_of_study", form.FieldOfStudy);
    formData.append("academic_major", form.AcademicMajor);
    formData.append("technical_skills", form.TechnicalSkills);
    formData.append("national_id_number", form.nationalId);

    try {
      await axios.post(`${import.meta.env.VITE_API_URL}/application`, formData);
      setLoading(false);
      setSubmitted(true);
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      const message = error.response?.data?.message;
      if (Array.isArray(message)) {
        message.forEach((msg) => toast.error(msg));
      } else {
        toast.error(
          message ||
            "Error submitting the application. Please try again later.",
        );
      }
    } finally {
      setLoading(false);
    }
  };

  const checkFormAvailability = async () => {
    try {
      const response = await axios.get(
        `${import.meta.env.VITE_API_URL}/training_program/openProgram`,
      );
      setFormAvailable(response.data.data == null ? false : true);
      setGroup(response.data.data?.id || " ");
    } catch (error) {
      setFormAvailable(false);
    }
  };

  useEffect(() => {
    checkFormAvailability();
    const timer = setTimeout(() => {
      setPageLoading(false);
    }, 1000);
    return () => clearTimeout(timer);
  }, []);

  if (pageLoading) {
    return (
      <div className={styles.pageLoading}>
        <Loader2 size={60} color="red" className={styles.spinner} />
      </div>
    );
  }

  if (submitted) {
    return (
      <section className={styles.success}>
        <div className={styles.successCard}>
          <CheckCircle2 className={styles.successIcon} size={64} />
          <h1>{t("form.success.title")}</h1>
          <p>{t("form.success.message")}</p>
          <Link to="/" className={styles.homeButton}>
            {t("nav.home")}
          </Link>
        </div>
      </section>
    );
  }

  return formAvailable ? (
    <main className={styles.page}>
      <div className={styles.container}>
        <Link to="/" className={styles.back}>
          <BackArrow size={17} />
          <span>{t("form.back")}</span>
        </Link>

        <header className={styles.heading}>
          <span>{t("brand.program")}</span>
          <h1>
            {t("form.title")} <strong>{t("form.titleAccent")}</strong>
          </h1>
          <p>{t("form.intro")}</p>
          <div className={styles.groupBadge}>
            {t("form.group")}: <strong>{Group}</strong>
          </div>
        </header>

        <form onSubmit={handleSubmit} className={styles.form} noValidate>
          <div className={styles.grid}>
            <TextField
              name="fullname"
              label={t("form.fullName")}
              placeholder={t("form.placeholders.name")}
              value={form.fullname}
              onChange={handleChange}
              onBlur={() => handleBlur("fullname")}
              error={touched.fullname && errors.fullname}
              required
            />

            <TextField
              name="email"
              label={t("form.email")}
              placeholder={t("form.placeholders.email")}
              type="email"
              value={form.email}
              onChange={handleChange}
              onBlur={() => handleBlur("email")}
              error={touched.email && errors.email}
              required
            />

            <TextField
              name="phoneNumber"
              label={t("form.phone")}
              placeholder="09xxxxxxxx"
              type="text"
              maxLength={10}
              value={form.phoneNumber}
              onChange={(e) => {
                const val = e.target.value.replace(/\D/g, "");
                handleChange({ target: { name: "phoneNumber", value: val } });
              }}
              onBlur={() => handleBlur("phoneNumber")}
              error={touched.phoneNumber && errors.phoneNumber}
              required
            />

            <TextField
              name="DateOfBirth"
              label={t("form.dob")}
              type="date"
              value={form.DateOfBirth}
              onChange={handleChange}
              onBlur={() => handleBlur("DateOfBirth")}
              error={touched.DateOfBirth && errors.DateOfBirth}
              required
            />

            <SelectField
              name="City"
              label={t("form.city")}
              value={form.City}
              onChange={handleChange}
              onBlur={() => handleBlur("City")}
              options={t("form.cityOptions", { returnObjects: true })}
              error={touched.City && errors.City}
              required
            />

            <TextField
              name="address"
              label={t("form.address")}
              placeholder={t("form.placeholders.address")}
              value={form.address}
              onChange={handleChange}
              onBlur={() => handleBlur("address")}
              error={touched.address && errors.address}
              required
            />

            <TextField
              name="UniversityName"
              label={t("form.university")}
              placeholder={t("form.placeholders.university")}
              value={form.UniversityName}
              onChange={handleChange}
              onBlur={() => handleBlur("UniversityName")}
              error={touched.UniversityName && errors.UniversityName}
              required
            />

            <SelectField
              name="FieldOfStudy"
              label={t("form.field")}
              value={form.FieldOfStudy}
              onChange={handleChange}
              onBlur={() => handleBlur("FieldOfStudy")}
              options={t("form.fieldOptions", { returnObjects: true })}
              error={touched.FieldOfStudy && errors.FieldOfStudy}
              required
            />

            <TextField
              name="AcademicMajor"
              label={t("form.major")}
              placeholder={t("form.placeholders.major")}
              value={form.AcademicMajor}
              onChange={handleChange}
              onBlur={() => handleBlur("AcademicMajor")}
              error={touched.AcademicMajor && errors.AcademicMajor}
              required
            />

            <SelectField
              name="YearOfStudy"
              label={t("form.year")}
              value={form.YearOfStudy}
              onChange={handleChange}
              onBlur={() => handleBlur("YearOfStudy")}
              options={t("form.yearOptions", { returnObjects: true })}
              error={touched.YearOfStudy && errors.YearOfStudy}
              required
            />

            <TextField
              name="GPA"
              label={t("form.gpa")}
              placeholder={t("form.placeholders.gpa")}
              type="text"
              value={form.GPA}
              onChange={(e) => {
                const val = e.target.value.replace(/[^0-9.]/g, "");
                handleChange({ target: { name: "GPA", value: val } });
              }}
              onBlur={() => handleBlur("GPA")}
              error={touched.GPA && errors.GPA}
              required
            />

            <TextField
              name="nationalId"
              label={t("form.nationalIdNumber")}
              placeholder={"14XXXXXXXXX"}
              type="text"
              maxLength={11}
              value={form.nationalId}
              onChange={(e) => {
                const val = e.target.value.replace(/\D/g, "");
                handleChange({ target: { name: "nationalId", value: val } });
              }}
              onBlur={() => handleBlur("nationalId")}
              error={touched.nationalId && errors.nationalId}
              required
            />

            <div className={styles.fullSpanField}>
              <TextField
                name="TechnicalSkills"
                label={t("form.skills")}
                placeholder={t("form.placeholders.skills")}
                value={form.TechnicalSkills}
                onChange={handleChange}
                onBlur={() => handleBlur("TechnicalSkills")}
                error={touched.TechnicalSkills && errors.TechnicalSkills}
                required
              />
            </div>

            <FileField
              name="academicQualification"
              label={t("form.qualification")}
              hint="5 MB"
              file={files.academicQualification}
              onChange={handleFileChange}
              error={
                touched.academicQualification && errors.academicQualification
              }
              required
              type=".pdf"
            />
            <FileField
              name="cv"
              label={t("form.cv")}
              hint="5 MB"
              file={files.cv}
              onChange={handleFileChange}
              error={touched.cv && errors.cv}
              required
              type=".pdf"
            />
            <FileField
              name="nationalID"
              label={t("form.nationalId")}
              hint="5 MB"
              file={files.nationalID}
              onChange={handleFileChange}
              error={touched.nationalID && errors.nationalID}
              required
              type=".pdf"
            />
            <FileField
              name="personalPhoto"
              label={t("form.photo")}
              hint="5 MB"
              file={files.personalPhoto}
              onChange={handleFileChange}
              error={touched.personalPhoto && errors.personalPhoto}
              required
              type=".pdf"
            />
          </div>

          <div className={styles.optionSections}>
            <RadioField
              name="availability"
              label={t("form.availability")}
              value={form.availability}
              onChange={handleChange}
              options={[
                ["yes", t("form.yes")],
                ["no", t("form.no")],
              ]}
            />
            <RadioField
              name="previous"
              label={t("form.previous")}
              value={form.previous}
              onChange={handleChange}
              options={[
                ["yes", t("form.yes")],
                ["no", t("form.no")],
              ]}
            />
            <RadioField
              name="source"
              label={t("form.how")}
              value={form.source}
              onChange={handleChange}
              options={t("form.sourceOptions", {
                returnObjects: true,
              }).map((item) => [item.value, item.label])}
            />
          </div>

          <div className={styles.fullField}>
            <label htmlFor="comments">
              {t("form.comments")} <em>*</em>
            </label>
            <textarea
              id="comments"
              name="comments"
              value={form.comments}
              onChange={handleChange}
              onBlur={() => handleBlur("comments")}
              placeholder={t("form.placeholders.comments")}
              rows={5}
              required
              className={
                touched.comments && errors.comments ? styles.inputError : ""
              }
            />
            {touched.comments && errors.comments && (
              <span className={styles.errorText}>
                <AlertCircle size={14} /> {errors.comments}
              </span>
            )}
          </div>

          <button type="submit" disabled={loading} className={styles.submit}>
            {loading ? (
              <Loader2 size={18} className={styles.spinner} />
            ) : (
              <>
                <Send size={18} />
                <span>{t("form.submit")}</span>
                <SubmitArrow size={18} />
              </>
            )}
          </button>
        </form>
      </div>
    </main>
  ) : (
    <NotAvailable />
  );
}

function TextField({
  name,
  label,
  placeholder,
  type = "text",
  value,
  onChange,
  onBlur,
  required = false,
  error,
  maxLength,
}) {
  return (
    <div className={styles.field}>
      <label htmlFor={name}>
        {label}
        {required && <em>*</em>}
      </label>
      <div className={styles.inputWrapper}>
        <input
          id={name}
          name={name}
          type={type}
          value={value}
          onChange={onChange}
          onBlur={onBlur}
          placeholder={placeholder}
          maxLength={maxLength}
          className={error ? styles.inputError : ""}
        />
      </div>
      {error && (
        <span className={styles.errorText}>
          <AlertCircle size={14} /> {error}
        </span>
      )}
    </div>
  );
}

function SelectField({
  name,
  label,
  value,
  onChange,
  onBlur,
  options = [],
  required = false,
  error,
}) {
  return (
    <div className={styles.field}>
      <label htmlFor={name}>
        {label}
        {required && <em>*</em>}
      </label>
      <select
        id={name}
        name={name}
        value={value}
        onChange={onChange}
        onBlur={onBlur}
        className={error ? styles.inputError : ""}
      >
        <option value="">— Select —</option>
        {options.map((option) => (
          <option key={option.value || option} value={option.value || option}>
            {option.label || option}
          </option>
        ))}
      </select>
      {error && (
        <span className={styles.errorText}>
          <AlertCircle size={14} /> {error}
        </span>
      )}
    </div>
  );
}

function FileField({
  name,
  label,
  hint,
  file,
  onChange,
  required = false,
  type = ".pdf",
  error,
}) {
  const { t } = useTranslation();
  return (
    <div
      className={`${styles.fileField} ${
        error ? styles.fileErrorContainer : ""
      }`}
    >
      <label>
        {label}
        {required && <em>*</em>}
      </label>
      <div className={styles.fileBox}>
        <CloudUpload size={22} />
        <strong>{file ? file.name : t("form.chooseFile")}</strong>
        <small>
          {file
            ? `${Math.round(file.size / 1024)} KB`
            : type === ".pdf"
              ? `${t("form.pdfHint")} · max ${hint}`
              : `${t("form.imgOrPdf")} · max ${hint}`}
        </small>
        <input
          type="file"
          accept={type}
          onChange={(event) => onChange(event, name)}
        />
      </div>
      {error && (
        <span className={styles.errorText}>
          <AlertCircle size={14} /> {error}
        </span>
      )}
    </div>
  );
}

function RadioField({ name, label, value, onChange, options }) {
  return (
    <div className={styles.radioField}>
      <span>
        {label} <em>*</em>
      </span>
      <div className={styles.radioOptions}>
        {options.map(([optionValue, optionLabel]) => (
          <label key={optionValue} className={styles.radioLabel}>
            <input
              type="radio"
              name={name}
              value={optionValue}
              checked={value === optionValue}
              onChange={onChange}
            />
            <span className={styles.radioCustom} />
            {optionLabel}
          </label>
        ))}
      </div>
    </div>
  );
}
