// import { Facebook, Instagram, Linkedin, Youtube } from 'lucide-react';
import { Link } from "react-router-dom";
import { useTranslation } from "react-i18next";
import styles from "./Footer.module.css";
import logo from "../assets/syriatel-logo.webp";

export default function Footer() {
  const { t } = useTranslation();
  return (
    <footer className={styles.footer}>
      <div className={styles.top}>
        <Link to="/">
          <img src={logo} alt="Syriatel" className={styles.logo} />
        </Link>
        <p>{t("brand.program")}</p>
        <div className={styles.socials}>
          <a href="#" aria-label="LinkedIn">
            {/* <Linkedin size={17} /> */}
          </a>
          <a href="#" aria-label="Facebook">
            {/* <Facebook size={17} /> */}
          </a>
          <a href="#" aria-label="Instagram">
            {/* <Instagram size={17} /> */}
          </a>
          <a href="#" aria-label="YouTube">
            {/* <Youtube size={17} /> */}
          </a>
        </div>
      </div>
      <div className={styles.bottom}>
        <div className={styles.links}>
          <Link to="/">{t("nav.home")}</Link>
          <Link to="/about">{t("nav.about")}</Link>
          <a href="#faq">{t("nav.faqs")}</a>
          <a href="#contact">{t("footer.contact")}</a>
        </div>
        <span>{t('footer.rights')}</span>
      </div>
    </footer>
  );
}
