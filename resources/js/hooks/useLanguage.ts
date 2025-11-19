import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';

export function useLanguage() {
    const { i18n } = useTranslation();

    useEffect(() => {
        // Set the document direction based on the current language
        const isRTL = i18n.language === 'ar';
        document.documentElement.dir = isRTL ? 'rtl' : 'ltr';
        document.documentElement.lang = i18n.language;
    }, [i18n.language]);

    const changeLanguage = (lng: string) => {
        i18n.changeLanguage(lng);
    };

    return {
        currentLanguage: i18n.language,
        changeLanguage,
        isRTL: i18n.language === 'ar',
    };
}
