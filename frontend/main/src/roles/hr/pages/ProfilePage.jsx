import { IdCard } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import AccountProfile, { ProfileDetails, ProfileSection } from '../../../features/account/AccountProfile';

export default function ProfilePage() {
  const { t } = useTranslation('account');

  return (
    <AccountProfile role="HR" endpoint="hr/profile">
      {({ contact_information: contact }) => (
        <ProfileSection title={t('profile.contactDetails')} icon={IdCard}>
          <ProfileDetails fields={[[t('profile.phone'), contact?.phone_number, 'ltr']]} />
        </ProfileSection>
      )}
    </AccountProfile>
  );
}
