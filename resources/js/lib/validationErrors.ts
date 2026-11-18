import i18n from "@/i18n";

const ARABIC_ERROR_MAP: Record<string, string> = {
    // Required
    "The email field is required.": "حقل البريد الإلكتروني مطلوب.",
    "The password field is required.": "حقل كلمة المرور مطلوب.",
    "The name field is required.": "حقل الاسم مطلوب.",
    "The phone field is required.": "حقل الهاتف مطلوب.",
    "The phone number field is required.": "حقل رقم الهاتف مطلوب.",

    // Format / Invalid
    "The email must be a valid email address.": "يجب أن يكون البريد الإلكتروني صالحاً.",
    "These credentials do not match our records.": "بيانات الاعتماد هذه غير متطابقة مع سجلاتنا.",
    "The selected email is invalid.": "البريد الإلكتروني المحدد غير صالح.",
    "We can't find a user with that email address.": "لم نتمكن من العثور على مستخدم بعنوان البريد الإلكتروني هذا.",
    "This password reset token is invalid.": "رمز إعادة تعيين كلمة المرور هذا غير صالح.",
    "The email has already been taken.": "البريد الإلكتروني مستخدم بالفعل.",
    "The password confirmation does not match.": "تأكيد كلمة المرور غير متطابق.",
    "The password must be at least 8 characters.": "يجب أن تتكون كلمة المرور من 8 أحرف على الأقل.",
    "Too Many Attempts.": "محاولات كثيرة جداً. يرجى الانتظار قليلاً ثم المحاولة مرة أخرى.",
    "Invalid coupon code": "رمز الكوبون غير صالح.",
    "This coupon has expired": "انتهت صلاحية هذا الكوبون.",
    "Coupon usage limit reached": "تم الوصول إلى الحد الأقصى لاستخدام هذا الكوبون.",
    "This coupon is not valid at this time": "هذا الكوبون غير صالح في هذا الوقت.",
};

export function localizeErrorMessage(msg: string): string {
    if (!msg) return "";
    if (/The (POST|GET|PUT|PATCH|DELETE) method is not supported for route/i.test(msg)) {
        return i18n.t("somethingWentWrong", "An error occurred. Please try again.");
    }
    const currentLang = i18n.language || (typeof document !== "undefined" ? document.documentElement.lang : "ar");
    if (currentLang?.startsWith("ar")) {
        if (ARABIC_ERROR_MAP[msg]) {
            return ARABIC_ERROR_MAP[msg];
        }
        // Dynamic matching for "The :field field is required."
        const requiredMatch = msg.match(/^The (.+) field is required\.$/i);
        if (requiredMatch) {
            const field = requiredMatch[1].toLowerCase();
            const fieldNames: Record<string, string> = {
                email: "البريد الإلكتروني",
                password: "كلمة المرور",
                name: "الاسم",
                phone: "الهاتف",
                "phone number": "رقم الهاتف",
                street: "الشارع",
                building: "المبنى",
                area: "المنطقة",
            };
            const translatedField = fieldNames[field] || field;
            return `حقل ${translatedField} مطلوب.`;
        }
    }
    return msg;
}

export function localizeErrors(errors: Record<string, any>): Record<string, string> {
    const result: Record<string, string> = {};
    for (const [key, value] of Object.entries(errors)) {
        const rawMsg = Array.isArray(value) ? value[0] : String(value);
        result[key] = localizeErrorMessage(rawMsg);
    }
    return result;
}
