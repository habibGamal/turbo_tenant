ensure all translation keys in the provided pages are exists in resources\js\locales\ar\translation.json and resources\js\locales\en\translation.json. If any key is missing, add it in both files and if you find any hardcoded text in the pages that should be translated, create a new key for it in both files.
and use t("key")
dont use : i18n.language === 'ar' ? 'رائج' : 'Hot'
