import {
  BookOpen, BriefcaseBusiness, Building2, FileText, GraduationCap, IdCard, UserRound,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import ApplicationAttachmentButton from '../../../../components/ui/ApplicationAttachmentButton';
import Button from '../../../../components/ui/Button';
import Card from '../../../../components/ui/Card';
import useTraineeProfile from '../../../../hooks/TraineeHooks/useTraineeProfile';
import styles from './ProfilePage.module.css';

const attachments = [
  ['cv', 'cv'],
  ['national_id', 'idAttachment'],
  ['academic_qualification', 'academicCertification'],
];

function ProfileSection({ title, icon: Icon, id, children }) {
  return (
    <Card className={styles.section} aria-labelledby={id}>
      <h2 id={id} className={styles.sectionTitle}>
        <Icon size={19} aria-hidden="true" /> {title}
      </h2>
      {children}
    </Card>
  );
}

function DetailValue({ value, direction }) {
  const { t } = useTranslation('trainee');
  if (value === null || value === undefined || value === '') {
    return <span className={styles.missing}>{t('profile.notProvided')}</span>;
  }
  return <bdi dir={direction}>{value}</bdi>;
}

function Details({ fields }) {
  const { t } = useTranslation('trainee');

  return (
    <dl className={styles.details}>
      {fields.map(([label, value, direction]) => (
        <div key={label}>
          <dt>{t(`profile.${label}`)}</dt>
          <dd><DetailValue value={value} direction={direction} /></dd>
        </div>
      ))}
    </dl>
  );
}

export default function ProfilePage() {
  const { t } = useTranslation('trainee');
  const { profile, loading, error, retry } = useTraineeProfile();

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

  const personal = profile.personal_information ?? {};
  const contact = profile.contact_information ?? {};
  const education = profile.educational_information ?? {};
  const training = profile.training_information ?? {};
  const initials = personal.name?.trim().split(/\s+/).slice(0, 2)
    .map((word) => Array.from(word)[0]).join('').toLocaleUpperCase();
  const skills = Array.isArray(education.technical_skills)
    ? education.technical_skills.filter(Boolean).join(', ')
    : education.technical_skills;
  const assignment = [
    ['trainingProgram', training.training_program, GraduationCap],
    ['department', training.department, Building2],
    ['directSupervisor', training.direct_supervisor, UserRound],
  ];

  return (
    <div className={styles.page}>
      <header className={styles.hero}>
        <div className={styles.avatar} aria-hidden="true">
          {initials || <UserRound size={27} />}
        </div>
        <div className={styles.identity}>
          <span className={styles.role}>{t('common:role.TRAINEE')}</span>
          <h1><bdi>{personal.name || t('profile.title')}</bdi></h1>
          {contact.email && <p><bdi dir="ltr">{contact.email}</bdi></p>}
        </div>
      </header>

      <section aria-labelledby="profile-training">
        <h2 id="profile-training" className={styles.trainingTitle}>{t('profile.training')}</h2>
        <dl className={styles.training}>
          {assignment.map(([label, value, Icon]) => (
            <div className={styles.trainingItem} key={label}>
              <dt>
                <span className={styles.trainingIcon}><Icon size={20} aria-hidden="true" /></span>
                {t(`profile.${label}`)}
              </dt>
              <dd><DetailValue value={value} /></dd>
            </div>
          ))}
        </dl>
      </section>

      <div className={styles.sections}>
        <ProfileSection id="profile-personal" title={t('profile.personal')} icon={IdCard}>
          <Details fields={[
            ['phone', contact.phone_number, 'ltr'],
            ['nationalId', personal.national_id_number, 'ltr'],
            ['birthDate', personal.birth_date, 'ltr'],
            ['city', contact.city && (
              <span className={styles.city}>{t(`common:city.${contact.city}`, { defaultValue: contact.city })}</span>
            )],
            ['address', contact.home_address],
          ]} />
        </ProfileSection>

        <ProfileSection id="profile-education" title={t('profile.education')} icon={BookOpen}>
          <Details fields={[
            ['university', education.university],
            ['field', education.field_of_study],
            ['academicMajor', education.academic_major],
            ['yearOfStudy', education.year_of_study && t(`common:academicYear.${education.year_of_study}`, { defaultValue: education.year_of_study })],
            ['gpa', education.gpa, 'ltr'],
          ]} />
        </ProfileSection>

        <ProfileSection id="profile-skills" title={t('profile.technicalSkills')} icon={BriefcaseBusiness}>
          <p className={styles.skills}><DetailValue value={skills} /></p>
        </ProfileSection>

        <ProfileSection id="profile-attachments" title={t('profile.attachments')} icon={FileText}>
          <div className={styles.attachments}>
            {attachments.map(([type, label]) => (
              <ApplicationAttachmentButton key={type} applicationId={profile.application_id} attachmentType={type}>
                {t(`profile.${label}`)}
              </ApplicationAttachmentButton>
            ))}
          </div>
        </ProfileSection>
      </div>
    </div>
  );
}
