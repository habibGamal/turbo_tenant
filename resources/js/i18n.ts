import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import LanguageDetector from 'i18next-browser-languagedetector';

// Import translation files
import translationAR from './locales/ar/translation.json';
import translationEN from './locales/en/translation.json';

// Translation resources
const resources = {
    ar: {
        translation: translationAR,
    },
    en: {
        translation: translationEN,
    },
};

i18n
    // Detect user language
    .use(LanguageDetector)
    // Pass the i18n instance to react-i18next
    .use(initReactI18next)
    // Initialize i18next
    .init({
        resources,
        fallbackLng: 'ar', // Fallback when translation key is missing
        supportedLngs: ['ar', 'en'], // Supported languages
        debug: false,
        interpolation: {
            escapeValue: false, // React already escapes values
        },
        detection: {
            // Only check localStorage and cookie, not browser navigator
            // This ensures first-time visitors get fallbackLng (Arabic)
            order: ['localStorage', 'cookie'],
            caches: ['localStorage', 'cookie'],
        },
    });

export default i18n;
