import { useState } from "react";
import { Link, useLocation, useParams, useSearchParams } from "react-router-dom";
import { ArrowLeft, ArrowRight, ChevronDown, ClipboardList, Layers3, Plus, Search, UserRound, UsersRound } from "lucide-react";
import { useTranslation } from "react-i18next";
import Button from "../../../components/ui/Button";
import Loading from "../../../components/ui/Loading";
import { getProgramApi, getTaskCandidatesApi } from "../../../Api/SupervisorApi/supervisorApi";
import CreateTaskModal from "../components/CreateTaskModal";
import TaskCard from "../components/TaskCard";
import useProgramQuery from "./useProgramQuery";
import styles from "./ProgramsPage.module.css";

export default function MilestoneWorkspace() {
  const { programId, milestoneId } = useParams();
  return <Workspace key={programId + "/" + milestoneId} programId={programId} milestoneId={milestoneId} />;
}

function Workspace({ programId, milestoneId }) {
  const { t, i18n } = useTranslation("milestoneWorkspace");
  const [params, setParams] = useSearchParams();
  const { pathname, search } = useLocation();
  const program = useProgramQuery(getProgramApi, programId);
  const candidates = useProgramQuery(getTaskCandidatesApi, programId);
  const [selected, setSelected] = useState([]);
  const [recipients, setRecipients] = useState(null);
  const [announcement, setAnnouncement] = useState("");
  const [filter, setFilter] = useState("");
  const view = params.get("view") === "groups" ? "groups" : "trainees";
  const traineeId = view === "trainees" ? params.get("trainee") : null;
  const phase = program.data?.phases?.find(item => String(item.id) === milestoneId);
  const returnTo = pathname + search;
  const programsPath = "/supervisor/programs?program=" + encodeURIComponent(programId);

  const roster = new Map((candidates.data ?? []).map(trainee => [String(trainee.id), { ...trainee, eligible: true, tasks: [] }]));
  const groups = new Map();
  const drafts = [];
  for (const task of phase?.tasks ?? []) {
    if (!task.trainee) {
      drafts.push(task);
      continue;
    }
    const id = String(task.trainee.id);
    if (!roster.has(id)) roster.set(id, { ...task.trainee, eligible: false, tasks: [] });
    roster.get(id).tasks.push(task);
    if (task.assignment_group_id) {
      const group = groups.get(task.assignment_group_id) ?? [];
      group.push(task);
      groups.set(task.assignment_group_id, group);
    }
  }

  const people = [...roster.values()].sort((a, b) => Number(b.eligible) - Number(a.eligible) || a.name.localeCompare(b.name, i18n.language));
  const activePerson = roster.get(traineeId);
  const selectedPeople = people.filter(person => person.eligible && selected.includes(String(person.id)));
  const matching = people.filter(person => person.name.toLocaleLowerCase(i18n.language).includes(filter.trim().toLocaleLowerCase(i18n.language)));
  const batches = [...groups.values()].sort((a, b) => Math.max(...b.map(task => task.id)) - Math.max(...a.map(task => task.id)));

  function changeView(next) {
    setParams(next === "groups" ? { view: "groups" } : {});
  }

  function togglePerson(id) {
    setSelected(previous => previous.includes(id) ? previous.filter(item => item !== id) : [...previous, id]);
    setAnnouncement("");
  }

  function saved(result) {
    const created = Array.isArray(result?.data) ? result.data : [result?.data];
    if (recipients.length > 1) {
      const group = created[0]?.assignment_group_id;
      setParams(group ? { view: "groups", group } : { view: "groups" });
    }
    setAnnouncement(recipients.length ? t("assignmentSaved", { count: recipients.length }) : t("draftSaved"));
    setSelected(previous => previous.filter(id => !recipients.some(person => String(person.id) === id)));
    program.reload();
  }

  return (
    <div className={styles.page}>
      <nav className={styles.breadcrumbs} aria-label={t("breadcrumbLabel")}>
        <Link to={programsPath}><ArrowLeft size={16} className={styles.directional} aria-hidden="true" />{t("programs")}</Link>
        {program.data && <span>{program.data.title}</span>}
      </nav>

      {program.loading ? <Loading /> : program.error || !phase ? (
        <div className={styles.empty} role="alert">
          <p>{t(program.error ? "loadError" : "missingMilestone")}</p>
          {program.error && <Button onClick={program.reload}>{t("taskWorkspace:retry")}</Button>}
        </div>
      ) : (
        <>
          <header className={styles.hero}>
            <span className={styles.heroIcon}><Layers3 size={26} aria-hidden="true" /></span>
            <div className={styles.heroBody}><h1>{phase.name}</h1>{phase.description && <p>{phase.description}</p>}</div>
            <div className={styles.actions}>
              <Button variant="secondary" size="sm" icon={Plus} onClick={() => setRecipients([])}>{t("newDraft")}</Button>
            </div>
          </header>

          <nav className={styles.switcher} aria-label={t("viewLabel")}>
            <Link to={pathname} aria-current={view === "trainees" ? "page" : undefined}>
              <UserRound size={18} aria-hidden="true" />{t("trainees")}
            </Link>
            <Link to={pathname + "?view=groups"} aria-current={view === "groups" ? "page" : undefined}>
              <UsersRound size={18} aria-hidden="true" />{t("groups")}
              <span className={styles.count}>{batches.length}</span>
            </Link>
          </nav>

          {announcement && <p className={styles.success} role="status">{announcement}</p>}

          {view === "groups" ? (
            batches.length ? (
              <div className={styles.groupList}>
                {batches.map(tasks => {
                  const id = tasks[0].assignment_group_id;
                  return <AssignmentGroup key={id} tasks={tasks} returnTo={pathname + "?view=groups&group=" + encodeURIComponent(id)} initiallyOpen={id === params.get("group")} />;
                })}
              </div>
            ) : <div className={styles.empty}>
              <UsersRound size={26} aria-hidden="true" />
              <h2>{t("noGroups")}</h2>
              <p>{t("groupHint")}</p>
              <Button variant="secondary" onClick={() => changeView("trainees")}>{t("trainees")}</Button>
            </div>
          ) : traineeId ? (
            <section>
              <Link className={styles.backLink} to={pathname}><ArrowLeft size={16} className={styles.directional} aria-hidden="true" />{t("allTrainees")}</Link>
              {activePerson ? (
                <>
                  <div className={styles.sectionHeader}>
                    <div>
                      <h2>{activePerson.name}</h2>
                      <p>{t("taskCount", { count: activePerson.tasks.length })}</p>
                    </div>
                    {activePerson.eligible && <Button icon={Plus} onClick={() => setRecipients([activePerson])}>{t("assignTask")}</Button>}
                  </div>
                  <TaskList tasks={activePerson.tasks} returnTo={returnTo} emptyText={t("noTraineeTasks")} />
                </>
              ) : candidates.loading ? <Loading /> : <div className={styles.empty}><p>{t("missingTrainee")}</p></div>}
            </section>
          ) : (
            <section aria-label={t("trainees")}>
              {candidates.error && <div className={styles.inlineError} role="alert">
                <p>{t("candidateLoadError")}</p>
                <Button size="sm" variant="secondary" onClick={candidates.reload}>{t("taskWorkspace:retry")}</Button>
              </div>}
              <div className={styles.rosterHeading}>
                <div><h2>{t("trainees")}</h2><p>{t("selectHint")}</p></div>
                <label className={styles.search}>
                  <Search size={18} aria-hidden="true" />
                  <input type="search" aria-label={t("searchTrainees")} placeholder={t("searchTrainees")} value={filter} onChange={event => setFilter(event.target.value)} />
                </label>
              </div>

              {selectedPeople.length > 0 && <div className={styles.selection}>
                <div aria-live="polite"><strong>{t("selected", { count: selectedPeople.length })}</strong></div>
                <div className={styles.actions}>
                  <Button size="sm" variant="secondary" onClick={() => setSelected([])}>{t("clearSelection")}</Button>
                  <Button size="sm" icon={Plus} onClick={() => setRecipients(selectedPeople)}>{t("assignTask")}</Button>
                </div>
              </div>}

              {candidates.loading && !people.length ? <Loading /> : matching.length ? (
                <ul className={styles.roster}>
                  {matching.map(person => {
                    const id = String(person.id);
                    const checked = selected.includes(id);
                    const reviewCount = person.tasks.filter(task => task.status === "for review").length;
                    return (
                      <li key={id} className={styles.traineeRow} data-selected={checked}>
                        {person.eligible ? (
                          <label className={styles.checkbox}>
                            <input type="checkbox" checked={checked} disabled={!checked && selectedPeople.length >= 100}
                              aria-label={t("selectPerson", { name: person.name })} onChange={() => togglePerson(id)} />
                          </label>
                        ) : <span className={styles.checkbox} />}
                        <Link to={pathname + "?trainee=" + encodeURIComponent(id)} className={styles.personLink}>
                          <span className={styles.avatar}><UserRound size={19} aria-hidden="true" /></span>
                          <span className={styles.personInfo}>
                            <strong>{person.name}</strong>
                            <small>{t("taskCount", { count: person.tasks.length })}{!person.eligible && candidates.data && " · " + t("previousAssignee")}</small>
                          </span>
                          {reviewCount > 0 && <span className={styles.review}>{t("reviewCount", { count: reviewCount })}</span>}
                          <ArrowRight size={18} className={styles.directional} aria-hidden="true" />
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              ) : <div className={styles.empty}><p>{t(filter ? "noMatches" : "noTrainees")}</p></div>}
            </section>
          )}

          {drafts.length > 0 && (
            <details className={styles.disclosure}>
              <summary><ClipboardList size={19} aria-hidden="true" /><strong>{t("drafts", { count: drafts.length })}</strong><ChevronDown className={styles.chevron} size={18} aria-hidden="true" /></summary>
              <TaskList tasks={drafts} returnTo={returnTo} />
            </details>
          )}
        </>
      )}

      {recipients && phase && <CreateTaskModal phase={phase} programId={programId} recipients={recipients} onUpdated={saved} onClose={() => setRecipients(null)} />}
    </div>
  );
}

function AssignmentGroup({ tasks, returnTo, initiallyOpen }) {
  const { t } = useTranslation("milestoneWorkspace");
  const completed = tasks.filter(task => task.status === "completed").length;
  const name = tasks.reduce((first, task) => task.id < first.id ? task : first).name;
  return (
    <details className={styles.disclosure} open={initiallyOpen}>
      <summary>
        <span className={styles.tileIcon}><UsersRound size={20} aria-hidden="true" /></span>
        <div className={styles.groupHeading}>
          <strong>{name}</strong>
          <small>{t("progressText", { completed, total: tasks.length })}</small>
        </div>
        <ChevronDown className={styles.chevron} size={18} aria-hidden="true" />
      </summary>
      <ul className={styles.groupMembers}>
        {tasks.map(task => (
          <li key={task.id}>
            <Link className={styles.memberLink} to={"/supervisor/tasks/details/" + task.id} state={{ returnTo }}>
              <span className={styles.personInfo}>
                <strong>{task.trainee.name}</strong>
                {task.name !== name && <small>{task.name}</small>}
              </span>
              <span className={styles.memberStatus} data-status={task.status}>
                {t("taskWorkspace:statuses." + task.status)}
              </span>
              <ArrowRight className={styles.directional} size={16} aria-hidden="true" />
            </Link>
          </li>
        ))}
      </ul>
    </details>
  );
}

function TaskList({ tasks, returnTo, emptyText }) {
  if (!tasks.length) return <div className={styles.empty}><ClipboardList size={24} aria-hidden="true" /><p>{emptyText}</p></div>;
  return <div className={styles.taskGrid}>
    {[...tasks].sort((a, b) => b.id - a.id).map(task => (
      <TaskCard key={task.id} task={task} returnTo={returnTo} showAssignee={false} />
    ))}
  </div>;
}
