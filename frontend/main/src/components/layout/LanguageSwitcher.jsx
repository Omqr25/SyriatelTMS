import { Globe } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function LanguageSwitcher({ className = '' }) {
  const { t, i18n } = useTranslation();
  const toggle = () => i18n.changeLanguage(i18n.language === 'ar' ? 'en' : 'ar');

  return (
    <button type="button" className={`headerControl ${className}`} aria-label={t('language.switchTo')} title={t('language.switchTo')} onClick={toggle}>
      <Globe className="headerGlobe" size={17} aria-hidden="true" />
      <span>{t('language.switchTo')}</span>
    </button>
  );
}
