import { useCallback, useEffect, useState } from "react";
import { ArrowLeft, ArrowRight, Plus, RotateCcw } from "lucide-react";
import { useTranslation } from "react-i18next";
import { useSearchParams } from "react-router-dom";
import { toast } from "react-toastify";
import Button from "../../components/ui/Button";
import Card from "../../components/ui/Card";
import { Input, Select } from "../../components/ui/Field";
import Loading from "../../components/ui/Loading";
import PageHeader from "../../components/ui/PageHeader";
import AttendanceDialog from "./AttendanceDialog";
import AttendanceTable, { AbsenceCounts, AttendanceError } from "./AttendanceTables";
import { attendanceActions, attendanceLists } from "./attendanceApi";
import useAttendanceData from "./useAttendanceData";
import styles from "./AttendancePage.module.css";
import applicantStyles from "../../roles/hr/pages/ApplicantsPage.module.css";

const staffTabs = ["absence", "leave", "records"];
const traineeTabs = ["absence", "leave"];
const queries = ["search", "sort", "direction", "page", "program_id", "supervisor_id", "date", "classification", "status", "recorded"];
const confirmActions = ["recordAbsence", "editAbsence", "deleteAbsence", "requestLeave", "editLeave", "cancelLeave", "cancelApproved"];

function FilterSelect({ field, value, setFilter, label, options = [] }) {
  return (
    <Select aria-label={label} value={value || ""} onChange={(event) => setFilter(field, event.target.value)}>
      <option value="">{label}</option>
      {options.map(({ id, name }) => <option key={id} value={id}>{name}</option>)}
    </Select>
  );
}

function Filters({ role, kind, query, configure, options }) {
  const { t } = useTranslation(["attendance", "common"]);
  const [search, setSearch] = useState(query.get("search") ?? "");
  const appliedSearch = query.get("search") ?? "";
  const setFilter = (name, value) => configure({ [name]: value, page: "" });

  useEffect(() => setSearch(appliedSearch), [appliedSearch]);
  useEffect(() => {
    const value = search.trim();
    if (value === appliedSearch) return;
    const timer = setTimeout(() => configure({ search: value, page: "" }, true), 300);
    return () => clearTimeout(timer);
  }, [appliedSearch, configure, search]);

  const roleIsStaff = role !== "TRAINEE";
  const choice = (field, values, labels) => (
    <FilterSelect field={field} value={query.get(field)} label={t(`filter.${field}`)} setFilter={setFilter}
      options={values.map((id) => ({ id, name: t(`${labels}.${id}`) }))} />
  );
  const categories = (field, values) => (
    <FilterSelect field={field} value={query.get(field)} label={t(`filter.${field}`)} setFilter={setFilter}
      options={values} />
  );

  return (
    <Card className={applicantStyles.filters}>
      {roleIsStaff && <Input aria-label={t("search")} placeholder={t("search")} value={search}
        onChange={(event) => setSearch(event.target.value)} type="search" />}
      {roleIsStaff && categories("program_id", options?.programs)}
      {role === "HR" && categories("supervisor_id", options?.supervisors)}
      {kind !== "records" && <Input aria-label={t("filter.date")} type="date" value={query.get("date") || ""}
        onChange={(event) => setFilter("date", event.target.value)} />}
      {kind === "absence" && choice("classification", ["excused", "unexcused"], "status")}
      {kind === "leave" && role !== "HR" && choice("status", ["pending", "accepted", "rejected", "cancelled", "expired"], "status")}
      {kind === "records" && choice("recorded", ["with", "without", "unexcused"], "filter.recordedOption")}
      <Button variant="secondary" size="sm" icon={RotateCcw}
        onClick={() => {
          setSearch("");
          configure(Object.fromEntries(queries.map((key) => [key, ""])));
        }}>
        {t("action.reset", { ns: "common" })}
      </Button>
    </Card>
  );
}

function AttendanceList({ kind, role, query, configure, options, revision, openRecord, act, busy }) {
  const { t } = useTranslation("attendance");
  const listPath = attendanceLists[kind === "leave" ? role : kind];
  const params = new URLSearchParams();
  queries.forEach((key) => { if (query.has(key)) params.set(key, query.get(key)); });
  const data = useAttendanceData(listPath, params.toString(), revision);
  const counts = data.result?.extraData?.counts;
  const bounds = data.result?.extraData;

  useEffect(() => {
    const { current_page: current, last_page: last } = data.result?.meta ?? {};
    if (!data.pending && !data.error && current > last && current === Number(query.get("page"))) {
      configure({ page: last }, true);
    }
  }, [data.pending, data.error, data.result, configure, query]);

  return (
    <>
      {role === "TRAINEE" && kind === "absence" && counts && <Card><AbsenceCounts counts={counts} /></Card>}
      <div className={styles.toolbar}>
        <Filters role={role} kind={kind} query={query} configure={configure} options={options} />
        {role === "SUPERVISOR" && kind === "absence" && (
          <Button icon={Plus} onClick={() => act("recordAbsence")}>{t("action.recordAbsence")}</Button>
        )}
        {role === "TRAINEE" && kind === "leave" && (
          <Button icon={Plus} disabled={!bounds?.can_request} onClick={() => act("requestLeave", null, bounds)}>
            {t("action.requestLeave")}
          </Button>
        )}
      </div>
      <AttendanceTable kind={kind} role={role} data={data} openRecord={openRecord} busy={busy}
        act={(name, row) => act(name, row, bounds)}
        sort={{ key: query.get("sort"), direction: query.get("direction") }}
        onSort={(sort, direction) => configure({ sort, direction, page: "" })}
        onPage={(page) => configure({ page })} />
    </>
  );
}

function TraineeRecord({ id, role, back, act, busy, revision }) {
  const { t, i18n } = useTranslation(["attendance", "common"]);
  const info = useAttendanceData(`attendance/trainees/${id}`, "", revision);
  const [absencePage, setAbsencePage] = useState(1);
  const [leavePage, setLeavePage] = useState(1);
  const person = info.result?.data;
  const absence = useAttendanceData(person ? attendanceLists.absence : null, `trainee_id=${id}&page=${absencePage}`, revision);
  const leave = useAttendanceData(person ? attendanceLists[role] : null, `trainee_id=${id}&page=${leavePage}`, revision);

  useEffect(() => {
    const page = absence.result?.meta;
    if (page?.current_page > page?.last_page && page.current_page === absencePage) {
      setAbsencePage(page.last_page);
    }
  }, [absence.result, absencePage]);

  return (
    <>
      <PageHeader title={t("recordTitle")} action={
        <Button icon={i18n.dir() === "rtl" ? ArrowRight : ArrowLeft} variant="secondary" onClick={back}>
          {t("back")}
        </Button>
      } />
      {info.error && <AttendanceError reload={info.reload} />}
      {!person && info.pending && <Loading />}
      {person && (
        <>
          <Card>
            <h3>{person.name}</h3>
            <dl className={styles.identification}>
              {[["id", person.id], ["email", person.email], ["program_id", person.training_program],
                ["department", person.department], ["supervisor_id", person.supervisor]]
                .map(([key, value]) => <div key={key}><dt>{t(`field.${key}`)}</dt><dd>{value ?? "—"}</dd></div>)}
            </dl>
            <AbsenceCounts counts={person} />
          </Card>
          <h3 className={styles.heading}>{t("tabs.absence")}</h3>
          <AttendanceTable kind="absence" role={role} data={absence} act={act} busy={busy} onPage={setAbsencePage} inRecord />
          <h3 className={styles.heading}>{t(role === "HR" ? "tabs.approved" : "tabs.leave")}</h3>
          <AttendanceTable kind="leave" role={role} data={leave} act={act} busy={busy} onPage={setLeavePage} inRecord />
        </>
      )}
    </>
  );
}

function RoleTabs({ role, active, onChange }) {
  const { t, i18n } = useTranslation("attendance");
  const ids = role === "TRAINEE" ? traineeTabs : staffTabs;

  const label = (id) => {
    if (id === "leave" && role === "HR") return "approved";
    if (role === "TRAINEE") return id === "leave" ? "myLeave" : "myAbsences";
    return id;
  };

  const onKeyDown = (event) => {
    const nextIndex = ids.indexOf(active);
    let target;
    if (event.key === "Home") target = 0;
    if (event.key === "End") target = ids.length - 1;
    if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
      const backward = (event.key === "ArrowLeft") === (i18n.dir() === "ltr");
      target = (nextIndex + ids.length + (backward ? -1 : 1)) % ids.length;
    }
    if (target === undefined) return;
    event.preventDefault();
    event.currentTarget.querySelectorAll('[role="tab"]')[target].focus();
    onChange(ids[target]);
  };

  return (
    <div role="tablist" aria-label={t("title")} className={styles.tabs} onKeyDown={onKeyDown}>
      {ids.map((id) => (
        <Button key={id} id={`attendance-tab-${id}`} type="button" role="tab" aria-selected={active === id}
          aria-controls={active === id ? "attendance-panel" : undefined} tabIndex={active === id ? 0 : -1}
          variant={active === id ? "soft" : "secondary"} onClick={() => onChange(id)}>
          {t(`tabs.${label(id)}`)}
        </Button>
      ))}
    </div>
  );
}

export function AttendancePage({ role }) {
  const { t } = useTranslation("attendance");
  const [query, setQuery] = useSearchParams();
  const [revision, setRevision] = useState(0);
  const [dialog, setDialog] = useState(null);
  const [busy, setBusy] = useState(false);
  const ids = role === "TRAINEE" ? traineeTabs : staffTabs;
  const tab = ids.includes(query.get("tab")) ? query.get("tab") : "absence";
  const recordId = role !== "TRAINEE" && /^\d+$/.test(query.get("trainee") ?? "") ? query.get("trainee") : null;
  const options = useAttendanceData(role === "TRAINEE" || recordId ? null : "attendance/options");
  const configure = useCallback((values, replace = false) => {
    const next = new URLSearchParams(query);
    Object.entries(values).forEach(([key, value]) => {
      if (value === "") next.delete(key);
      else next.set(key, value);
    });
    if (next.toString() !== query.toString()) setQuery(next, { replace });
  }, [query, setQuery]);

  const submit = async (action, id, payload) => {
    setBusy(true);
    try {
      await attendanceActions[action](id, payload);
      toast.success(t("saved"));
      setDialog(null);
      setRevision((value) => value + 1);
    } catch (error) {
      toast.error(error.response?.data?.message || t("saveError"));
      setRevision((value) => value + 1);
    } finally {
      setBusy(false);
    }
  };
  const act = (action, row, bounds) => {
    if (confirmActions.includes(action)) setDialog({ action, row, bounds });
    else submit(action, row.id);
  };

  return (
    <div className={styles.page}>
      <PageHeader title={t("title")} />
      <RoleTabs role={role} active={tab} onChange={(next) => {
        if (tab !== next) setQuery({ tab: next });
        else if (recordId) configure({ trainee: "" });
      }} />
      <div id="attendance-panel" role="tabpanel" aria-labelledby={`attendance-tab-${tab}`} className={styles.panel}>
        {recordId ? (
          <TraineeRecord key={recordId} id={recordId} role={role} revision={revision} act={act} busy={busy}
            back={() => configure({ trainee: "" })} />
        ) : (
          <>
            {options.error && <AttendanceError reload={options.reload} />}
            <AttendanceList key={tab} kind={tab} role={role} revision={revision} query={query} configure={configure}
              options={options.result?.data} busy={busy} act={act}
              openRecord={role !== "TRAINEE" ? (id) => configure({ trainee: id }) : undefined} />
          </>
        )}
      </div>
      {dialog && <AttendanceDialog dialog={dialog} close={() => setDialog(null)} submit={submit} busy={busy} bounds={dialog.bounds} />}
    </div>
  );
}

export const HrAttendancePage = () => <AttendancePage role="HR" />;
export const SupervisorAttendancePage = () => <AttendancePage role="SUPERVISOR" />;
export const TraineeAttendancePage = () => <AttendancePage role="TRAINEE" />;
