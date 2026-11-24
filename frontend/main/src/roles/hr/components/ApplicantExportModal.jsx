import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import { Download, FileArchive, FileSpreadsheet, RotateCw } from "lucide-react";
import Button from "../../../components/ui/Button";
import Badge from "../../../components/ui/Badge";
import { Field, Select } from "../../../components/ui/Field";
import Modal from "../../../components/ui/Modal";
import {
  changeApplicantExport, downloadApplicantArtifact, downloadExportManifest,
  listApplicantExports, requestApplicantExport,
} from "../../../Api/HrApi/applicantExportsApi";
import styles from "./ApplicantExportModal.module.css";

const tones = { ready: "green", queued: "blue", running: "blue", failed: "red", empty: "amber" };
const recoverable = ["failed", "cancelled"];

export default function ApplicantExportModal({ program, onClose }) {
  const { t, i18n } = useTranslation("hr", { keyPrefix: "applicantExports" });
  const [snapshots, setSnapshots] = useState(null);
  const [selection, setSelection] = useState("");
  const [revision, setRevision] = useState(0);
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState(false);
  const [loadError, setLoadError] = useState(false);
  const [confirmation, setConfirmation] = useState(null);
  const snapshot = snapshots?.find(({ id }) => id === selection) ?? snapshots?.[0];
  const active = snapshots?.find(({ operation }) => operation);
  const disabled = !snapshots || busy || !!active;
  const locale = i18n.resolvedLanguage || i18n.language;
  const number = (value) => new Intl.NumberFormat(locale).format(value);
  const date = (value) => new Intl.DateTimeFormat(locale, { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
  const size = (bytes) => t("size", { value: new Intl.NumberFormat(locale, { maximumFractionDigits: 1 }).format(bytes / 1024 ** 2) });
  const parts = snapshot?.tasks.filter(({ kind }) => kind === "zip") ?? [];
  const workbook = snapshot?.tasks.find(({ kind, state }) => kind === "excel" && state === "ready");
  const states = snapshot && [snapshot.capture_state, snapshot.excel_state, snapshot.documents_state];

  useEffect(() => {
    const request = new AbortController();
    let timer;
    async function refresh() {
      try {
        const data = await listApplicantExports(program.id, request.signal);
        if (request.signal.aborted) return;
        setSnapshots(data);
        setLoadError(false);
        if (data.some(({ operation }) => operation)) timer = window.setTimeout(refresh, 4000);
      } catch {
        if (request.signal.aborted) return;
        setLoadError(true);
        timer = window.setTimeout(refresh, 10000);
      }
    }
    refresh();
    return () => {
      request.abort();
      window.clearTimeout(timer);
    };
  }, [program.id, revision]);

  async function interact(callback) {
    if (busy) return;
    setBusy(true);
    setActionError(false);
    try {
      const id = await callback();
      if (id) setSelection(id);
    } catch {
      setActionError(true);
    } finally {
      setRevision((value) => value + 1);
      setBusy(false);
    }
  }

  const request = (kind, fresh = false) => interact(() => requestApplicantExport(program.id, {
    kind, fresh, snapshot_id: fresh ? null : snapshot?.id,
  }));
  const change = (action) => interact(async () => (await changeApplicantExport(program.id, snapshot.id, action))?.id);
  const badge = (state) => <Badge tone={tones[state]}>{t(`state.${state}`)}</Badge>;

  return (
    <Modal title={t("title", { program: program.name })} onClose={onClose}>
      <div className={styles.layout}>
        {loadError && <p role="alert">{t("loadError")}</p>}
        {actionError && <p role="alert">{t("actionError")}</p>}
        {!snapshots && !loadError && <p role="status">{t("loading")}</p>}
        {snapshots?.length > 0 && (
          <Field label={t("snapshot")}>
            <Select value={snapshot.id} onChange={(event) => { setSelection(event.target.value); setConfirmation(null); }}>
              {snapshots.map(({ id, created_at }) => <option key={id} value={id}>{date(created_at)} · {id}</option>)}
            </Select>
          </Field>
        )}
        <div className={styles.actions}>
          <Button size="sm" icon={FileSpreadsheet} disabled={disabled || (!!snapshot && (snapshot.capture_state !== "ready" || snapshot.excel_state !== "none"))} onClick={() => request("excel")}>
            {t("excel")}
          </Button>
          <Button size="sm" variant="secondary" icon={FileArchive} disabled={disabled || (!!snapshot && (snapshot.capture_state !== "ready" || snapshot.documents_state !== "none"))} onClick={() => request("documents")}>
            {t("documents")}
          </Button>
          <Button size="sm" variant="secondary" disabled={disabled} onClick={() => setConfirmation("fresh")}>{t("new")}</Button>
          <Button size="sm" variant="secondary" icon={RotateCw} disabled={busy} onClick={() => setRevision((value) => value + 1)}>{t("refresh")}</Button>
        </div>
        {active && <p role="status">{t(`operation.${active.operation}`)}</p>}
        {confirmation && (
          <div className={styles.summary}>
            <p>{t(confirmation === "fresh" ? "confirmNew" : "confirmCancel")}</p>
            <div className={styles.actions}>
              <Button size="sm" disabled={busy || (confirmation === "fresh" && !!active)}
                onClick={() => { setConfirmation(null); if (confirmation === "fresh") request("capture", true); else change("cancel"); }}>
                {t("confirm")}
              </Button>
              <Button size="sm" variant="secondary" onClick={() => setConfirmation(null)}>{t("back")}</Button>
            </div>
          </div>
        )}
        {snapshot && (
          <>
            <div className={styles.summary}>
              <div className={styles.line}><span>{t("applicants", { value: number(snapshot.applicant_count) })}</span><span>{t("expires", { value: date(snapshot.expires_at) })}</span></div>
              <div className={styles.line}><span>{t("snapshot")}</span>{badge(snapshot.capture_state)}</div>
              <div className={styles.line}><span>{t("excel")}</span>{badge(snapshot.excel_state)}</div>
              <div className={styles.line}><span>{t("documents")}</span>{badge(snapshot.documents_state)}</div>
              {snapshot.applicant_count === 0 && <p role="status">{t("noApplicants")}</p>}
              {snapshot.applicant_count > 0 && snapshot.documents_state === "empty" && <p role="status">{t("noDocuments")}</p>}
              {snapshot.document_warnings > 0 && <p className={styles.warning}>{t("documentWarnings", { value: number(snapshot.document_warnings) })}</p>}
            </div>
            {snapshot.tasks.filter(({ kind, state }) => kind !== "zip" && state === "running").map(({ id, progress }) => (
              <p key={id} role="status">{t("applicantProgress", { value: number(progress), total: number(snapshot.applicant_count) })}</p>
            ))}
            {snapshot.tasks.filter(({ kind, error }) => kind !== "zip" && error).map(({ id, error }) => (
              <p key={id} role="status">{t(`errors.${error}`, { defaultValue: t("processingError", { error }) })}</p>
            ))}
            <div className={styles.actions}>
              {workbook && <Button size="sm" icon={Download} onClick={() => downloadApplicantArtifact(snapshot.id, workbook.id)}>{t("downloadExcel")}</Button>}
              {snapshot.capture_state === "ready" && <Button size="sm" variant="secondary" onClick={() => interact(() => downloadExportManifest(program.id, snapshot.id))}>{t("manifest")}</Button>}
              {states.some((state) => recoverable.includes(state)) && <Button size="sm" variant="secondary" disabled={disabled} onClick={() => change("retry")}>{t("retry")}</Button>}
              {snapshot.operation && <Button size="sm" variant="soft" disabled={busy} onClick={() => setConfirmation("cancel")}>{t("cancel")}</Button>}
            </div>
            {parts.length > 0 && (
              <div className={styles.summary}>
                <strong>{t("parts", { ready: number(parts.filter(({ state }) => state === "ready").length), total: number(parts.length) })}</strong>
                <ul className={styles.parts}>
                  {parts.map((part) => (
                    <li key={part.id}>
                      <div className={styles.line}>
                        <span>{t("part", { value: number(part.part) })} {part.bytes != null && `· ${size(part.bytes)}`}</span>
                        {part.state === "ready"
                          ? <Button size="sm" variant="secondary" icon={Download} onClick={() => downloadApplicantArtifact(snapshot.id, part.id)}>{t("download")}</Button>
                          : badge(part.state)}
                      </div>
                      {part.oversized && <span className={styles.warning}>{t("oversized")}</span>}
                      {part.state === "running" && <span>{t("progress", { value: number(part.progress) })}</span>}
                      {part.error && <span>{t(`errors.${part.error}`, { defaultValue: t("processingError", { error: part.error }) })}</span>}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </>
        )}
      </div>
    </Modal>
  );
}
