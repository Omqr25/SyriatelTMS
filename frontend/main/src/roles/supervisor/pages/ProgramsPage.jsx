import { useState } from "react";
import { Link, useParams, useSearchParams } from "react-router-dom";
import { ArrowRight, CalendarDays, Layers3, Plus, SquarePen, Trash2 } from "lucide-react";
import { useTranslation } from "react-i18next";
import { toast } from "react-toastify";
import Button from "../../../components/ui/Button";
import { Field, Select } from "../../../components/ui/Field";
import Loading from "../../../components/ui/Loading";
import Modal from "../../../components/ui/Modal";
import CreateProgramModal from "../components/createProgramModel";
import {
  deleteProgramApi,
  getMyProgramApi,
  getProgramApi,
} from "../../../Api/SupervisorApi/supervisorApi";
import useProgramQuery from "./useProgramQuery";
import styles from "./ProgramsPage.module.css";

const listPrograms = (_, config) => getMyProgramApi(config);

export default function ProgramsPage() {
  const { t, i18n } = useTranslation("milestoneWorkspace");
  const { programId } = useParams();
  const [params, setParams] = useSearchParams();
  const [dialog, setDialog] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState("");
  const programs = useProgramQuery(listPrograms, "owned");
  const list = programs.data ?? [];
  const requested = params.get("program") || programId;
  const selected = list.find((item) => String(item.id) === requested) ?? list[0];
  const details = useProgramQuery(getProgramApi, selected?.id ?? null);
  const program = details.data;
  const i18nDate = program?.deadline
    ? new Intl.DateTimeFormat(i18n.language, { dateStyle: "medium", timeZone: "UTC" })
      .format(new Date(program.deadline.slice(0, 10) + "T00:00:00Z"))
    : null;

  async function deleteProgram() {
    setDeleting(true);
    setDeleteError("");
    try {
      const result = await deleteProgramApi(program.id);
      toast.success(result.message);
      setDialog(null);
      programs.reload();
      details.reload();
    } catch (error) {
      setDeleteError(error.response?.data?.message || t("loadError"));
    } finally {
      setDeleting(false);
    }
  }

  return (
    <div className={styles.page}>
      <header className={styles.pageHeading}>
        <h1>{t("programs")}</h1>
        {!programs.loading && !programs.error && (
          <Button icon={Plus} onClick={() => setDialog("create")}>{t("hr:program.add")}</Button>
        )}
      </header>

      {programs.error || details.error ? (
        <div className={styles.empty} role="alert">
          <p>{t("loadError")}</p>
          <Button onClick={programs.error ? programs.reload : details.reload}>{t("taskWorkspace:retry")}</Button>
        </div>
      ) : programs.loading || details.loading ? <Loading /> : !program ? (
        <div className={styles.empty}><Layers3 aria-hidden="true" /><p>{t("noPrograms")}</p></div>
      ) : (
        <>
          {list.length > 1 && (
            <div className={styles.selector}>
              <Field label={t("chooseProgram")}>
                <Select value={selected.id} onChange={event => setParams({ program: event.target.value })}>
                  {list.map(item => <option key={item.id} value={item.id}>{item.title}</option>)}
                </Select>
              </Field>
            </div>
          )}
          <section className={styles.hero}>
            <span className={styles.heroIcon}><Layers3 size={26} aria-hidden="true" /></span>
            <div className={styles.heroBody}>
              <h2>{program.title}</h2>
              {program.description && <p>{program.description}</p>}
              {i18nDate && <span className={styles.meta}><CalendarDays size={16} aria-hidden="true" />{t("deadline", { date: i18nDate })}</span>}
            </div>
            <div className={styles.actions}>
              <Button variant="secondary" size="sm" icon={SquarePen} onClick={() => setDialog("edit")}>{t("editProgram")}</Button>
              <Button variant="soft" size="sm" icon={Trash2} onClick={() => { setDeleteError(""); setDialog("delete"); }}>{t("deleteProgram")}</Button>
            </div>
          </section>

          <section aria-labelledby="milestone-list-title">
            <h2 id="milestone-list-title" className={styles.sectionTitle}>{t("milestones")}</h2>
            {program.phases?.length ? (
              <ul className={styles.milestoneGrid}>
                {program.phases.map(phase => {
                  const tasks = phase.tasks ?? [];
                  const assigned = tasks.filter(task => task.trainee);
                  const completed = assigned.filter(task => task.status === "completed").length;
                  return (
                    <li key={phase.id}>
                      <Link className={styles.milestoneCard} to={"/supervisor/programs/" + program.id + "/milestones/" + phase.id}>
                        <span className={styles.tileIcon}><Layers3 size={20} aria-hidden="true" /></span>
                        <div>
                          <h3>{phase.name}</h3>
                          <p>{assigned.length ? t("progressText", { completed, total: assigned.length }) : t("noAssignments")}</p>
                        </div>
                        <ArrowRight className={styles.directional} size={18} aria-hidden="true" />
                      </Link>
                    </li>
                  );
                })}
              </ul>
            ) : <div className={styles.empty}><p>{t("noMilestones")}</p></div>}
          </section>
        </>
      )}

      {dialog === "create" && (
        <CreateProgramModal onClose={() => setDialog(null)} onSuccess={(created) => {
          if (created?.id) setParams({ program: created.id });
          programs.reload();
        }} />
      )}
      {dialog === "edit" && program && (
        <CreateProgramModal program2={program} onClose={() => setDialog(null)} onSuccess={() => { programs.reload(); details.reload(); }} />
      )}
      {dialog === "delete" && program && (
        <Modal title={t("deleteProgram")} onClose={() => { if (!deleting) setDialog(null); }}>
          <p>{t("confirmDeleteProgram", { name: program.title })}</p>
          {deleteError && <p className={styles.error} role="alert">{deleteError}</p>}
          <div className={styles.dialogActions}>
            <Button variant="secondary" disabled={deleting} onClick={() => setDialog(null)}>{t("common:action.cancel")}</Button>
            <Button loading={deleting} onClick={deleteProgram}>{t("deleteProgram")}</Button>
          </div>
        </Modal>
      )}
    </div>
  );
}
