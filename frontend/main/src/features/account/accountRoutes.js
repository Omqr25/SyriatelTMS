import { Settings, UserRound } from 'lucide-react';
import SettingsPage from './SettingsPage';

export function accountRoutes(ProfilePage, profileDescriptionKey = 'account:profile.menuDescription') {
  return [
    {
      path: 'profile',
      icon: UserRound,
      labelKey: 'account:profile.title',
      descriptionKey: profileDescriptionKey,
      Component: ProfilePage,
    },
    {
      path: 'settings',
      icon: Settings,
      labelKey: 'account:settings.title',
      descriptionKey: 'account:settings.menuDescription',
      Component: SettingsPage,
    },
  ];
}
