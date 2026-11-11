import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

/**
 * أي ملف JSON جديد داخل locales/<lang>/<namespace>.json يُحمَّل تلقائياً.
 * مثال: locales/ar/supervisor.json  ->  namespace اسمه "supervisor".
 */
const files = import.meta.glob('./locales/*/*.json', { eager: true });
const resources = {};
for (const [path, mod] of Object.entries(files)) {
  const [, lng, ns] = path.match(/\.\/locales\/([^/]+)\/([^/]+)\.json$/);
  (resources[lng] ??= {})[ns] = mod.default;
}

export const LANGUAGES = {
  ar: { label: 'العربية', dir: 'rtl' },
  en: { label: 'English', dir: 'ltr' },
};

const STORAGE_KEY = 'syriatel-tms:lang';
const stored = localStorage.getItem(STORAGE_KEY);
const initialLng = stored in LANGUAGES ? stored : 'ar';

i18n.use(initReactI18next).init({
  resources,
  lng: initialLng,
  fallbackLng: 'ar',
  defaultNS: 'common',
  ns: Object.keys(resources.ar),
  interpolation: { escapeValue: false },
});

function applyDocumentLanguage(lng) {
  document.documentElement.lang = lng;
  document.documentElement.dir = LANGUAGES[lng]?.dir ?? 'rtl';
  localStorage.setItem(STORAGE_KEY, lng);
}

applyDocumentLanguage(initialLng);
i18n.on('languageChanged', applyDocumentLanguage);

export default i18n;
