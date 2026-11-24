import { useId } from 'react';
import { UserRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import Button from '../../components/ui/Button';
import Card from '../../components/ui/Card';
import useAccountProfile from './useAccountProfile';
import styles from './AccountProfile.module.css';

export function ProfileSection({ title, icon: Icon, children }) {
  const headingId = useId();
  return (
    <Card className={styles.section} aria-labelledby={headingId}>
      <h2 id={headingId} className={styles.sectionTitle}>
        <Icon size={19} aria-hidden="true" /> {title}
      </h2>
      {children}
    </Card>
  );
}

export function ProfileValue({ value, direction }) {
  const { t } = useTranslation('account');
  if (value === null || value === undefined || value === '') {
    return <span className={styles.missing}>{t('profile.notProvided')}</span>;
  }
  return <bdi dir={direction}>{value}</bdi>;
}

export function ProfileDetails({ fields, className = styles.details }) {
  return (
    <dl className={className}>
      {fields.map(([label, value, direction]) => (
        <div key={label}>
          <dt>{label}</dt>
          <dd><ProfileValue value={value} direction={direction} /></dd>
        </div>
      ))}
    </dl>
  );
}

export function ProfileHighlights({ title, fields }) {
  const headingId = useId();
  return (
    <section aria-labelledby={headingId}>
      <h2 id={headingId} className={styles.highlightsTitle}>{title}</h2>
      <dl className={styles.highlights}>
        {fields.map(({ label, value, icon: Icon, direction, details }) => (
          <div className={styles.highlight} key={label}>
            <dt>
              <span className={styles.highlightIcon}><Icon size={20} aria-hidden="true" /></span>
              {label}
            </dt>
            <dd>
              <ProfileValue value={value} direction={direction} />
              {details && <ProfileDetails fields={details} className={styles.highlightDetails} />}
            </dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

export default function AccountProfile({ role, endpoint, children }) {
  const { t } = useTranslation('account');
  const { profile, loading, error, retry } = useAccountProfile(endpoint);

  if (loading || !profile) {
    return (
      <Card className={`${styles.page} ${styles.state}`}>
        <UserRound size={25} aria-hidden="true" />
        {loading ? (
          <p role="status">{t('profile.loading')}</p>
        ) : (
          <>
            <div role="alert"><h1>{t('profile.unavailable')}</h1></div>
            {error && <Button variant="secondary" onClick={retry}>{t('profile.retry')}</Button>}
          </>
        )}
      </Card>
    );
  }

  const name = profile.personal_information?.name;
  const email = profile.contact_information?.email;
  const initials = name?.trim().split(/\s+/).slice(0, 2)
    .map((word) => Array.from(word)[0]).join('').toLocaleUpperCase();

  return (
    <div className={styles.page}>
      <header className={styles.hero}>
        <div className={styles.avatar} aria-hidden="true">
          {initials || <UserRound size={27} />}
        </div>
        <div className={styles.identity}>
          <span className={styles.role}>{t(`common:role.${role}`)}</span>
          <h1><bdi>{name || t('profile.title')}</bdi></h1>
          {email && <p><bdi dir="ltr">{email}</bdi></p>}
        </div>
      </header>

      {children(profile)}
    </div>
  );
}
