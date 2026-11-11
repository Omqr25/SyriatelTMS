// import { FileDown } from 'lucide-react';
// import { useTranslation } from 'react-i18next';
// import Button from '../../../components/ui/Button';
// import Card from '../../../components/ui/Card';
// import { useToast } from '../../../context/ToastContext';
// import { useData } from '../../../data/DataContext';
// import styles from './ReportsPage.module.css';
// import UnderDevelopment from '../../../components/error/UnderDevelopment';

// export default function ReportsPage() {
//   const { t } = useTranslation('hr');
//   const { applicants, trainees, interviews } = useData();
//   const { toast } = useToast();

//   const acceptanceRate = applicants.length ? Math.round((trainees.length / applicants.length) * 100) : 0;



  

//   return (
//     <Card>
//       <h4 className={styles.title}>{t('reports.title')}</h4>

//       <div className={styles.grid}>
//         <div className={`${styles.metric} ${styles.primary}`}>
//           <p>{t('reports.acceptanceRate')}</p>
//           <strong>{acceptanceRate}%</strong>
//         </div>
//         <div className={styles.metric}>
//           <p>{t('reports.totalInterviews')}</p>
//           <strong>{interviews.length}</strong>
//         </div>
//       </div>

//       <Button variant="dark" icon={FileDown} onClick={() => toast(t('reports.exporting'), 'info')}>
//         {t('reports.exportPdf')}
//       </Button>
//     </Card>
//   );
// }



import { useState } from "react";
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


import html2canvas from "html2canvas";
import { jsPDF } from "jspdf";

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

// async function downloadReportPdf(report, t, language) {
//   const element = document.createElement("div");
//   element.innerHTML = createReportHtml(report, t, language);

//   element.style.cssText = `
//     position: fixed;
//     left: -10000px;
//     top: 0;
//     width: 180mm;
//     padding: 8mm;
//     background: #fff;
//     color: #222;
//     font-family: Arial, Tahoma, sans-serif;
//     box-sizing: border-box;
//   `;

//   const style = document.createElement("style");

//   style.textContent = `
//     .pdf-report {
//       direction: inherit;
//       text-align: start;
//       font-family: Arial, Tahoma, sans-serif;
//       font-size: 12px;
//       color: #222;
//       overflow-wrap: anywhere;
//     }

//     .pdf-header {
//       text-align: center;
//       border-bottom: 3px solid #e60000;
//       padding-bottom: 18px;
//       margin-bottom: 22px;
//     }

//     .pdf-brand {
//       color: #e60000;
//       font-size: 19px;
//       font-weight: bold;
//       letter-spacing: 1px;
//     }

//     .pdf-header h1 {
//       margin: 12px 0 6px;
//       font-size: 23px;
//     }

//     .pdf-header p {
//       color: #666;
//       margin: 0;
//       line-height: 1.8;
//     }

//     .pdf-meta {
//       padding: 10px 14px;
//       background: #f7f7f7;
//       border-radius: 5px;
//       margin-bottom: 20px;
//     }

//     .pdf-meta p {
//       margin: 8px 0;
//       line-height: 1.8;
//     }

//     .pdf-report table {
//       width: 100%;
//       border-collapse: collapse;
//       margin-top: 16px;
//     }

//     .pdf-report thead {
//       display: table-header-group;
//     }

//     .pdf-report tr {
//       break-inside: avoid;
//       page-break-inside: avoid;
//     }

//     .pdf-report th {
//       background: #e60000;
//       color: #fff;
//       padding: 12px;
//       text-align: start;
//     }

//     .pdf-report td {
//       border: 1px solid #ddd;
//       padding: 11px;
//       text-align: start;
//       line-height: 1.6;
//     }

//     .pdf-report tbody tr:nth-child(even) {
//       background: #f8f8f8;
//     }

//     .pdf-report .number {
//       text-align: center;
//       direction: ltr;
//     }

//     .pdf-footer {
//       margin-top: 28px;
//       padding-top: 12px;
//       border-top: 1px solid #ddd;
//       text-align: center;
//       color: #777;
//       font-size: 10px;
//       line-height: 1.8;
//     }
//   `;

//   element.prepend(style);
//   document.body.appendChild(element);

//   try {
//     await html2pdf()
//       .set({
//         margin: 8,
//         filename: `TMS-Training-Report-${report.training_program?.id ?? "cycle"}.pdf`,
//         image: { type: "jpeg", quality: 0.98 },
//         html2canvas: {
//           scale: 2,
//           backgroundColor: "#ffffff",
//           useCORS: true,
//         },
//         jsPDF: {
//           unit: "mm",
//           format: "a4",
//           orientation: "portrait",
//         },
//         pagebreak: {
//           mode: ["css", "legacy"],
//         },
//       })
//       .from(element)
//       .save();
//   } finally {
//     element.remove();
//   }
// }



async function downloadReportPdf(report, t, language) {
  const element = document.createElement("div");

  element.innerHTML = createReportHtml(report, t, language);

  const style = document.createElement("style");

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

  document.head.appendChild(style);

  element.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 750px;
    background: #ffffff;
    z-index: 99999;
    pointer-events: none;
  `;

  document.body.appendChild(element);

  try {
    // انتظر حتى يرسم المتصفح التقرير.
    await new Promise((resolve) =>
      requestAnimationFrame(() =>
        requestAnimationFrame(resolve)
      )
    );

    const content = element.querySelector(".pdf-report");

    if (!content || !content.innerText.trim()) {
      throw new Error("Report HTML is empty");
    }

    const canvas = await html2canvas(content, {
      scale: 2,
      backgroundColor: "#ffffff",
      logging: true,
      useCORS: true,
      width: content.scrollWidth,
      height: content.scrollHeight,
      windowWidth: Math.max(
        document.documentElement.clientWidth,
        content.scrollWidth
      ),
      windowHeight: Math.max(
        document.documentElement.clientHeight,
        content.scrollHeight
      ),
      scrollX: 0,
      scrollY: 0,
    });

    // console.log("PDF canvas dimensions:", {
    //   width: canvas.width,
    //   height: canvas.height,
    // });

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
    element.remove();
    style.remove();
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
  const [loading, setLoading] = useState(false);

  const language = i18n.resolvedLanguage || i18n.language || "ar";
  const isArabic = language.startsWith("ar");

  const getReport = async () => {
    if (loading) return;

    setLoading(true);

    try {
      const response = await getReportApi();
      const data = response?.data ?? response;

      if (
        !data ||
        typeof data !== "object" ||
        !data.training_program ||
        typeof data.total_applications !== "number"
      ) {
        throw new Error("Invalid report response");
      }

      setReport(data);

      await downloadReportPdf(data, t, language);

      toast.success(t("reports.downloadSuccess"));
    } catch (error) {
      console.error("Failed to generate report:", error);
      toast.error(t("reports.downloadError"));
    } finally {
      setLoading(false);
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

        <Button
          onClick={getReport}
          disabled={loading}
          loading={loading}
          icon={loading ? LoaderCircle : Download}
        >
          {loading
            ? t("reports.generating")
            : report
              ? t("reports.refresh")
              : t("reports.generate")}
        </Button>
      </header>

      {!report && !loading && (
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

