import styles from "./ButtonLoader.module.css";

export default function ButtonLoader() {
  return (
    <span className={styles.loader} aria-label="Loading">
      <span></span>
      <span></span>
      <span></span>
    </span>
  );
}