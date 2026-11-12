import styles from "./Loading.module.css";
import logo from "../../assets/syriatel-logo.webp";

export default function Loading() {
  return (
    <div className={styles.splashScreen}>
      <div className={styles.container}>
        <div className={`${styles.orbit} ${styles.orbitOne}`}>
          <span />
        </div>

        <div className={`${styles.orbit} ${styles.orbitTwo}`}>
          <span />
        </div>

        <div className={styles.logoWrapper}>
          <img src={logo} alt="Syriatel — سيريتل" className={styles.logo} />
        </div>
      </div>
    </div>
  );
}
