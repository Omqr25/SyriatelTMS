import { useCallback, useEffect, useState } from "react";
import { useTranslation } from "react-i18next";
import {
  confirmEnglishScores,
  downloadEnglishScoreTemplate,
  downloadEnglishScoreExceptions,
  ENGLISH_SCORES_CHANGED,
  getEnglishScoreHistory,
  getEnglishScoreRows,
  previewEnglishScores,
} from "../../../Api/HrApi/englishScoreImportsApi";
import { getProgramsApi } from "../../../Api/HrApi/registrationApi";
import Badge from "../../../components/ui/Badge";
import Button from "../../../components/ui/Button";
import Card from "../../../components/ui/Card";
import { Field, Input, Select } from "../../../components/ui/Field";
import Pagination from "../../../components/ui/Pagination";
import Table from "../../../components/ui/Table";
import styles from "./EnglishScoreImportsPage.module.css";

const ROW_TONES = {
  ready: "blue", replacement: "amber", applied: "green",
  unchanged: "neutral", duplicate: "neutral", replacement_skipped: "amber",
};

function useImportPageData(fetchData, page, refreshKey) {
  const [result, setResult] = useState({ data: [], totalPages: 0, loading: true });
  useEffect(() => {
    const request = new AbortController();
    setResult({ data: [], totalPages: 0, loading: true });
    fetchData(page, request.signal)
      .then(({ data, meta }) => {
        if (!request.signal.aborted) setResult({ data, totalPages: meta.total_pages });
      })
      .catch(() => {
        if (!request.signal.aborted) setResult({ data: [], totalPages: 0, error: true });
      });
    return () => request.abort();
  }, [fetchData, page, refreshKey]);

  return result;
}

function BatchSummary({ summary }) {
  const { t } = useTranslation("englishScores");
  return (
    <>
      <div className={styles.metrics}>
        <div>
          <strong>{t("matched", { matched: summary.matched_rows, total: summary.total_rows })}</strong>
          {summary.fully_matched && <Badge tone="green">{t("allMatched")}</Badge>}
        </div>
        <div><strong>{t("withoutScores", { remaining: summary.applicants_without_scores, total: summary.applicant_count })}</strong></div>
        <div><strong>{t("exceptionsCount", { count: summary.exception_rows })}</strong></div>
      </div>
      <div className={styles.chips}>
        {Object.entries(summary.counts).map(([status, count]) => (
          <Badge key={status} tone={ROW_TONES[status] ?? "red"}>
            {t(`rowStatus.${status}`)} · {count}
          </Badge>
        ))}
      </div>
    </>
  );
}

function BatchRows({ batch }) {
  const { t, i18n } = useTranslation("englishScores");
  const [downloading, setDownloading] = useState(false);
  const [downloadFailed, setDownloadFailed] = useState(false);
  const [page, setPage] = useState(1);
  const [exceptions, setExceptions] = useState(false);
  const [retry, setRetry] = useState(0);
  const loadRows = useCallback(
    (rowPage, signal) => getEnglishScoreRows(batch.id, rowPage, exceptions, signal),
    [batch.id, exceptions],
  );
  const result = useImportPageData(loadRows, page, retry);

  const download = async () => {
    setDownloading(true);
    setDownloadFailed(false);
    try {
      await downloadEnglishScoreExceptions(batch.id, i18n.language);
    } catch {
      setDownloadFailed(true);
    } finally {
      setDownloading(false);
    }
  };

  const columns = [
    { key: "row_number", header: t("row") },
    { key: "email", header: t("email"), render: (row) => <bdi className={styles.email}>{row.email || "—"}</bdi> },
    { key: "previous_score", header: t("previousScore"), render: (row) => <bdi>{row.previous_score ?? "—"}</bdi> },
    { key: "score", header: t("workbookScore"), render: (row) => <bdi>{row.score ?? (row.score_input || "—")}</bdi> },
    {
      key: "status", header: t("result"),
      render: (row) => <Badge tone={ROW_TONES[row.status] ?? "red"}>{t(`rowStatus.${row.reason ?? row.status}`)}</Badge>,
    },
  ];

  return (
    <>
      <div className={styles.heading}>
        <label className={styles.checkbox}>
          <input type="checkbox" checked={exceptions} onChange={(event) => {
            setExceptions(event.target.checked);
            setPage(1);
          }} />
          {t("onlyExceptions")}
        </label>
        {batch.summary.exception_rows > 0 && (
          <Button size="sm" variant="secondary" loading={downloading} onClick={download}>{t("downloadExceptions")}</Button>
        )}
      </div>
      {downloadFailed && <p role="alert" className={styles.error}>{t("downloadFailed")}</p>}
      {result.error ? (
        <div role="alert" className={styles.heading}>
          {t("loadError")}
          <Button size="sm" variant="secondary" onClick={() => setRetry((value) => value + 1)}>{t("retry")}</Button>
        </div>
      ) : (
        <Table columns={columns} rows={result.data} rowKey="row_number" emptyText={t(result.loading ? "loading" : "noRows")} />
      )}
      <Pagination currentPage={page} totalPages={result.totalPages} onPageChange={setPage} />
    </>
  );
}

function ImportHistory({ onSelect, refreshKey }) {
  const { t, i18n } = useTranslation("englishScores");
  const [page, setPage] = useState(1);
  const [retry, setRetry] = useState(0);
  const history = useImportPageData(getEnglishScoreHistory, page, refreshKey + retry);
  const date = new Intl.DateTimeFormat(i18n.language, { dateStyle: "medium", timeStyle: "short" });
  const columns = [
    { key: "program_name", header: t("program") },
    { key: "file_name", header: t("workbook"), render: (batch) => <bdi>{batch.file_name}</bdi> },
    { key: "uploader_name", header: t("uploader") },
    { key: "confirmed_at", header: t("confirmed"), render: (batch) => date.format(new Date(batch.confirmed_at)) },
    { key: "applied", header: t("applied"), render: (batch) => batch.summary.counts.applied ?? 0 },
    {
      key: "actions", header: t("result"),
      render: (batch) => <Button size="sm" variant="secondary" onClick={() => onSelect(batch)}>{t("viewReport")}</Button>,
    },
  ];
  return (
    <Card className={styles.section}>
      <h2>{t("history")}</h2>
      {history.error ? (
        <div role="alert" className={styles.heading}>
          {t("loadError")}
          <Button size="sm" variant="secondary" onClick={() => setRetry((value) => value + 1)}>{t("retry")}</Button>
        </div>
      ) : (
        <Table columns={columns} rows={history.data} emptyText={t(history.loading ? "loading" : "noHistory")} />
      )}
      <Pagination currentPage={page} totalPages={history.totalPages} onPageChange={setPage} />
    </Card>
  );
}

export default function EnglishScoreImportsPage() {
  const { t, i18n } = useTranslation("englishScores");
  const [programs, setPrograms] = useState([]);
  const [programId, setProgramId] = useState("");
  const [file, setFile] = useState(null);
  const [preview, setPreview] = useState(null);
  const [report, setReport] = useState(null);
  const [replaceExisting, setReplaceExisting] = useState(false);
  const [busy, setBusy] = useState("");
  const [error, setError] = useState("");
  const [programError, setProgramError] = useState(false);
  const [programRetry, setProgramRetry] = useState(0);
  const [historyRevision, setHistoryRevision] = useState(0);

  useEffect(() => {
    const request = new AbortController();
    getProgramsApi(request.signal).then(({ programs: options }) => {
      if (!request.signal.aborted) {
        setPrograms(options.filter((program) => program.has_applicants));
        setProgramError(false);
      }
    }).catch(() => {
      if (!request.signal.aborted) setProgramError(true);
    });
    return () => request.abort();
  }, [programRetry]);

  const run = async (step, action) => {
    setBusy(step);
    setError("");
    try {
      await action();
    } catch (failure) {
      const response = failure.response?.data;
      setError(
        response?.errors?.file?.[0]
        || response?.errors?.training_program_id?.[0]
        || t(failure.response?.status === 413 ? "fileTooLarge" : "requestError"),
      );
    } finally {
      setBusy("");
    }
  };

  const createPreview = (event) => {
    event.preventDefault();
    setPreview(null);
    setReplaceExisting(false);
    run("preview", async () => {
      setPreview(await previewEnglishScores(programId, file, i18n.language));
    });
  };

  const confirm = () => run("confirm", async () => {
    const confirmed = await confirmEnglishScores(preview.id, replaceExisting, i18n.language);
    setPreview(null);
    setReport(confirmed);
    setHistoryRevision((value) => value + 1);
    if (confirmed.summary.counts.applied) {
      try {
        localStorage.setItem(ENGLISH_SCORES_CHANGED, String(confirmed.id));
      } catch {
        // The list still loads current scores when visited.
      }
    }
  });

  return (
    <div className={styles.page}>
      <Card className={styles.section}>
        <div className={styles.heading}>
          <h2>{t("newImport")}</h2>
          <Button size="sm" variant="secondary" loading={busy === "template"} disabled={!!busy}
            onClick={() => run("template", downloadEnglishScoreTemplate)}>
            {t("downloadTemplate")}
          </Button>
        </div>
        <p className={styles.description}>{t("instructions")}</p>
        {programError && (
          <div role="alert" className={styles.heading}>
            {t("loadError")}
            <Button size="sm" variant="secondary" onClick={() => setProgramRetry((value) => value + 1)}>{t("retry")}</Button>
          </div>
        )}
        <form className={styles.fields} onSubmit={createPreview}>
          <Field label={t("program")}>
            <Select value={programId} required disabled={!!busy} onChange={(event) => {
              setProgramId(event.target.value);
              setPreview(null);
            }}>
              <option value="">{t("selectProgram")}</option>
              {programs.map((program) => <option value={program.id} key={program.id}>{program.name}</option>)}
            </Select>
          </Field>
          <Field label={t("workbook")}>
            <Input type="file" accept=".xlsx" required disabled={!!busy} onChange={(event) => {
              setFile(event.target.files[0] ?? null);
              setPreview(null);
            }} />
          </Field>
          <Button type="submit" loading={busy === "preview"} disabled={!programId || !file || !!busy}>{t("preview")}</Button>
        </form>
        {error && <p role="alert" className={styles.error}>{error}</p>}
      </Card>

      {preview && (
        <Card className={styles.section}>
          <h2>{t("previewFor", { program: preview.program_name })}</h2>
          <BatchSummary summary={preview.summary} />
          <BatchRows key={preview.id} batch={preview} />
          <div className={styles.heading}>
            {preview.summary.counts.replacement > 0 && (
              <label className={styles.checkbox}>
                <input type="checkbox" checked={replaceExisting} disabled={!!busy} onChange={(event) => setReplaceExisting(event.target.checked)} />
                {t("replaceExisting")}
              </label>
            )}
            <Button onClick={confirm} loading={busy === "confirm"} disabled={!!busy}>{t("confirm")}</Button>
          </div>
        </Card>
      )}

      {report && (
        <Card className={styles.section}>
          <div className={styles.heading}>
            <h2>{t("reportFor", { program: report.program_name })}</h2>
            <Button size="sm" variant="secondary" onClick={() => setReport(null)}>{t("closeReport")}</Button>
          </div>
          <p className={styles.description}><bdi>{report.file_name}</bdi> · {report.uploader_name}</p>
          <BatchSummary summary={report.summary} />
          <BatchRows key={report.id} batch={report} />
        </Card>
      )}

      <ImportHistory onSelect={setReport} refreshKey={historyRevision} />
    </div>
  );
}
