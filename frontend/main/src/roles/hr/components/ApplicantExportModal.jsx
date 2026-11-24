import { useEffect, useRef, useState } from "react";
import { useTranslation } from "react-i18next";
import { CircleAlert, Download, FileArchive, FileSpreadsheet, FileText, Plus, Users } from "lucide-react";
import Button from "../../../components/ui/Button";
import Badge from "../../../components/ui/Badge";
import { Field, Select } from "../../../components/ui/Field";
import Modal from "../../../components/ui/Modal";
import {
  changeApplicantExport, downloadApplicantArtifact, downloadExportReport,
  listApplicantExports, requestApplicantExport,
} from "../../../Api/HrApi/applicantExportsApi";
import styles from "./ApplicantExportModal.module.css";

const tones = { ready: "green", queued: "blue", running: "blue", failed: "red", empty: "amber" };
const processing = ["queued", "running"];
const recoverable = ["failed", "cancelled"];
const active = (item) => [item.capture_state, item.excel_state, item.documents_state].some((state) => processing.includes(state));

function freshRequestKey() {
  if (crypto.randomUUID) return crypto.randomUUID();
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  const hex = Array.from(bytes, (value) => value.toString(16).padStart(2, "0")).join("");
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

function historyState(item) {
  if (item.expired || new Date(item.expires_at).getTime() <= Date.now()) return "expired";
  if (active(item)) return "running";
  const states = [item.excel_state, item.documents_state, item.capture_state];
  if (states.includes("failed")) return "failed";
  return states.includes("cancelled") ? "cancelled" : "ready";
}

const taskKind = (task, kind) => kind === "excel" ? task.kind === "excel" : ["capture", "plan", "zip"].includes(task.kind);

function useExportStatus(program, selection) {
  const [status, setStatus] = useState(null);
  const [loadError, setLoadError] = useState(false);
  const refresh = useRef(() => {});
  useEffect(() => {
    const controller = new AbortController();
    let timer;
    let running = false;
    let pending = false;
    let failures = 0;

    async function load() {
      if (controller.signal.aborted) return;
      if (running) {
        pending = true;
        return;
      }
      clearTimeout(timer);
      running = true;
      let delay = 20000;
      try {
        const data = await listApplicantExports(program, controller.signal, selection ? { selected: selection } : undefined);
        if (controller.signal.aborted) return;
        setStatus(data);
        setLoadError(false);
        failures = 0;
        if (data.data.some((item) => !item.expired && active(item))) delay = 4000;
      } catch {
        if (controller.signal.aborted) return;
        setLoadError(true);
        delay = Math.min(30000, 5000 * 2 ** Math.min(failures++, 3));
      } finally {
        running = false;
        if (!controller.signal.aborted) {
          timer = setTimeout(load, pending ? 0 : delay);
          pending = false;
        }
      }
    }

    function visible() {
      if (!document.hidden) load();
    }
    refresh.current = load;
    load();
    window.addEventListener("focus", visible);
    document.addEventListener("visibilitychange", visible);
    return () => {
      controller.abort();
      clearTimeout(timer);
      window.removeEventListener("focus", visible);
      document.removeEventListener("visibilitychange", visible);
    };
  }, [program, selection]);

  return { status, loadError, refresh };
}

export default function ApplicantExportModal({ program, onClose }) {
  const { t, i18n } = useTranslation("hr", { keyPrefix: "applicantExports" });
  const [selection, setSelection] = useState("");
  const { status, loadError, refresh } = useExportStatus(program.id, selection);
  const [older, setOlder] = useState([]);
  const [olderCursor, setOlderCursor] = useState();
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState("");
  const [confirmation, setConfirmation] = useState(null);
  const busyRef = useRef(false);
  const freshKey = useRef(null);
  const historyRequest = useRef(null);
  useEffect(() => () => historyRequest.current?.abort(), []);

  const locale = i18n.resolvedLanguage || i18n.language;
  const number = (value) => new Intl.NumberFormat(locale).format(value);
  const date = (value) => new Intl.DateTimeFormat(locale, { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
  const size = (bytes) => t("size", { value: new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(bytes / 1024 ** 2) });
  const snapshots = status?.data ?? [];
  const choices = [...new Map([...older, ...snapshots].map((item) => [item.id, item])).values()]
    .sort((a, b) => b.id.localeCompare(a.id));
  const snapshot = selection ? choices.find(({ id }) => id === selection) : snapshots[0];
  const expired = snapshot && (snapshot.expired || new Date(snapshot.expires_at).getTime() <= Date.now());
  const tasks = snapshot?.tasks ?? [];
  const workbook = tasks.find(({ kind, state }) => kind === "excel" && state === "ready");
  const parts = tasks.filter(({ kind }) => kind === "zip");
  const ongoingDocuments = snapshots.some((item) => item.id !== snapshot?.id && !item.expired && item.documents_state === "queued");
  const cursor = olderCursor === undefined ? status?.next_before : olderCursor;
  const missing = snapshot?.document_warnings;

  async function interact(callback) {
    if (busyRef.current) return;
    busyRef.current = true;
    setBusy(true);
    setActionError("");
    try {
      const id = await callback();
      if (typeof id === "string" && id) setSelection(id);
    } catch (error) {
      const conflict = [409, 422].includes(error.response?.status) && error.response?.data?.message;
      setActionError(typeof conflict === "string" ? conflict : t("actionError"));
    } finally {
      refresh.current();
      busyRef.current = false;
      setBusy(false);
    }
  }

  function request(kind, fresh = false) {
    if (!fresh && selection && !snapshot) return;
    return interact(async () => {
      if (fresh) freshKey.current ??= freshRequestKey();
      const id = await requestApplicantExport(program.id, {
        kind, fresh, snapshot_id: fresh ? null : snapshot?.id,
        ...(fresh ? { request_key: freshKey.current } : {}),
      }, locale);
      if (fresh) freshKey.current = null;
      return id;
    });
  }

  function change(action, kind) {
    return interact(() => changeApplicantExport(program.id, snapshot.id, action, kind, locale));
  }

  function loadOlder() {
    return interact(async () => {
      const controller = new AbortController();
      historyRequest.current = controller;
      const data = await listApplicantExports(program.id, controller.signal, { before: cursor });
      if (!controller.signal.aborted) {
        setOlder((items) => [...items, ...data.data]);
        setOlderCursor(data.next_before);
      }
    });
  }

  function stateFor(item, kind) {
    if (!item) return "none";
    if (item.expired || new Date(item.expires_at).getTime() <= Date.now()) return "expired";
    if (item.tasks.some((task) => taskKind(task, kind) && task.state === "running")) return "running";
    const state = item[`${kind}_state`];
    return kind === "documents" && state === "none" && item.capture_state !== "ready" ? item.capture_state : state;
  }

  const badge = (state) => <Badge tone={tones[state]}>{t(`state.${state}`)}</Badge>;
  const excelState = stateFor(snapshot, "excel");
  const documentState = stateFor(snapshot, "documents");
  const selectionExpired = Boolean(selection && !snapshot && status);
  const downloadable = snapshot && !expired;
  const errors = tasks.filter((task) => task.error && task.state === "failed");
  const capacityError = errors.find((task) => task.capacity_required != null);
  const otherErrors = [...new Set(errors.filter((task) => task.capacity_required == null).map((task) => task.error))];

  return (
    <Modal title={t("title")} onClose={onClose}>
      <div className={styles.layout}>
        <p className={styles.program}>{program.name}</p>
        {loadError && <p role="alert" className={styles.warning}>{t("loadError")}</p>}
        {actionError && <p role="alert" className={styles.warning}>{actionError}</p>}
        {!status && !loadError && <p role="status">{t("loading")}</p>}
        {(choices.length > 0 || selectionExpired) && (
          <div className={styles.history}>
            <Field label={t("previous")}>
              <Select value={snapshot?.id ?? selection} onChange={(event) => { setSelection(event.target.value); setConfirmation(null); }}>
                {selectionExpired && <option value={selection}>{t("state.expired")}</option>}
                {choices.map((item) => <option key={item.id} value={item.id}>{t("historyOption", {
                  date: date(item.created_at), count: number(item.applicant_count),
                  status: t(`state.${historyState(item)}`),
                })}</option>)}
              </Select>
            </Field>
            {cursor && <Button size="sm" variant="secondary" onClick={loadOlder} disabled={busy}>{t("older")}</Button>}
          </div>
        )}

        <div className={styles.cards}>
          <div className={styles.summaryCard}><Users size={18} aria-hidden="true" /><span>{t("eligible")}</span><strong>{snapshot ? number(snapshot.applicant_count) : "—"}</strong></div>
          <div className={`${styles.summaryCard} ${missing ? styles.warning : ""}`}><FileArchive size={18} aria-hidden="true" /><span>{t("unavailable")}</span>
            <strong>{missing != null ? number(missing) : t(snapshot && recoverable.includes(snapshot.capture_state) ? "unavailableCount" : "pendingCount")}</strong></div>
        </div>

        <dl className={styles.statusRows} aria-live="polite">
          <div><dt>{t("dataStage")}</dt><dd>{badge(expired || selectionExpired ? "expired" : snapshot?.data_state ?? "none")}</dd></div>
          <div><dt>{t("excelStage")}</dt><dd>{badge(selectionExpired ? "expired" : excelState)}</dd></div>
          <div><dt>{t("documentsStage")}</dt><dd>{badge(selectionExpired ? "expired" : documentState)}
            {snapshot && ["queued", "running"].includes(snapshot.capture_state) && !expired && <small>{t("preparingDocuments")}</small>}</dd></div>
        </dl>
        {tasks.filter(({ state }) => state === "running").map((task) => <p key={task.id} className={styles.hint}>
          {t(task.kind === "zip" ? "progress" : "applicantProgress", {
            value: number(task.progress), total: number(snapshot.applicant_count),
          })}
        </p>)}
        {snapshot?.applicant_count === 0 && <p className={styles.hint}>{t("noApplicants")}</p>}
        {snapshot?.applicant_count > 0 && documentState === "empty" && <p className={styles.hint}>{t("noDocuments")}</p>}
        {missing > 0 && <p className={styles.warning}><CircleAlert size={17} aria-hidden="true" />{t("documentWarnings", { value: number(missing) })}</p>}
        {capacityError && <p role="status" className={styles.warning}>{t("capacity", {
          required: size(capacityError.capacity_required), available: size(capacityError.capacity_available),
        })}</p>}
        {otherErrors.map((error) => <p key={error} role="status" className={styles.warning}>
          {t(`errors.${error}`, { defaultValue: t("processingError") })}
        </p>)}
        {ongoingDocuments && snapshot?.documents_state === "none" && <p className={styles.hint}>{t("otherDocumentExport")}</p>}

        <div className={styles.actions}>
          {workbook && downloadable
            ? <Button size="sm" icon={Download} onClick={() => downloadApplicantArtifact(snapshot.id, workbook.id)}>{t("downloadExcel")}</Button>
            : <Button size="sm" icon={FileSpreadsheet} disabled={!status || busy || selectionExpired || (snapshot && excelState !== "none")} onClick={() => request("excel")}>{t("excel")}</Button>}
          <Button size="sm" variant="secondary" icon={FileArchive}
            disabled={!status || busy || expired || selectionExpired || ongoingDocuments || (snapshot && snapshot.documents_state !== "none")}
            onClick={() => request("documents")}>{t("documents")}</Button>
          {downloadable && ["excel", "documents"].map((kind) => {
            const action = recoverable.includes(snapshot[`${kind}_state`]) || (kind === "documents" && recoverable.includes(snapshot.capture_state))
              ? "retry"
              : snapshot[`${kind}_state`] === "queued" || (kind === "documents" && snapshot.capture_state === "queued") ? "cancel" : null;
            return action && <Button key={kind} size="sm" variant="secondary" disabled={busy || (kind === "documents" && ongoingDocuments && action === "retry")}
              onClick={() => action === "retry" ? change(action, kind) : setConfirmation(kind)}>{t(`${action}.${kind}`)}</Button>;
          })}
        </div>
        {parts.length > 0 && (
          <div className={styles.partGroup}>
            <strong>{t("parts", { ready: number(parts.filter(({ state }) => state === "ready").length), total: number(parts.length) })}</strong>
            <ul className={styles.parts}>
              {parts.map((part) => (
                <li className={styles.part} key={part.id}>
                  <span>{t("part", { value: number(part.part) })}{part.bytes != null && ` · ${size(part.bytes)}`}
                    {part.oversized && <small>{t("oversized")}</small>}</span>
                  {part.state === "ready" && downloadable
                    ? <Button size="sm" variant="secondary" icon={Download} onClick={() => downloadApplicantArtifact(snapshot.id, part.id)}>{t("download")}</Button>
                    : badge(expired ? "expired" : part.state)}
                </li>
              ))}
            </ul>
          </div>
        )}
        {confirmation && (
          <div className={styles.confirmation} role="group" aria-label={t("confirm")}>
            <p>{t(confirmation === "fresh" ? "confirmNew" : "confirmCancel")}</p>
            <div className={styles.actions}>
              <Button size="sm" autoFocus disabled={busy} onClick={() => {
                setConfirmation(null);
                if (confirmation === "fresh") request("capture", true);
                else change("cancel", confirmation);
              }}>{t("confirm")}</Button>
              <Button size="sm" variant="secondary" onClick={() => setConfirmation(null)}>{t("back")}</Button>
            </div>
          </div>
        )}
        <footer className={styles.footer}>
          <div className={styles.actions}>
            {downloadable && ["ready", "failed", "cancelled"].includes(snapshot.capture_state) &&
              <Button size="sm" variant="secondary" icon={FileText} disabled={busy} onClick={() => interact(() => downloadExportReport(program.id, snapshot.id, locale))}>{t("report")}</Button>}
            <Button size="sm" variant="secondary" icon={Plus} disabled={!status || busy} onClick={() => setConfirmation("fresh")}>{t("new")}</Button>
          </div>
          {snapshot && <p>{t("expires", { value: date(snapshot.expires_at) })}</p>}
          {status && <p>{t("retention", { hours: number(snapshot ? Math.round((new Date(snapshot.expires_at) - new Date(snapshot.created_at)) / 3600000) : status.retention_hours) })}</p>}
        </footer>
      </div>
    </Modal>
  );
}
