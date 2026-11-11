import { Loader2 } from "lucide-react";
import styles from "./Button.module.css";

export default function Button({
  variant = "primary",
  size = "md",
  loading = false,
  fullWidth = false,
  icon: Icon,
  type = "button",
  className = "",
  disabled,
  children,
  ...rest
}) {
  const classes = [
    styles.btn,
    styles[variant],
    styles[size],
    fullWidth && styles.block,
    className,
  ]
    .filter(Boolean)
    .join(" ");
  return (
    <button
      type={type}
      className={classes}
      disabled={disabled || loading}
      {...rest}
    >
      
      {loading ? (
        <Loader2 size={16} className={styles.spin} />
      ) : Icon ? (
        <Icon size={16} />
      ) : null}

      {children && <span>{children}</span>}
    </button>
  );
}
