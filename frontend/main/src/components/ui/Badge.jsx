import styles from './Badge.module.css';

/** tone: neutral | red | green | amber | blue */
export default function Badge({ tone = 'neutral', children }) {
  return <span className={`${styles.badge} ${styles[tone]}`}>{children}</span>;
}
