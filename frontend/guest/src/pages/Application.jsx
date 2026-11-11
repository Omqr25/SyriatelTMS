// import { useState } from "react";
// import {
//   ArrowLeft,
//   ArrowRight,
//   CalendarDays,
//   CheckCircle2,
//   CloudUpload,
//   FileText,
//   Send,
// } from "lucide-react";
// import { Link } from "react-router-dom";
// import { useTranslation } from "react-i18next";
// import styles from "./Application.module.css";

// const initial = {
//   fullName: "",
//   email: "",
//   phone: "",
//   dob: "",
//   city: "",
//   address: "",
//   university: "",
//   field: "",
//   major: "",
//   year: "",
//   gpa: "",
//   skills: "",
//   availability: "",
//   previous: "",
//   source: "",
//   comments: "",
// };
// export default function Application() {
//   const { t, i18n } = useTranslation();
//   const [form, setForm] = useState(initial);
//   const [files, setFiles] = useState({
//     qualification: null,
//     cv: null,
//     nationalId: null,
//     photo: null,
//   });
//   const [sent, setSent] = useState(false);
//   const Arrow = i18n.dir() === "rtl" ? ArrowLeft : ArrowRight;
//   const update = (e) => setForm({ ...form, [e.target.name]: e.target.value });
//   const file = (name, e) =>
//     setFiles({ ...files, [name]: e.target.files?.[0] || null });
//   const submit = (e) => {
//     e.preventDefault();
//     setSent(true);
//     window.scrollTo({ top: 0, behavior: "smooth" });
//   };
//   if (sent)
//     return (
//       <section className={styles.success}>
//         <div>
//           <CheckCircle2 size={62} />
//           <h1>
//             {i18n.language === "ar"
//               ? "تم استلام طلبك بنجاح"
//               : "Application received successfully"}
//           </h1>
//           <p>
//             {i18n.language === "ar"
//               ? "شكراً لاهتمامك ببرنامج تطوير المواهب. سيتم مراجعة طلبك والتواصل معك عند الحاجة."
//               : "Thank you for your interest in the Talent Development Program. Your application will be reviewed and we will contact you when needed."}
//           </p>
//           <Link to="/">{t("nav.home")}</Link>
//         </div>
//       </section>
//     );
//   return (
//     <section className={styles.page}>
//       <div className={styles.container}>
//         <Link to="/" className={styles.back}>
//           <ArrowLeft size={16} />
//           {t("form.back")}
//         </Link>
//         <div className={styles.heading}>
//           <span>{t("brand.program")}</span>
//           <h1>
//             {t("form.title")} <strong>{t("form.titleAccent")}</strong>
//           </h1>
//           <p>{t("form.intro")}</p>
//         </div>
//         <div className={styles.google}>
//           <div className={styles.googleIcon}>G</div>
//           <div>
//             <b>Google Account</b>
//             <span>{t("form.googleNotice")}</span>
//           </div>
//           <button type="button">{t("form.switchAccount")}</button>
//         </div>
//         <form onSubmit={submit} className={styles.form}>
//           <div className={styles.grid}>
//             {textField(
//               "fullName",
//               t("form.fullName"),
//               t("form.placeholders.name"),
//               "text",
//               true,
//             )}
//             {textField(
//               "email",
//               t("form.email"),
//               t("form.placeholders.email"),
//               "email",
//               true,
//             )}
//             {textField(
//               "phone",
//               t("form.phone"),
//               t("form.placeholders.phone"),
//               "tel",
//               true,
//             )}
//             {textField("dob", t("form.dob"), "", "date", true)}
//             {selectField("city", t("form.city"), t("form.cityOptions"), true)}
//             {textField(
//               "address",
//               t("form.address"),
//               t("form.placeholders.address"),
//               "text",
//               true,
//             )}
//             {textField(
//               "university",
//               t("form.university"),
//               t("form.placeholders.university"),
//               "text",
//               true,
//             )}
//             {selectField(
//               "field",
//               t("form.field"),
//               t("form.fieldOptions"),
//               true,
//             )}
//             {textField(
//               "major",
//               t("form.major"),
//               t("form.placeholders.major"),
//               "text",
//               true,
//             )}
//             {selectField("year", t("form.year"), t("form.yearOptions"), true)}
//             {textField(
//               "gpa",
//               t("form.gpa"),
//               t("form.placeholders.gpa"),
//               "number",
//               true,
//             )}
//             {textField(
//               "skills",
//               t("form.skills"),
//               t("form.placeholders.skills"),
//               "text",
//               true,
//             )}
//             <FileField
//               name="qualification"
//               label={t("form.qualification")}
//               hint="1 GB"
//               required
//             />
//             <FileField name="cv" label={t("form.cv")} hint="10 GB" required />
//             <FileField
//               name="nationalId"
//               label={t("form.nationalId")}
//               hint="10 GB"
//               required
//             />
//             <FileField
//               name="photo"
//               label={t("form.photo")}
//               hint="10 GB"
//               required
//             />
//           </div>
//           <div className={styles.optionSections}>
//             <RadioField
//               name="availability"
//               label={t("form.availability")}
//               value={form.availability}
//               onChange={update}
//               options={[
//                 ["yes", t("form.yes")],
//                 ["no", t("form.no")],
//               ]}
//             />
//             <RadioField
//               name="previous"
//               label={t("form.previous")}
//               value={form.previous}
//               onChange={update}
//               options={[
//                 ["yes", t("form.yes")],
//                 ["no", t("form.no")],
//               ]}
//             />
//             <RadioField
//               name="source"
//               label={t("form.how")}
//               value={form.source}
//               onChange={update}
//               options={t("form.sourceOptions").map((x, i) => [String(i), x])}
//             />
//           </div>
//           <label className={styles.fullField}>
//             <span>
//               {t("form.comments")} <em>*</em>
//             </span>
//             <textarea
//               name="comments"
//               value={form.comments}
//               onChange={update}
//               placeholder={t("form.placeholders.comments")}
//               required
//               rows="5"
//             />
//           </label>
//           <button className={styles.submit} type="submit">
//             <Send size={18} />
//             {t("form.submit")}
//             <Arrow size={18} />
//           </button>
//         </form>
//       </div>
//     </section>
//   );
//   function textField(
//     name,
//     label,
//     placeholder,
//     type = "text",
//     required = false,
//   ) {
//     return (
//       <label className={styles.field}>
//         <span>
//           {label} {required && <em>*</em>}
//         </span>
//         <input
//           name={name}
//           type={type}
//           value={form[name]}
//           onChange={update}
//           placeholder={placeholder}
//           required={required}
//         />
//         {type === "date" && (
//           <CalendarDays className={styles.insideIcon} size={17} />
//         )}
//       </label>
//     );

//   }
//   function selectField(name, label, options, required = false) {
//     return (
//       <label className={styles.field}>
//         <span>
//           {label} {required && <em>*</em>}
//         </span>
//         <select
//           name={name}
//           value={form[name]}
//           onChange={update}
//           required={required}
//         >
//           <option value="">—</option>
//           {options.map((o) => (
//             <option key={o} value={o}>
//               {o}
//             </option>
//           ))}
//         </select>
//       </label>
//     );
//   }

//   function FileField({ name, label, hint, required }) {
//     return (
//       <label className={styles.fileField}>
//         <span>
//           {label} {required && <em>*</em>}
//         </span>
//         <div className={styles.fileBox}>
//           <CloudUpload size={22} />
//           <b>{files[name] ? files[name].name : t("form.chooseFile")}</b>
//           <small>
//             {files[name]
//               ? Math.round(files[name].size / 1024) + " KB"
//               : `${t("form.pdfHint")} · max ${hint}`}
//           </small>
//           <input
//             type="file"
//             accept="application/pdf"
//             onChange={(e) => file(name, e)}
//             required={required}
//           />
//         </div>
//       </label>
//     );
//   }

//   function RadioField({ name, label, value, onChange, options }) {
//     return (
//       <div className={styles.radioField}>
//         <span>
//           {label} <em>*</em>
//         </span>
//         <div>
//           {options.map(([v, text]) => (
//             <label key={v}>
//               <input
//                 type="radio"
//                 name={name}
//                 value={v}
//                 checked={value === v}
//                 onChange={onChange}
//                 required
//               />
//               <i />
//               {text}
//             </label>
//           ))}
//         </div>
//       </div>
//     );
//   }
// }

import { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import {
  ArrowLeft,
  ArrowRight,
  CalendarDays,
  CheckCircle2,
  CloudUpload,
  Send,
} from "lucide-react";
import { useTranslation } from "react-i18next";
import { toast } from "react-toastify";
import styles from "./Application.module.css";
import axios from "axios";
import { Loader2 } from "lucide-react";
import NotAvailable from "./NotAvailable";
/*
 * Initial form state.
 * Keeping the initial state in one place makes
 * resetting or managing the form easier.
 */
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
  nationalId:"",

};

/*
 * Files uploaded by the applicant.
 * We only store the File object here.
 * Uploading to the backend/storage will be handled later.
 */
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
  const [submitted, setSubmitted] = useState(false);
  const [loading, setLoading] = useState(false);
  const [pageLoading, setPageLoading] = useState(true);
  const [formAvailable, setFormAvailable] = useState(false);
  const [Group, setGroup] = useState("1");

  /*
   * Determine the correct arrow according to
   * the current language direction.
   */
  const BackArrow = i18n.dir() === "rtl" ? ArrowRight : ArrowLeft;
  const SubmitArrow = i18n.dir() === "rtl" ? ArrowLeft : ArrowRight;

  /*
   * Update a normal form field.
   */
  const handleChange = (event) => {
    const { name, value } = event.target;

    setForm((previousForm) => ({
      ...previousForm,
      [name]: value,
    }));
  };

  /*
   * Store the selected file.
   *
   * No API request is made here.
   * The backend integration will be added later.
   */
  const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
  const handleFileChange = (event, fieldName) => {
    const selectedFile = event.target.files?.[0] || null;


      if (!selectedFile) return;

  if (selectedFile.size > MAX_FILE_SIZE) {
    toast.error("حجم الملف يجب ألا يتجاوز 5 MB");
    event.target.value = "";
    return;
  }

    setFiles((previousFiles) => ({
      ...previousFiles,
      [fieldName]: selectedFile,
    }));
  };

  /*
   * Temporary submit handler.
   *
   * For now, we only show the success screen.
   * Later this function can call the backend API.
   */
  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    const formData = new FormData();
    formData.append("academic_qualification", files.academicQualification);
    formData.append("cv", files.cv);
    formData.append("national_id", files.nationalID);
    formData.append("personal_photo", files.personalPhoto);

    formData.append("training_program_id", Group);

    formData.append("full_name",form.fullname)
    formData.append("email",form.email)
    formData.append("phone_number",form.phoneNumber)
    formData.append("birth_date",form.DateOfBirth)
    formData.append("city",form.City)
    formData.append("year_of_study",form.YearOfStudy)
    formData.append("gpa",form.GPA)
    formData.append("commitment",form.availability=='yes'?1:0)
    formData.append("previous_training",form.previous=='yes'?1:0)
    formData.append("how_heard",form.source)
    formData.append("additional_info",form.comments)
    formData.append("home_address",form.address)
    formData.append("university",form.UniversityName)
    formData.append("field_of_study",form.FieldOfStudy)
    formData.append("academic_major",form.AcademicMajor)
    formData.append("technical_skills",form.TechnicalSkills)
    formData.append("national_id_number",form.nationalId)
    console.log("FormData:", formData);
    console.group("================ APPLICATION DATA ================");

    for (const [key, value] of formData.entries()) {
      /*
       * إذا كان العنصر File
       */
      if (value instanceof File) {
        console.log(key, {
          name: value.name,
          type: value.type,
          size: `${(value.size / 1024).toFixed(2)} KB`,
          file: value,
        });
      } else {
        /*
         * البيانات النصية
         */
        console.log(key, value);
      }
    }

    console.groupEnd();

    try {
      const res = await axios.post(
        // "https://syriatel.houseofgeniuses.tech/api/v1/application",
        `${import.meta.env.VITE_API_URL}/application`,
        formData,
      );
      console.log("res:", res);
      setLoading(false);
      setSubmitted(true);
    } catch (error) {
      // console.log(error);
      const message = error.response?.data?.message;
      console.log(error.response?.data?.errors)

      if (Array.isArray(message)) {
        message.forEach((msg) => {
          toast.error(msg);
        });

        return;
      } else {
        toast.error(
          message ||
            "Error submitting the application. Please try again later.",
        );
      }
    } finally {
      setLoading(false);
    }
    

    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  };

  // check if the form is available for submission
  const checkFormAvailability = async () => {
    try {
      const response = await axios.get(
        // "https://syriatel.houseofgeniuses.tech/api/v1/training_program/openProgram",
        `${import.meta.env.VITE_API_URL}/training_program/openProgram`,
      );
      setFormAvailable(response.data.data==null?false:true);
      console.log("checkdata",response.data.data)
      setGroup(response.data.data?.id||" ");
    }catch (error) {
         const message = error.response?.data?.message;
      console.log(message)
      setFormAvailable(false);
    }
  }

    useEffect(() => {
    // Simulate a loading delay (e.g., fetching data)
    checkFormAvailability();

    const timer = setTimeout(() => {
      setPageLoading(false);
    }, 1000);

    },[])
  /*
   * Loading screen
   */
  if (pageLoading) {
    return (
        <div className={styles.pageLoading}>
          <Loader2 size={60} color="red" className={styles.spinner} />
        </div>
    )
  }
   /*
   * Success screen
   */
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

  return formAvailable  ? (
    <main className={styles.page}>
      <div className={styles.container}>
        {/* Back to home */}
        <Link to="/" className={styles.back}>
          <BackArrow size={17} />
          <span>{t("form.back")}</span>
        </Link>

        {/* Page heading */}
        <header className={styles.heading}>
          <span>{t("brand.program")}</span>

          <h1>
            {t("form.title")} <strong>{t("form.titleAccent")}</strong>
          </h1>

          <p>{t("form.intro")}</p>
          <p>Group: {Group}</p>
        </header>

        <form onSubmit={handleSubmit} className={styles.form}>
          {/* Personal and academic information */}
          <div className={styles.grid}>
            <TextField
              name="fullname"
              label={t("form.fullName")}
              placeholder={t("form.placeholders.name")}
              value={form.fullname}
              onChange={handleChange}
              required
            />

            <TextField
              name="email"
              label={t("form.email")}
              placeholder={t("form.placeholders.email")}
              type="email"
              value={form.email}
              onChange={handleChange}
              required
            />

            <TextField
              name="phoneNumber"
              label={t("form.phone")}
              placeholder={t("form.placeholders.phone")}
              type="tel"
              value={form.phoneNumber}
              onChange={handleChange}
              required
            />

            <TextField
              name="DateOfBirth"
              label={t("form.dob")}
              type="date"
              value={form.DateOfBirth}
              onChange={handleChange}
              required
              // icon={<CalendarDays size={17} />}
            />

            <SelectField
              name="City"
              label={t("form.city")}
              value={form.City}
              onChange={handleChange}
              options={t("form.cityOptions", {
                returnObjects: true,
              })}
              required
            />

            <TextField
              name="address"
              label={t("form.address")}
              placeholder={t("form.placeholders.address")}
              value={form.address}
              onChange={handleChange}
              required
            />

            <TextField
              name="UniversityName"
              label={t("form.university")}
              placeholder={t("form.placeholders.university")}
              value={form.UniversityName}
              onChange={handleChange}
              required
            />

            <SelectField
              name="FieldOfStudy"
              label={t("form.field")}
              value={form.FieldOfStudy}
              onChange={handleChange}
              options={t("form.fieldOptions", {
                returnObjects: true,
              })}
              required
            />

            <TextField
              name="AcademicMajor"
              label={t("form.major")}
              placeholder={t("form.placeholders.major")}
              value={form.AcademicMajor}
              onChange={handleChange}
              required
            />

            <SelectField
              name="YearOfStudy"
              label={t("form.year")}
              value={form.YearOfStudy}
              onChange={handleChange}
              options={t("form.yearOptions", {
                returnObjects: true,
              })}
              required
            />

            <TextField
              name="GPA"
              label={t("form.gpa")}
              placeholder={t("form.placeholders.gpa")}
              type="number"
              value={form.GPA}
              onChange={handleChange}
              required
            />

             <TextField
              name="nationalId"
              label={t("form.nationalIdNumber")}
              placeholder={"14XXXXXXXXX"}
              type="number"
              value={form.nationalId}
              onChange={handleChange}
              required
            />

            <TextField
              name="TechnicalSkills"
              label={t("form.skills")}
              placeholder={t("form.placeholders.skills")}
              value={form.TechnicalSkills}
              onChange={handleChange}
              required
              
              
            />

            {/* Required documents */}
            <div>

            </div>
            <FileField
              name="academicQualification"
              label={t("form.qualification")}
              hint="5 MB"
              file={files.academicQualification}
              onChange={handleFileChange}
              required
              type=".pdf"
            />

            <FileField
              name="cv"
              label={t("form.cv")}
              hint="5 MB"
              file={files.cv}
              onChange={handleFileChange}
              required
              type=".pdf"
            />

            <FileField
              name="nationalID"
              label={t("form.nationalId")}
              hint="5 MB"
              file={files.nationalID}
              onChange={handleFileChange}
              required
              type=".pdf"
            />

            <FileField
              name="personalPhoto"
              label={t("form.photo")}
              hint="5 MB"
              file={files.personalPhoto}
              onChange={handleFileChange}
              required
              type=".pdf"
            />
          </div>

          {/* Radio questions */}
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
              }).map((item, index) => [item, item])}
            />
          </div>

          {/* Additional comments */}
          <div className={styles.fullField}>
            <label htmlFor="comments">
              {t("form.comments")} <em>*</em>
            </label>

            <textarea
              id="comments"
              name="comments"
              value={form.comments}
              onChange={handleChange}
              placeholder={t("form.placeholders.comments")}
              rows={5}
              required
            />
          </div>

          {/* Submit button */}
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
  ):
    <NotAvailable/>
  ;
}

/* =========================================================
   Reusable Components
   ========================================================= */

/*
 * Normal text / email / phone / date / number field.
 */
function TextField({
  name,
  label,
  placeholder,
  type = "text",
  value,
  onChange,
  required = false,
  icon,
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
          placeholder={placeholder}
          required={required}
        />

        {icon && <span className={styles.inputIcon}>{icon}</span>}
      </div>
    </div>
  );
}

/*
 * Select field.
 */
function SelectField({
  name,
  label,
  value,
  onChange,
  options = [],
  required = false,
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
        required={required}
      >
        <option value="">—</option>

        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
            {console.log(option.value)}
          </option>
        ))}
      </select>
    </div>
  );
}

/*
 * File upload component.
 *
 * It only handles the UI and selected File object.
 * No upload request is performed.
 */
function FileField({ name, label, hint, file, onChange, required = false ,type=".pdf,.jpg,.jpeg,.png,.webp"}) {
  const { t } = useTranslation();

  return (
    <div className={styles.fileField}>
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
            : type === ".pdf" ? `${t("form.pdfHint")} · max ${hint}` : `${t("form.imgOrPdf")} · max ${hint}`}
        </small>

        <input
          type="file"
          // accept="application/pdf"
          accept={type}
          onChange={(event) => onChange(event, name)}
          required={required}
        />
      </div>
    </div>
  );
}

/*
 * Radio button group.
 */
function RadioField({ name, label, value, onChange, options }) {
  return (
    <div className={styles.radioField}>
      <span>
        {label} <em>*</em>
      </span>

      <div className={styles.radioOptions}>
        {options.map(([optionValue, optionLabel]) => (
          <label key={optionValue}>
            <input
              type="radio"
              name={name}
              value={optionValue}
              checked={value === optionValue}
              onChange={onChange}
              required
            />

            <span className={styles.radioCircle} />

            {optionLabel}
          </label>
        ))}
      </div>
    </div>
  );
}
