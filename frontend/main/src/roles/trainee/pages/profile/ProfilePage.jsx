import {
  BookOpen, BriefcaseBusiness, Building2, FileText, GraduationCap, IdCard, UserRound,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import ApplicationAttachmentButton from '../../../../components/ui/ApplicationAttachmentButton';
import AccountProfile, {
  ProfileDetails, ProfileHighlights, ProfileSection, ProfileValue,
} from '../../../../features/account/AccountProfile';
import styles from '../../../../features/account/AccountProfile.module.css';

const attachments = [
  ['cv', 'cv'],
  ['national_id', 'idAttachment'],
  ['academic_qualification', 'academicCertification'],
];

function programDate(value, formatter) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return <time dateTime={value}>{formatter.format(date)}</time>;
}

function TraineeDetails({ profile }) {
  const { t, i18n } = useTranslation('trainee');
  const personal = profile.personal_information ?? {};
  const contact = profile.contact_information ?? {};
  const education = profile.educational_information ?? {};
  const training = profile.training_information ?? {};
  const skills = Array.isArray(education.technical_skills)
    ? education.technical_skills.filter(Boolean).join(', ')
    : education.technical_skills;
  const dateFormatter = new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeZone: 'UTC' });

  return (
    <>
      <ProfileHighlights title={t('profile.training')} fields={[
        {
          label: t('profile.trainingProgram'),
          value: training.training_program,
          icon: GraduationCap,
          details: training.training_program && [
            [t('profile.programStartDate'), programDate(training.training_program_start_date, dateFormatter)],
            [t('profile.programEndDate'), programDate(training.training_program_end_date, dateFormatter)],
          ],
        },
        { label: t('account:profile.department'), value: training.department, icon: Building2 },
        { label: t('profile.directSupervisor'), value: training.direct_supervisor, icon: UserRound },
      ]} />

      <div className={styles.sections}>
        <ProfileSection title={t('profile.personal')} icon={IdCard}>
          <ProfileDetails fields={[
            [t('account:profile.phone'), contact.phone_number, 'ltr'],
            [t('profile.nationalId'), personal.national_id_number, 'ltr'],
            [t('profile.birthDate'), personal.birth_date, 'ltr'],
            [t('profile.city'), contact.city && (
              <span className={styles.city}>{t(`common:city.${contact.city}`, { defaultValue: contact.city })}</span>
            )],
            [t('profile.address'), contact.home_address],
          ]} />
        </ProfileSection>

        <ProfileSection title={t('profile.education')} icon={BookOpen}>
          <ProfileDetails fields={[
            [t('profile.university'), education.university],
            [t('profile.field'), education.field_of_study],
            [t('profile.academicMajor'), education.academic_major],
            [t('profile.yearOfStudy'), education.year_of_study && t(`common:academicYear.${education.year_of_study}`, { defaultValue: education.year_of_study })],
            [t('profile.gpa'), education.gpa, 'ltr'],
          ]} />
        </ProfileSection>

        <ProfileSection title={t('profile.technicalSkills')} icon={BriefcaseBusiness}>
          <p className={styles.skills}><ProfileValue value={skills} /></p>
        </ProfileSection>

        <ProfileSection title={t('profile.attachments')} icon={FileText}>
          <div className={styles.attachments}>
            {attachments.map(([type, label]) => (
              <ApplicationAttachmentButton key={type} applicationId={profile.application_id} attachmentType={type}>
                {t(`profile.${label}`)}
              </ApplicationAttachmentButton>
            ))}
          </div>
        </ProfileSection>
      </div>
    </>
  );
}

export default function ProfilePage() {
  return (
    <AccountProfile role="TRAINEE" endpoint="profile">
      {(profile) => <TraineeDetails profile={profile} />}
    </AccountProfile>
  );
}
