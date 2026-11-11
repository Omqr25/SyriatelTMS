
import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import {
  ArrowLeft,
  ArrowRight,
  Construction,
  Settings,
  RadioTower,
} from "lucide-react";

import styles from "./UnderDevelopment.module.css";

export default function UnderDevelopment({
  homePath = "/",
  logoSrc = "/logo.webp",
}) {
  const { t, i18n } = useTranslation("common");

  const isArabic = i18n.resolvedLanguage?.startsWith("ar");
  const direction = isArabic ? "rtl" : "ltr";

  const BackIcon = isArabic ? ArrowLeft : ArrowRight;

  return (
    <main className={styles.page} dir={direction}>
      <div className={styles.backgroundShape} />

      <header className={styles.header}>
        <Link
          to={homePath}
          className={styles.logoLink}
          aria-label="Syriatel"
        >
          <img
            src={logoSrc}
            alt="Syriatel"
            className={styles.logo}
          />
        </Link>

        <span className={styles.badge}>
          <span className={styles.statusDot} />
          {t("underDevelopment.badge")}
        </span>
      </header>

      <section className={styles.content}>
        <div className={styles.illustration} aria-hidden="true">
          <div className={styles.glow} />

          <div className={styles.browser}>
            <div className={styles.browserBar}>
              <span />
              <span />
              <span />
            </div>

            <div className={styles.browserBody}>
              <div className={styles.gear}>
                <Settings size={100} strokeWidth={1.3} />
              </div>

              <div className={styles.constructionIcon}>
                <Construction size={48} strokeWidth={1.5} />
              </div>
            </div>
          </div>

          <div className={styles.tower}>
            <RadioTower size={54} strokeWidth={1.2} />
          </div>

          <span className={styles.sparkle}>✦</span>
          <span className={styles.sparkleTwo}>✧</span>
        </div>

        <div className={styles.textContent}>
          <p className={styles.eyebrow}>
            {t("underDevelopment.eyebrow")}
          </p>

          <h1 className={styles.title}>
            {t("underDevelopment.title")}{" "}
            <span>{t("underDevelopment.titleAccent")}</span>
          </h1>

          <p className={styles.description}>
            {t("underDevelopment.description")}
          </p>

          <div className={styles.divider} aria-hidden="true">
            <span />
            <Settings size={20} />
            <span />
          </div>

          <Link to={homePath} className={styles.homeButton}>
            <BackIcon size={19} />
            <span>{t("underDevelopment.homeButton")}</span>
          </Link>

          <p className={styles.footerNote}>
            {t("underDevelopment.footerNote")}
          </p>
        </div>
      </section>

      <footer className={styles.footer}>
        <span>{t("underDevelopment.footer")}</span>
      </footer>
    </main>
  );
}
