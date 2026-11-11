import styles from "./Loading.module.css";

export default function Loading({ text = "Loading..." }) {
  return (
    <div className={styles.overlay}>
      <div className={styles.container}>
        <div className={styles.logoWrapper}>
          <img
            src="/logo.webp"
            alt="Syriatel"
            className={styles.logo}
          />
        </div>

        <div className={styles.spinner} />

        <p className={styles.text}>{text}</p>
      </div>
    </div>
  );
}