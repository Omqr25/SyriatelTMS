import { ArrowDown, ArrowUp, ArrowUpDown, Check, Eye, Pencil, Trash2, X } from "lucide-react";
import { useTranslation } from "react-i18next";
import Badge from "../../components/ui/Badge";
import Button from "../../components/ui/Button";
import Loading from "../../components/ui/Loading";
import Pagination from "../../components/ui/Pagination";
import Table from "../../components/ui/Table";
import styles from "./AttendancePage.module.css";
import applicantStyles from "../../roles/hr/pages/ApplicantsPage.module.css";

const countKeys = ["total_absences", "excused_absences", "unexcused_absences"];

const tones = {
  excused: "green", unexcused: "red",
  pending: "amber", accepted: "green", rejected: "red", cancelled: "neutral", expired: "neutral",
};

export function AbsenceCounts({ counts }) {
  const { t } = useTranslation("attendance");
  return (
    <dl className={styles.counts}>
      {countKeys.map((key) => (
        <div key={key}>
          <dt>{t(`field.${key}`)}</dt>
          <dd>{counts[key]}</dd>
        </div>
      ))}
    </dl>
  );
}

export function AttendanceError({ reload }) {
  const { t } = useTranslation("attendance");
  return (
    <div className={styles.error} role="alert">
      <span>{t("loadError")}</span>
      <Button variant="secondary" size="sm" onClick={reload}>{t("retry")}</Button>
    </div>
  );
}

export default function AttendanceTable({
  kind, role, data, onPage, sort, onSort, openRecord, act, busy, inRecord = false,
}) {
  const { t, i18n } = useTranslation("attendance");
  const defaultSort = { absence: ["date", "desc"], leave: ["created_at", "desc"], records: ["name", "asc"] }[kind];
  const activeSort = sort?.key || defaultSort[0];
  const direction = sort?.direction || defaultSort[1];

  const column = (key, sortKey = key, render) => {
    const active = activeSort === sortKey;
    const label = t(`field.${key}`);
    if (!onSort || !sortKey) return { key, header: label, render };

    const Icon = active ? (direction === "asc" ? ArrowUp : ArrowDown) : ArrowUpDown;

    return {
      key,
      header: (
        <button className={applicantStyles.sortHeader} type="button" onClick={() =>
          onSort(sortKey, active && direction === "asc" ? "desc" : "asc")
        }>
          {label}
          <Icon size={13} aria-hidden="true" className={active ? applicantStyles.activeSort : undefined} />
        </button>
      ),
      ariaSort: active ? (direction === "asc" ? "ascending" : "descending") : undefined,
      render,
    };
  };

  const status = (value) => <Badge tone={tones[value]}>{t(`status.${value}`)}</Badge>;
  const recordButton = (id) => (
    <Button variant="secondary" size="sm" icon={Eye} onClick={() => openRecord(id)}>
      {t("action.viewRecord")}
    </Button>
  );
  const action = (name, icon, row, variant = "secondary") => (
    <Button variant={variant} size="sm" icon={icon} disabled={busy} onClick={() => act(name, row)}>
      {t(`action.${name}`)}
    </Button>
  );
  const actions = (row, id) => (
    <div className={applicantStyles.actions}>
      {openRecord && recordButton(id)}
      {kind === "absence" && role === "SUPERVISOR" && row.can_manage && (
        <>
          {action("editAbsence", Pencil, row)}
          {action("deleteAbsence", Trash2, row)}
        </>
      )}
      {kind === "leave" && role === "TRAINEE" && row.can_edit && action("editLeave", Pencil, row)}
      {kind === "leave" && role === "TRAINEE" && row.can_cancel && action("cancelLeave", X, row)}
      {kind === "leave" && role === "SUPERVISOR" && row.can_decide && (
        <>
          {action("approve", Check, row, "success")}
          {action("reject", X, row, "primary")}
        </>
      )}
      {kind === "leave" && role === "SUPERVISOR" && row.status === "accepted" && row.can_cancel && action("cancelApproved", X, row)}
    </div>
  );

  const own = role === "TRAINEE";
  const person = (getName) => !own && !inRecord ? [column("name", "name", (row) => <strong>{getName(row)}</strong>)] : [];
  const actionsColumn = (getId) => openRecord || role === "SUPERVISOR" || (own && kind === "leave")
    ? [column("actions", null, (row) => actions(row, getId(row)))]
    : [];
  const submitted = new Intl.DateTimeFormat(i18n.language, { dateStyle: "short", timeStyle: "short", timeZone: "Asia/Damascus" });
  const columns = {
    absence: () => [
      ...person((row) => row.trainee_name),
      column("date"),
      column("classification", "is_excused", (row) => status(row.classification)),
      column("reason", null),
      ...actionsColumn((row) => row.trainee_id),
    ],
    leave: () => [
      ...person((row) => row.trainee.name),
      column("start_date"),
      column("end_date"),
      column("status", role === "HR" ? null : "status", (row) => status(row.status)),
      column("reason", null),
      column("submitted_at", "created_at", (row) => row.submitted_at ? submitted.format(new Date(row.submitted_at)) : "—"),
      ...actionsColumn((row) => row.trainee.id),
    ],
    records: () => [
      column("name", "name", (row) => <strong>{row.name}</strong>),
      ...countKeys.map((key) => column(key)),
      column("actions", null, (row) => recordButton(row.id)),
    ],
  };

  return (
    <section className={styles.results} aria-busy={data.pending}>
      {data.error && <AttendanceError reload={data.reload} />}
      {!data.result && data.pending && <Loading />}
      {data.result && (
        <>
          <Table columns={columns[kind]()} rows={data.result.data} emptyText={t(`empty.${kind}`)} />
          <Pagination
            currentPage={data.result.meta.current_page}
            totalPages={data.result.meta.last_page}
            onPageChange={onPage}
          />
        </>
      )}
    </section>
  );
}
