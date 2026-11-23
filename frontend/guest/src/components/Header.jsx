import { Globe, Menu, X, ArrowUpRight } from "lucide-react";
import { useEffect, useState } from "react";
import { Link, NavLink, useLocation } from "react-router-dom";
import { useTranslation } from "react-i18next";
import i18n from "../i18n";
import ThemeToggle from "./ThemeToggle";
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
        <nav id="guest-navigation" className={`${styles.nav} ${open ? styles.open : ""}`}>
          <NavLink
            to="/" end
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
          <NavLink to="/gallery" className={({ isActive }) => (isActive ? styles.active : "")}>
            {t("nav.gallery")}
          </NavLink>
          <NavLink
            to="/faq"
            className={({ isActive }) => (isActive ? styles.active : "")}
          >
            {t("nav.faqs")}
          </NavLink>
          <Link to="/apply" className={styles.mobileApply}>
            {t("nav.apply")} <ArrowUpRight size={16} />
          </Link>
        </nav>
        <div className={styles.actions}>
          <ThemeToggle />
          <button
            type="button"
            className="headerControl"
            onClick={toggleLanguage}
            aria-label={t("header.language")}
            title={t("header.language")}
          >
            <Globe className="headerGlobe" size={17} aria-hidden="true" />
            <span>{lang === "ar" ? "EN" : "عربي"}</span>
          </button>
          <Link to="/apply" className={styles.apply}>
            {t("nav.apply")} <ArrowUpRight size={17} />
          </Link>
          <button
            type="button"
            className={`headerControl ${styles.menu}`}
            onClick={() => setOpen((v) => !v)}
            aria-label={t("header.menu")}
            aria-expanded={open}
            aria-controls="guest-navigation"
          >
            {open ? <X size={20} aria-hidden="true" /> : <Menu size={20} aria-hidden="true" />}
          </button>
        </div>
      </div>
    </header>
  );
}
