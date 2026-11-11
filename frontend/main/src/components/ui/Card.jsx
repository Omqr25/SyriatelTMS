import styles from "./Card.module.css";

export default function Card({
  as: Tag = "section",
  padded = true,
  className = "",
  children,
  ...rest
}) {
  const classes = [styles.card, padded && styles.padded, className]
    .filter(Boolean)
    .join(" ");
  return (
    <Tag className={classes} {...rest}>
      {children}
    </Tag>
  );
}
