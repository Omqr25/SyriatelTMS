import { useState } from "react";
import { useTranslation } from "react-i18next";
import {
  GraduationCap,
  Layers3,
  Plus,
  Trash2,
  CalendarDays,
  Save,
} from "lucide-react";

import Modal from "../../../components/ui/Modal";
import Button from "../../../components/ui/Button";
import { Field, Input, Textarea } from "../../../components/ui/Field";
import { createProgramApi ,updateProgramApi} from "../../../Api/SupervisorApi/supervisorApi";
import styles from "./CreateProgramModal.module.css";
import { toast } from "react-toastify";
import useAssigned from "../../../hooks/SupervisorHooks/useassigned";

const createPhase = () => ({
  id: `${Date.now()}-${Math.random()}`,
  name: "",
  description: "",
});
  
export default function CreateProgramModal({
  onClose,
  onSubmit,
  //   loading = false,
  program2,
  onSuccess,
}) {
  const { t } = useTranslation("hr");
  // console.log("program",program2)
  const [program, setProgram] = useState({
    name: program2?.title || "",
    description: program2?.description || "",
    endDate: program2?.deadline || "",
  });

  const [phases, setPhases] = useState(program2?.phases || [createPhase()]);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);


  // تحديث بيانات البرنامج
  const handleProgramChange = (e) => {
    const { name, value } = e.target;

    setProgram((prev) => ({
      ...prev,
      [name]: value,
    }));
  };

  // تحديث بيانات مرحلة محددة
  const handlePhaseChange = (id, field, value) => {
    setPhases((prev) =>
      prev.map((phase) =>
        phase.id === id ? { ...phase, [field]: value } : phase,
      ),
    );
  };

  // إضافة مرحلة جديدة
  const addPhase = () => {
    setPhases((prev) => [...prev, createPhase()]);
  };

  // حذف مرحلة
  const removePhase = (id) => {
    setPhases((prev) => {
      if (prev.length <= 1) return prev;

      return prev.filter((phase) => phase.id !== id);
    });
  };

  // إرسال البيانات إلى المكون الأب
  const handleSubmit = async (e, update = program2?true:false, id = program2?.id) => {
    e.preventDefault();
    setError("");
    setLoading(true);
    const payload = {
      ...program,
      phases: phases.map(({ name, description }) => ({
        name: name.trim(),
        description: description.trim(),
      })),
    };

    try {
      // console.log("payload:", payload);
      if (update) {
        const data = await updateProgramApi(id, payload);
        // console.log( "update",data)
        toast.success(data.message);
      } else {
        const data = await createProgramApi(payload);
        toast.success(data.message);
      }
       await onSuccess()
      onClose();
    } catch (err) {
      const message =
        err.response?.data?.message || err.message || t("program.saveError");
      toast.error(message);
      setError(Array.isArray(message) ? message.join("، ") : message);
    } finally {
      setLoading(false);

    }
  };

  return (
    <Modal title={t("program.createTitle")} onClose={onClose}>
      <form className={styles.form} onSubmit={handleSubmit} dir="auto">
        {/* بيانات البرنامج الأساسية */}
        <div className={styles.programFields}>
          <Field label={t("program.name")}>
            <Input
              name="name"
              value={program.name}
              onChange={handleProgramChange}
              placeholder={"SU-G4"}
              maxLength={100}
              required
            />
          </Field>

          <Field label={t("program.endDate")}>
            <div className={styles.dateField}>
              <Input
                type="date"
                name="endDate"
                value={program.endDate}
                onChange={handleProgramChange}
                min={new Date().toLocaleDateString("en-CA")}
                required
              />
            </div>
          </Field>

          <div className={styles.fullWidth}>
            <Field label={t("program.description")}>
              <Textarea
                name="description"
                value={program.description}
                onChange={handleProgramChange}
                placeholder={""}
                maxLength={1000}
                required
              />
            </Field>
          </div>
        </div>

        {/* المراحل التدريبية */}
        <section className={styles.phasesSection}>
          <div className={styles.phasesHeader}>
            <div className={styles.sectionTitle}>
              <div className={styles.sectionIcon}>
                <Layers3 size={21} />
              </div>

              <div>
                <h4>{t("program.phasesTitle")}</h4>
                <p>{t("program.phasesDescription")}</p>
              </div>
            </div>

            <Button type="button" size="sm" icon={Plus} onClick={addPhase}>
              {t("program.addPhase")}
            </Button>
          </div>

          <div className={styles.phasesList}>
            {phases.map((phase, index) => (
              <div className={styles.phaseCard} key={phase.id}>
                <div className={styles.phaseNumber}>{index + 1}</div>

                <div className={styles.phaseFields}>
                  <Field label={t("program.phaseName")}>
                    <Input
                      name={`phase-${phase.id}-name`}
                      value={phase.name}
                      onChange={(e) =>
                        handlePhaseChange(phase.id, "name", e.target.value)
                      }
                      placeholder={""}
                      maxLength={100}
                      required
                    />
                  </Field>

                  <Field label={t("program.phasesDescription")}>
                    <Textarea
                      name={`phase-${phase.id}-description`}
                      value={phase.description}
                      onChange={(e) =>
                        handlePhaseChange(
                          phase.id,
                          "description",
                          e.target.value,
                        )
                      }
                      maxLength={1000}
                      required
                    />
                  </Field>
                </div>

                <button
                  type="button"
                  className={styles.deleteButton}
                  onClick={() => removePhase(phase.id)}
                  disabled={phases.length === 1 || loading}
                  title={t("program.removePhase")}
                  aria-label={t("program.removePhase")}
                >
                  <Trash2 size={18} />
                </button>
              </div>
            ))}
          </div>
        </section>

        {/* رسائل الخطأ */}
        {error && (
          <p className={styles.error} role="alert">
            {error}
          </p>
        )}

        {/* أزرار النموذج */}
        <div className={styles.footer}>
          <Button
            type="button"
            variant="secondary"
            onClick={onClose}
            disabled={loading}
          >
            {t("action.cancel", { ns: "common" })}
          </Button>

          <Button
            type="submit"
            icon={loading ? undefined : Save}
            loading={loading}
          >
            {program2
              ? t("action.update", { ns: "common" })
              : t("action.save", { ns: "common" })}
          </Button>
        </div>
      </form>
    </Modal>
  );
}
