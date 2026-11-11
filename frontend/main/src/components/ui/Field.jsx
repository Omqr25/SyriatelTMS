import styles from "./Field.module.css";

export function Field({ label, children }) {
  return (
    <label className={styles.field}>
      <span className={styles.label}>{label}</span>
      {children}
    </label>
  );
}

export const Input = (props, type = "text") => (
  <input className={styles.control} {...props} />
);
export const Select = (props) => (
  <select className={styles.control} {...props} />
);
export const Textarea = (props) => (
  <textarea className={styles.control} rows={3} {...props} />
);
