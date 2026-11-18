import { useLocation } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { useTenantSafe } from "@/core/context/TenantContext";
import { cn } from "@/lib/utils";

const HIDDEN_ROUTE_PREFIXES = [
    "/checkout",
    "/login",
    "/register",
    "/forgot-password",
    "/reset-password",
];

function resolveWhatsAppUrl(
    whatsappNumber?: string | null,
    socialLinks?: Array<{ platform: string; url: string }>
): string | null {
    const raw = whatsappNumber?.trim();
    if (raw) {
        if (/^https?:\/\//i.test(raw)) {
            return raw;
        }
        let digits = raw.replace(/\D/g, "");
        if (digits.startsWith("00")) {
            digits = digits.slice(2);
        }
        if (/^01\d{9}$/.test(digits)) {
            digits = `20${digits.slice(1)}`;
        }
        if (digits.length >= 8) {
            return `https://wa.me/${digits}`;
        }
    }

    const socialEntry = socialLinks?.find(
        (item) =>
            item?.platform === "whatsapp" &&
            typeof item?.url === "string" &&
            item.url.trim() !== ""
    );

    return socialEntry ? socialEntry.url.trim() : null;
}

/**
 * Floating WhatsApp contact button shown on browsing routes when configured.
 */
export function FloatingWhatsAppButton() {
    const { t } = useTranslation();
    const { pathname } = useLocation();
    const tenant = useTenantSafe();
    const settings = tenant?.settings || {};

    if (HIDDEN_ROUTE_PREFIXES.some((prefix) => pathname.startsWith(prefix))) {
        return null;
    }

    const whatsappUrl = resolveWhatsAppUrl(
        settings.whatsapp_number,
        settings.social_links
    );
    if (!whatsappUrl) {
        return null;
    }

    const isProductPage = pathname.startsWith("/products/");

    return (
        <a
            href={whatsappUrl}
            target="_blank"
            rel="noopener noreferrer"
            data-testid="floating-whatsapp-button"
            aria-label={t("chatOnWhatsApp", "Chat with us on WhatsApp")}
            title={t("chatOnWhatsApp", "Chat with us on WhatsApp")}
            className={cn(
                "fixed end-4 md:end-6 z-30 flex h-12 w-12 md:h-14 md:w-14 items-center justify-center rounded-full bg-[#25D366] hover:bg-[#20bd5a] text-white shadow-lg transition-transform hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#25D366] focus-visible:ring-offset-2",
                isProductPage
                    ? "bottom-[calc(8.5rem+env(safe-area-inset-bottom))] md:bottom-6"
                    : "bottom-[calc(4.5rem+env(safe-area-inset-bottom))] md:bottom-6"
            )}
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="currentColor"
                className="h-6 w-6 md:h-7 md:w-7"
                aria-hidden="true"
            >
                <path d="M12.031 2c-5.516 0-9.969 4.453-9.969 9.969 0 1.766.469 3.484 1.344 5l-1.406 5.156 5.281-1.375c1.469.797 3.109 1.219 4.75 1.219 5.516 0 9.969-4.453 9.969-9.969s-4.453-10-9.969-10zm5.813 14.125c-.25.703-1.438 1.344-2 1.422-.516.078-1.172.109-1.891-.125-.438-.141-1-.328-1.719-.641-3.031-1.313-5-4.375-5.156-4.578-.156-.203-1.234-1.641-1.234-3.125s.781-2.219 1.063-2.516c.281-.297.609-.375.813-.375s.406 0 .578.016c.188.016.438-.078.688.531.25.609.859 2.094.938 2.25.078.156.125.344.031.547-.094.203-.141.328-.297.5-.156.172-.313.391-.453.516-.156.156-.313.328-.141.625.172.297.766 1.266 1.656 2.047 1.141 1.016 2.094 1.328 2.406 1.484.297.156.484.125.656-.078.172-.203.75-.875.953-1.172.203-.297.406-.25.672-.156.281.094 1.75.828 2.047.984.297.156.5.219.578.344.078.125.078.734-.172 1.438z" />
            </svg>
        </a>
    );
}

export default FloatingWhatsAppButton;
