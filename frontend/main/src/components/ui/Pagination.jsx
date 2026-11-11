import { ChevronLeft, ChevronRight } from "lucide-react";
import { useTranslation } from "react-i18next";

import styles from "./Pagination.module.css";

export default function Pagination({
  currentPage,
  totalPages,
  onPageChange,
}) {
  const { i18n, t } = useTranslation("common");

  const isRTL = i18n.dir() === "rtl";

  if (!totalPages || totalPages <= 1) {
    return null;
  }

  const getPages = () => {
    const pages = [];

    // عدد الصفحات القليلة
    if (totalPages <= 7) {
      for (let i = 1; i <= totalPages; i++) {
        pages.push(i);
      }

      return pages;
    }

    // بداية الصفحات
    if (currentPage <= 4) {
      return [1, 2, 3, 4, 5, "...", totalPages];
    }

    // نهاية الصفحات
    if (currentPage >= totalPages - 3) {
      return [
        1,
        "...",
        totalPages - 4,
        totalPages - 3,
        totalPages - 2,
        totalPages - 1,
        totalPages,
      ];
    }

    // منتصف الصفحات
    return [
      1,
      "...",
      currentPage - 1,
      currentPage,
      currentPage + 1,
      "...",
      totalPages,
    ];
  };

  const pages = getPages();

  const goToPage = (page) => {
    if (
      page === "..." ||
      page === currentPage ||
      page < 1 ||
      page > totalPages
    ) {
      return;
    }

    onPageChange(page);
  };

  return (
    <div className={styles.wrapper}>
      <div className={styles.pagination}>
        {/* Previous */}
        <button
          type="button"
          className={styles.arrowButton}
          disabled={currentPage === 1}
          onClick={() =>
            onPageChange(currentPage - 1)
          }
          aria-label={t("pagination.previous")}
        >
          {isRTL ? (
            <ChevronRight size={18} />
          ) : (
            <ChevronLeft size={18} />
          )}
        </button>

        {/* Pages */}
        <div className={styles.pages}>
          {pages.map((page, index) =>
            page === "..." ? (
              <span
                key={`ellipsis-${index}`}
                className={styles.ellipsis}
              >
                •••
              </span>
            ) : (
              <button
                key={page}
                type="button"
                className={`${styles.pageButton} ${
                  currentPage === page
                    ? styles.active
                    : ""
                }`}
                onClick={() => goToPage(page)}
                aria-current={
                  currentPage === page
                    ? "page"
                    : undefined
                }
              >
                {page}
              </button>
            ),
          )}
        </div>

        {/* Next */}
        <button
          type="button"
          className={styles.arrowButton}
          disabled={currentPage === totalPages}
          onClick={() =>
            onPageChange(currentPage + 1)
          }
          aria-label={t("pagination.next")}
        >
          {isRTL ? (
            <ChevronLeft size={18} />
          ) : (
            <ChevronRight size={18} />
          )}
        </button>
      </div>
    </div>
  );
}