import React from "react";
import { Link } from "@inertiajs/react";
import { ScrollText } from "lucide-react";
import { useTranslation } from "react-i18next";

interface LogoProps {
    siteLogo?: string;
    siteName?: string;
}

export function Logo({ siteLogo, siteName }: LogoProps) {
    const { t } = useTranslation();

    return (
        <Link href="/" className="flex items-center gap-2 shrink-0">
            {siteLogo ? (
                <img
                    src={siteLogo}
                    alt={siteName || t("home")}
                    className="h-12 w-auto object-contain rounded"
                />
            ) : (
                <div className="h-10 w-10 rounded-lg bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                    <ScrollText className="h-6 w-6 text-primary-foreground" />
                </div>
            )}
            <span className="text-xl font-bold hidden sm:inline">
                {siteName || t("home")}
            </span>
        </Link>
    );
}
