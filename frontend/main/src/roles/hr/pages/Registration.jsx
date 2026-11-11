import React, { useState } from "react";
import { useTranslation } from "react-i18next";
import PageHeader from "../../../components/ui/PageHeader";
import Button from "../../../components/ui/Button";
import { Unlock, School, Plus } from "lucide-react";
import form from "../../../components/ui/Form.module.css";

import Card from "../../../components/ui/Card";
import styles from "./Registration.module.css";
import Modal from "../../../components/ui/Modal";
import { Field, Input } from "../../../components/ui/Field";
import useRegistration from "../../../hooks/HrHooks/useRegistration";
import { toast } from "react-toastify";
import Loading from "../../../components/ui/Loading";
 
const Registration = () => {
  const [showAdd, setShowAdd] = useState(false);

  const { programs , createPrograms ,loading,loadingCreate} = useRegistration();
  // console.log("programs", programs);
  const { t } = useTranslation("hr");

  const handleSubmit =async (e) => {
    e.preventDefault();

    const data = new FormData(e.currentTarget);
    try{
      const programData = await createPrograms({
      name: data.get("name"),
      startDate: data.get("startDate"),
      endDate: data.get("endDate"),
      startDateRegistration: data.get("startDateRegistration"),
      endDateRegistration: data.get("endDateRegistration"),
    });

    toast.success(programData.message || "Program created successfully");
    setShowAdd(false)
    }catch(error){
      const message =
              error.response?.data?.message || error.message || "invalid credentials";
            toast.error(message);
    }
    
    
  };

  return loading ? (<Loading/>): (
    <>
      <PageHeader
        title={t("registration.title")}
        action={
          <Button icon={Plus} onClick={() => setShowAdd(true)}>
            {t("registration.add")}
          </Button>
        }
      />
      <div  className={styles.grid}>
        {programs.map((program) => (
          <Card key={program.id} className={styles.card}>
            <div className={styles.flex}>
              <span className={styles.icon}>
                <School color="#e60000" size={22} />
              </span>
              <h3> {program.name} </h3>
            </div>

            <div className={styles.flex}>
              <span className={styles.bold}>
                {t("registration.startDate")}:
              </span>
              <p className={styles.value}> {program.start_date} </p>
            </div>

            <div className={styles.flex}>
              <span className={styles.bold}>{t("registration.endDate")}:</span>
              <p className={styles.value}> {program.end_date} </p>
            </div>

            <div className={styles.flex}>
              <span className={styles.bold}>
                {t("registration.startDateRegistration")}:
              </span>
              <p className={styles.value}>{program.application_start_date} </p>
            </div>

            <div className={styles.flex}>
              <span className={styles.bold}>
                {t("registration.endDateRegistration")}:
              </span>
              <p className={styles.value}>{program.application_end_date} </p>
            </div>

            {/* <hr /> */}

            {/* <div className={styles.flex}>
              <Button
                variant="success"
                size="sm"
                className={styles.button}
                icon={Unlock}
                onClick={() => " "}
              >
                {t("registration.openRegistration")}
              </Button>

              <Button
                size="sm"
                className={styles.button}
                icon={LockIcon}
                onClick={() => " "}
              >
                {t("registration.closeRegistration")}
              </Button>
            </div> */}
          </Card>
        ))}
      </div>

      {showAdd && (
        <Modal title={t("registration.add")} onClose={() => setShowAdd(false)}>
          <form className={form.form} onSubmit={handleSubmit}>
            <Field label={t("registration.name")}>
              <Input name="name" required />
            </Field>
            <Field label={t("registration.startDate")}>
              <Input type="Date" name="startDate" required />
            </Field>
            <Field label={t("registration.endDate")}>
              <Input type="Date" name="endDate" required />
            </Field>

            <Field label={t("registration.startDateRegistration")}>
              <Input type="Date" name="startDateRegistration" required />
            </Field>

            <Field label={t("registration.endDateRegistration")}>
              <Input type="Date" name="endDateRegistration" required />
            </Field>

            <Button loading={loadingCreate}  type="submit" >
              {t("action.create", { ns: "common" })}
            </Button>
          </form>
        </Modal>
      )}
    </>
  );
};

export default Registration;
