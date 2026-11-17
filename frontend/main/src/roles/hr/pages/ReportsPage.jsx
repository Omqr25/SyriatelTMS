import { useCallback, useEffect, useRef, useState } from "react";
import { useTranslation } from "react-i18next";
import {
  FileText,
  Download,
  Users,
  ClipboardList,
  Building2,
  UserCheck,
  LoaderCircle,
} from "lucide-react";
import { toast } from "react-toastify";


import { getReportApi } from "../../../Api/HrApi/hrApi";
import Button from "../../../components/ui/Button";
import Card from "../../../components/ui/Card";
import styles from "./ReportsPage.module.css";




const REPORT_FIELDS = [
  { key: "total_applications", group: "applications" },
  { key: "accepted_applications", group: "applications" },
  { key: "rejected_applications", group: "applications" },
  { key: "participating_trainees", group: "trainees" },
  { key: "completed_trainees", group: "trainees" },
  { key: "fired_trainees", group: "trainees" },
  { key: "total_departments", group: "organization" },
  { key: "active_supervisor_accounts", group: "organization" },
];

const GROUPS = [
  { key: "applications", icon: ClipboardList },
  { key: "trainees", icon: Users },
  { key: "organization", icon: Building2 },
];

const escapeHtml = (value) =>
  String(value ?? "").replace(/[&<>"']/g, (char) => {
    const entities = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    };

    return entities[char];
  });

function createReportHtml(report, t, language) {
  const isArabic = language.startsWith("ar");
  const direction = isArabic ? "rtl" : "ltr";

  const rows = REPORT_FIELDS.map(({ key }) => {
    const value = Number(report[key] ?? 0);

    return `
      <tr>
        <td>${escapeHtml(t(`reports.fields.${key}`))}</td>
        <td class="number" dir="ltr">
          ${escapeHtml(
            new Intl.NumberFormat(language).format(value)
          )}
        </td>
      </tr>
    `;
  }).join("");

  return `
    <div
      class="pdf-report"
      dir="${direction}"
      lang="${escapeHtml(language)}"
    >
      <header class="pdf-header">
        <div class="pdf-brand">SYRIATEL TMS</div>
        <h1>${escapeHtml(t("reports.pdfTitle"))}</h1>
        <p>${escapeHtml(t("reports.pdfSubtitle"))}</p>
      </header>

      <section class="pdf-meta">
        <p>
          <strong>${escapeHtml(t("reports.trainingProgram"))}:</strong>
          ${escapeHtml(
            report.training_program?.name ||
              t("reports.notSpecified")
          )}
        </p>

        <p>
          <strong>${escapeHtml(t("reports.generatedAt"))}:</strong>
          ${escapeHtml(report.generated_at || "—")}
        </p>
      </section>

      <table>
        <thead>
          <tr>
            <th>${escapeHtml(t("reports.indicator"))}</th>
            <th>${escapeHtml(t("reports.count"))}</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>

      <footer class="pdf-footer">
        ${escapeHtml(t("reports.pdfFooter"))}
      </footer>
    </div>
  `;
}

async function downloadReportPdf(report, t, language) {
  const [{ default: html2canvas }, { jsPDF }] = await Promise.all([
    import("html2canvas"), import("jspdf"),
  ]);
  const frame = document.createElement("iframe");
  frame.title = "Report export";
  frame.setAttribute("aria-hidden", "true");
  frame.tabIndex = -1;
  frame.style.cssText = "position:fixed;left:-10000px;top:0;width:750px;height:1500px;border:0;pointer-events:none;";
  document.body.appendChild(frame);
  try {
    const exportDocument = frame.contentDocument;
    exportDocument.open();
    exportDocument.write('<!doctype html><html><head><meta charset="utf-8"></head><body style="margin:0"></body></html>');
    exportDocument.close();
    const element = exportDocument.createElement("div");
    element.innerHTML = createReportHtml(report, t, language);

    const style = exportDocument.createElement("style");

    style.textContent = `
      .pdf-report {
        width: 700px;
        padding: 24px;
        box-sizing: border-box;
        background: #ffffff;
        color: #222222;
        font-family: Arial, Tahoma, sans-serif;
        font-size: 14px;
        line-height: 1.8;
      }

      .pdf-header {
        text-align: center;
        border-bottom: 3px solid #e60000;
        padding-bottom: 18px;
        margin-bottom: 22px;
      }

      .pdf-brand {
        color: #e60000;
        font-size: 20px;
        font-weight: bold;
      }

      .pdf-report table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
      }

      .pdf-report th {
        background: #e60000;
        color: #ffffff;
        padding: 12px;
      }

      .pdf-report td {
        border: 1px solid #dddddd;
        padding: 12px;
      }

      .pdf-report .number {
        text-align: center;
      }

      .pdf-footer {
        margin-top: 24px;
        padding-top: 12px;
        border-top: 1px solid #dddddd;
      }
    `;

    exportDocument.head.appendChild(style);

    exportDocument.body.appendChild(element);
    await exportDocument.fonts.ready;

    const content = element.querySelector(".pdf-report");

    if (!content || !content.innerText.trim()) {
      throw new Error("Report HTML is empty");
    }

    const canvas = await html2canvas(content, {
      scale: 2,
      backgroundColor: "#ffffff",
      logging: false,
      useCORS: true,
      width: content.scrollWidth,
      height: content.scrollHeight,
      windowWidth: Math.max(
        exportDocument.documentElement.clientWidth,
        content.scrollWidth
      ),
      windowHeight: Math.max(
        exportDocument.documentElement.clientHeight,
        content.scrollHeight
      ),
      scrollX: 0,
      scrollY: 0,
    });

    if (!canvas.width || !canvas.height) {
      throw new Error("Canvas has invalid dimensions");
    }

    // تحقق من أن الالتقاط يحتوي على صورة فعلية.
    const imageData = canvas.toDataURL("image/png");

    if (imageData.length < 1000) {
      throw new Error("Canvas image appears to be empty");
    }

    const pdf = new jsPDF({
      orientation: "portrait",
      unit: "mm",
      format: "a4",
      compress: true,
    });

    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();

    const margin = 10;
    const maxWidth = pageWidth - margin * 2;
    const maxHeight = pageHeight - margin * 2;

    // احتواء التقرير في صفحة A4 واحدة إن أمكن.
    const ratio = Math.min(
      maxWidth / canvas.width,
      maxHeight / canvas.height
    );

    const imageWidth = canvas.width * ratio;
    const imageHeight = canvas.height * ratio;

    const x = (pageWidth - imageWidth) / 2;

    pdf.addImage(
      imageData,
      "PNG",
      x,
      margin,
      imageWidth,
      imageHeight
    );

    pdf.save(
      `TMS-Training-Report-${report.training_program?.id ?? "cycle"}.pdf`
    );
  } finally {
    frame.remove();
  }
}



function StatCard({ label, value, Icon, language }) {
  return (
    <Card>
      <div className={styles.statCard}>
        <div className={styles.statIcon}>
          <Icon size={22} />
        </div>

        <div className={styles.statContent}>
          <span className={styles.statLabel}>{label}</span>
          <strong className={styles.statValue}>
            {new Intl.NumberFormat(language).format(
              Number(value ?? 0)
            )}
          </strong>
        </div>
      </div>
    </Card>
  );
}

export default function ReportsPage() {
  const { t, i18n } = useTranslation("hr");

  const [report, setReport] = useState(null);
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);
  const [error, setError] = useState(null);
  const requestVersion = useRef(0);

  const language = i18n.resolvedLanguage || i18n.language || "ar";
  const isArabic = language.startsWith("ar");

  const getReport = useCallback(async () => {
    const version = ++requestVersion.current;
    setLoading(true);
    setError(null);
    try {
      const data = await getReportApi();
      if (!data || !data.training_program || typeof data.total_applications !== "number") {
        throw new Error("Invalid report response");
      }
      if (version === requestVersion.current) setReport(data);
    } catch (err) {
      if (version === requestVersion.current) setError({ message: err.response?.data?.message });
    } finally {
      if (version === requestVersion.current) setLoading(false);
    }
  }, []);

  useEffect(() => {
    getReport();
    return () => { requestVersion.current += 1; };
  }, [getReport]);

  const download = async () => {
    if (!report || loading || exporting) return;
    setExporting(true);
    try {
      await downloadReportPdf(report, t, language);
      toast.success(t("reports.downloadSuccess"));
    } catch (err) {
      console.error("Failed to export report:", err);
      toast.error(t("reports.downloadError"));
    } finally {
      setExporting(false);
    }
  };

  return (
    <main className={styles.page} dir={isArabic ? "rtl" : "ltr"}>
      <header className={styles.header}>
        <div className={styles.heading}>
          <div className={styles.titleIcon}>
            <FileText size={26} />
          </div>

          <div>
            <h1>{t("reports.title")}</h1>
            <p>{t("reports.subtitle")}</p>
          </div>
        </div>

        <div className={styles.actions}>
          <Button
            onClick={getReport}
            disabled={loading || exporting}
            loading={loading}
            icon={FileText}
          >
            {loading ? t("reports.generating") : t("reports.refresh")}
          </Button>
          <Button onClick={download} disabled={!report || loading || exporting} loading={exporting} icon={Download}>
            {exporting ? t("reports.exporting") : t("reports.exportPdf")}
          </Button>
        </div>
      </header>

      {error && <p role="alert">{error.message || t("reports.loadError")}</p>}

      {!report && !loading && !error && (
        <Card>
          <div className={styles.emptyState}>
            <FileText size={42} strokeWidth={1.5} />
            <h2>{t("reports.emptyTitle")}</h2>
            <p>{t("reports.emptyDescription")}</p>
          </div>
        </Card>
      )}

      {loading && !report && (
        <Card>
          <div className={styles.loadingState}>
            <LoaderCircle className={styles.spinner} size={32} />
            <p>{t("reports.loading")}</p>
          </div>
        </Card>
      )}

      {report && (
        <>
          <Card>
            <div className={styles.programHeader}>
              <div className={styles.programIcon}>
                <ClipboardList size={23} />
              </div>

              <div>
                <span className={styles.muted}>
                  {t("reports.trainingProgram")}
                </span>

                <h2>{report.training_program.name}</h2>

                <span className={styles.muted}>
                  {t("reports.generatedAt")}: {report.generated_at}
                </span>
              </div>
            </div>
          </Card>

          {GROUPS.map(({ key: groupKey, icon: GroupIcon }) => {
            const fields = REPORT_FIELDS.filter(
              (field) => field.group === groupKey
            );

            return (
              <section className={styles.section} key={groupKey}>
                <h2 className={styles.sectionTitle}>
                  {t(`reports.groups.${groupKey}`)}
                </h2>

                <div className={styles.statsGrid}>
                  {fields.map(({ key }) => (
                    <StatCard
                      key={key}
                      label={t(`reports.fields.${key}`)}
                      value={report[key]}
                      Icon={key.includes("department") ? Building2 :
                        key.includes("trainee") ? UserCheck : GroupIcon}
                      language={language}
                    />
                  ))}
                </div>
              </section>
            );
          })}
        </>
      )}
    </main>
  );
}

