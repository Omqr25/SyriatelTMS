
import { LoaderCircle } from "lucide-react";
import { useTranslation } from "react-i18next";
import styles from "./RefreshingIndicator.module.css";

export default function RefreshingIndicator() {
  const { t } = useTranslation("common");

//   if (!show) return null;

  return (
    <span
      className={styles.indicator}
      role="status"
      aria-live="polite"
    >
      <LoaderCircle
        size={15}
        className={styles.spinner}
        aria-hidden="true"
      />

      {/* <span>{t("status.refreshing")}</span> */}
    </span>
  );
}