import { Globe, Menu, X, ArrowUpRight } from "lucide-react";
import { useEffect, useState } from "react";
import { Link, NavLink, useLocation } from "react-router-dom";
import { useTranslation } from "react-i18next";
import i18n from "../i18n";
import styles from "./Header.module.css";
import logo from "../assets/syriatel-logo.webp";

export default function Header() {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const { pathname } = useLocation();
  const lang = i18n.language === "ar" ? "ar" : "en";

  useEffect(() => setOpen(false), [pathname]);

  const toggleLanguage = () => {
    const next = lang === "ar" ? "en" : "ar";
    i18n.changeLanguage(next);
    localStorage.setItem("syriatel-language", next);
    document.documentElement.lang = next;
    document.documentElement.dir = next === "ar" ? "rtl" : "ltr";
  };

  return (
    <header className={styles.header}>
      <div className={styles.inner}>
        <Link to="/" className={styles.logoLink} aria-label="Syriatel Home">
          <img src={logo} alt="Syriatel" className={styles.logo} />
        </Link>
        <nav className={`${styles.nav} ${open ? styles.open : ""}`}>
          <NavLink
            to="/"
            className={({ isActive }) => (isActive ? styles.active : "")}
          >
            {t("nav.home")}
          </NavLink>
          <NavLink
            to="/about"
            className={({ isActive }) => (isActive ? styles.active : "")}
          >
            {t("nav.about")}
          </NavLink>
          <a href="faq" onClick={() => setOpen(false)}>
            {t("nav.faqs")}
          </a>
          <Link to="/apply" className={styles.mobileApply}>
            {t("nav.apply")} <ArrowUpRight size={16} />
          </Link>
        </nav>
        <div className={styles.actions}>
          <button
            className={styles.lang}
            onClick={toggleLanguage}
            aria-label="Change language"
          >
            <Globe size={17} />
            <span>{lang === "ar" ? "EN" : "ع"}</span>
          </button>
          <Link to="/apply" className={styles.apply}>
            {t("nav.apply")} <ArrowUpRight size={17} />
          </Link>
          <button
            className={styles.menu}
            onClick={() => setOpen((v) => !v)}
            aria-label="Menu"
          >
            {open ? <X /> : <Menu />}
          </button>
        </div>
      </div>
    </header>
  );
}
