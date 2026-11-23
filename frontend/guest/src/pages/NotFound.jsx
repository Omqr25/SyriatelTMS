import { ArrowLeft, MapPinOff } from "lucide-react";
import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import styles from "./NotFound.module.css";

export default function NotFound() {
  const { t } = useTranslation();

  return (
    <section className={styles.page} aria-labelledby="not-found-title">
      <div className={styles.icon}><MapPinOff size={28} aria-hidden="true" /></div>
      <p className={styles.code}>404</p>
      <h1 id="not-found-title">{t("notFound.title")}</h1>
      <p>{t("notFound.description")}</p>
      <Link to="/" className={styles.home}>
        <ArrowLeft size={18} aria-hidden="true" /> {t("notFound.home")}
      </Link>
    </section>
  );
}
