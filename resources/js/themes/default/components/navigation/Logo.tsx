import React from "react";
import { Link } from "react-router-dom";
import { ScrollText } from "lucide-react";
import { useTranslation } from "react-i18next";
import { ImageWithFallback } from "@/components/ui/image";

interface LogoProps {
    siteLogo?: string;
    siteName?: string;
    siteNameAr?: string;
}

export function Logo({ siteLogo, siteName, siteNameAr }: LogoProps) {
    const { t, i18n } = useTranslation();

    const displayName = i18n.language === 'ar' && siteNameAr ? siteNameAr : siteName;

    return (
        <Link to="/" className="flex items-center gap-2 shrink-0">
            {siteLogo ? (
                <ImageWithFallback
                    src={siteLogo}
                    alt={displayName || t("home")}
                    className="h-12 w-auto object-contain rounded"
                    forceWidth={480}
                    priority={true}
                />
            ) : (
                <div className="h-10 w-10 rounded-lg bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                    <ScrollText className="h-6 w-6 text-primary-foreground" />
                </div>
            )}
            <span className="text-xl font-bold hidden sm:inline">
                {displayName || t("home")}
            </span>
        </Link>
    );
}
