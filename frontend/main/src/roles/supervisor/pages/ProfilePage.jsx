import { Building2, MapPin, Phone } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import AccountProfile, { ProfileHighlights } from '../../../features/account/AccountProfile';
import styles from '../../../features/account/AccountProfile.module.css';

export default function ProfilePage() {
  const { t } = useTranslation('account');

  return (
    <AccountProfile role="SUPERVISOR" endpoint="supervisor/profile">
      {({ work_information: work, contact_information: contact }) => {
        const location = work?.department_location;

        return (
          <ProfileHighlights title={t('profile.workDetails')} fields={[
            { label: t('profile.department'), value: work?.department, icon: Building2 },
            {
              label: t('profile.departmentLocation'),
              value: location && <span className={styles.city}>{t(`common:city.${location}`, { defaultValue: location })}</span>,
              icon: MapPin,
            },
            { label: t('profile.phone'), value: contact?.phone_number, direction: 'ltr', icon: Phone },
          ]} />
        );
      }}
    </AccountProfile>
  );
}
