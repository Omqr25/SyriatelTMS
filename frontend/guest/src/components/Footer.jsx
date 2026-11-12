import {
  FaFacebookF,
  FaXTwitter,
  FaTelegram,
  FaInstagram,
  FaYoutube,
} from "react-icons/fa6";
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
          <a
            href="https://www.facebook.com/Syriatel.Sy/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Facebook"
          >
            <FaFacebookF size={17} />
          </a>
          <a
            href="https://twitter.com/Syriatelco"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Twitter"
          >
            <FaXTwitter size={17} />
          </a>
          <a
            href="https://telegram.me/syriatel"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Telegram"
          >
            <FaTelegram size={17} />
          </a>
          <a
            href="https://instagram.com/syriatel.sy/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Instagram"
          >
            <FaInstagram size={17} />
          </a>
          <a
            href="http://www.youtube.com/Syriatelchannel"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="YouTube"
          >
            <FaYoutube size={17} />
          </a>
        </div>
      </div>
      <div className={styles.bottom}>
        <div className={styles.links}>
          <Link to="/">{t("nav.home")}</Link>
          <Link to="/about">{t("nav.about")}</Link>
          <a href="/faq">{t("nav.faqs")}</a>
          {/* <a href="#contact">{t("footer.contact")}</a> */}
        </div>
        <span>{t("footer.rights")}</span>
      </div>
    </footer>
  );
}
