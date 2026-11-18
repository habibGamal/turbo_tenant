import React, { useState } from "react";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetTrigger } from "@/components/ui/sheet";
import {
    Menu,
    Home,
    ScrollText,
    MapPin,
    Phone,
    Mail,
    Globe,
} from "lucide-react";
import { PLATFORM_ICONS } from "@/themes/default/components/icons/social-icons";
import { useTranslation } from "react-i18next";

interface FooterLink {
    label: string;
    labelAr?: string;
    href: string;
}

interface FooterSection {
    title: string;
    titleAr?: string;
    links: FooterLink[];
}

interface MobileMenuProps {
    categories?: Array<{ id: number; name: string; name_ar?: string }>;
    footerSections: FooterSection[];
    settings?: any;
}

export function MobileMenu({
    categories = [],
    footerSections,
    settings,
}: MobileMenuProps) {
    const { t, i18n } = useTranslation();
    const [open, setOpen] = useState(false);
    const drawerSide = i18n.dir() === "rtl" ? "left" : "right";

    const getText = (text: string, textAr?: string) => {
        return i18n.language === "ar" && textAr ? textAr : text;
    };

    const siteTitle = (
        i18n.language === "ar" && settings?.site_name_ar
            ? settings.site_name_ar
            : (settings?.site_name || t("home"))
    ).trim();

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild className="md:hidden">
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t("openMenu", "Open menu")}
                >
                    <Menu className="h-5 w-5" />
                </Button>
            </SheetTrigger>
            <SheetContent side={drawerSide} className="w-[300px] overflow-y-auto">
                <nav className="flex flex-col gap-4 mt-8">
                    <Link
                        to="/"
                        onClick={() => setOpen(false)}
                        className="flex items-center gap-2 text-lg font-medium hover:text-primary transition-colors min-h-11"
                    >
                        <Home className="h-5 w-5" />
                        {t("home")}
                    </Link>
                    <Link
                        to="/menu"
                        onClick={() => setOpen(false)}
                        className="flex items-center gap-2 text-lg font-medium hover:text-primary transition-colors min-h-11"
                    >
                        <ScrollText className="h-5 w-5" />
                        {t("menu")}
                    </Link>

                    {categories.length > 0 && (
                        <>
                            <div className="my-2 border-t" />
                            <p className="text-sm font-semibold text-muted-foreground">
                                {t("categories")}
                            </p>
                            {categories.slice(0, 5).map((category) => (
                                <Link
                                    key={category.id}
                                    to={`/menu?category_ids[]=${category.id}`}
                                    onClick={() => setOpen(false)}
                                    className="ps-4 text-sm hover:text-primary transition-colors py-1.5"
                                >
                                    {getText(category.name, category.name_ar)}
                                </Link>
                            ))}
                        </>
                    )}
                </nav>

                <div className="my-4 border-t" />

                {/* Footer Content in Mobile Menu */}
                <div className="space-y-6 pb-8 px-1">
                    {footerSections.map((section, index) => (
                        <div key={index}>
                            <h4 className="font-semibold text-sm mb-2 text-foreground">
                                {getText(section.title, section.titleAr)}
                            </h4>
                            <ul className="space-y-2">
                                {section.links.map((link, linkIndex) => (
                                    <li key={linkIndex}>
                                        <Link
                                            to={link.href}
                                            onClick={() => setOpen(false)}
                                            className="inline-flex items-center min-h-9 text-sm text-muted-foreground hover:text-primary transition-colors"
                                        >
                                            {getText(link.label, link.labelAr)}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}

                    {/* Contact Info */}
                    <div>
                        <h4 className="font-semibold text-sm mb-2 text-foreground">
                            {t("contactUs")}
                        </h4>
                        <ul className="space-y-2 text-sm text-muted-foreground">
                            {settings?.contact_address && (
                                <li className="flex items-start gap-2">
                                    <MapPin className="h-4 w-4 shrink-0 mt-0.5" />
                                    <span dir="auto">{settings.contact_address}</span>
                                </li>
                            )}
                            {settings?.contact_phone && (
                                <li className="flex items-center gap-2">
                                    <Phone className="h-4 w-4 shrink-0" />
                                    <a
                                        href={`tel:${settings.contact_phone}`}
                                        dir="ltr"
                                        className="hover:text-foreground inline-flex items-center min-h-9"
                                    >
                                        {settings.contact_phone}
                                    </a>
                                </li>
                            )}
                            {settings?.contact_email && (
                                <li className="flex items-center gap-2">
                                    <Mail className="h-4 w-4 shrink-0" />
                                    <a
                                        href={`mailto:${settings.contact_email}`}
                                        dir="ltr"
                                        className="hover:text-foreground inline-flex items-center min-h-9 break-all"
                                    >
                                        {settings.contact_email}
                                    </a>
                                </li>
                            )}
                        </ul>
                    </div>

                    {/* Social Links */}
                    <div className="flex flex-wrap gap-2">
                        {(settings?.social_links ?? []).map((social: { platform: string; url: string }, idx: number) => {
                            const Icon = PLATFORM_ICONS[social.platform] ?? Globe;
                            return (
                                <Button
                                    key={idx}
                                    variant="outline"
                                    size="icon"
                                    asChild
                                >
                                    <a
                                        href={social.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label={t(`socialPlatform.${social.platform}`, social.platform)}
                                    >
                                        <Icon className="h-4 w-4" />
                                    </a>
                                </Button>
                            );
                        })}
                    </div>

                    {/* Copyright */}
                    <p className="text-xs text-muted-foreground">
                        © {new Date().getFullYear()} {siteTitle}. {t("allRightsReserved").replace(/\.+$/, "")}.
                    </p>
                </div>
            </SheetContent>
        </Sheet>
    );
}
