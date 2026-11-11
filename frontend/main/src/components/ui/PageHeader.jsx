import styles from './PageHeader.module.css';
import RefreshingIndicator from './RefreshingIndicator';
export default function PageHeader({ title, action,update }) {
  return (
    <div className={styles.header}>
      <h3>{title}
        {
          update && (
            <RefreshingIndicator/>
          )
        }
      </h3>
      {action}
    </div>
  );
}
