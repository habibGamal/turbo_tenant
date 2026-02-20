import React from 'react';
import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    ScrollText,
    Mail,
    Phone,
    MapPin,
    Globe,
} from 'lucide-react';
import { PLATFORM_ICONS } from '@/themes/default/components/icons/social-icons';
import { useTranslation } from 'react-i18next';

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

interface FooterProps {
    sections?: FooterSection[];
    showNewsletter?: boolean;
}

export default function Footer({ sections, showNewsletter = true }: FooterProps) {
    const { t, i18n } = useTranslation();

    const { settings } = usePage<any>().props;
    const pages = settings?.pages || [];

    const defaultSections: FooterSection[] = [
        {
            title: t('quickLinks'),
            titleAr: t('quickLinks'),
            links: [
                { label: t('home'), labelAr: t('home'), href: '/' },
                { label: t('menu'), labelAr: t('menu'), href: '/menu' },
                { label: t('about'), labelAr: t('about'), href: '/pages/about-us' },
                { label: t('contact'), labelAr: t('contact'), href: '/pages/contact-us' },
            ],
        },
        {
            title: t('support'),
            titleAr: t('support'),
            links: [
                { label: t('deliveryInfo'), labelAr: t('deliveryInfo'), href: '/pages/delivery-policy' },
                { label: t('trackOrder'), labelAr: t('trackOrder'), href: '/orders' },
                { label: t('returns'), labelAr: t('returns'), href: '/pages/return-policy' },
            ],
        },
        {
            title: t('legal'),
            titleAr: t('legal'),
            links: pages.map((page: any) => ({
                label: page.title,
                labelAr: page.title_ar || page.title,
                href: `/pages/${page.slug}`,
            })),
        },
    ];

    const sectionsToUse = sections || defaultSections;

    const getText = (text: string, textAr?: string) => {
        return i18n.language === 'ar' && textAr ? textAr : text;
    };

    const handleNewsletterSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Handle newsletter subscription
    };

    return (
        <footer className="hidden md:block border-t bg-muted/30 mt-20">
            {/* Newsletter Section */}


            {/* Main Footer Content */}
            <div className="container mx-auto px-4 py-12">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8">
                    {/* Brand Section */}
                    <div className="lg:col-span-4 space-y-4">
                        <Link href="/" className="flex items-center gap-2">
                            {settings?.site_logo ? (
                                <img
                                    src={settings.site_logo}
                                    alt={i18n.language === 'ar' && settings?.site_name_ar ? settings.site_name_ar : (settings?.site_name || t("home"))}
                                    className="h-10 w-auto object-contain"
                                />
                            ) : (
                                <div className="h-10 w-10 rounded-lg bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                                    <ScrollText className="h-6 w-6 text-primary-foreground" />
                                </div>
                            )}
                            <span className="text-xl font-bold">{i18n.language === 'ar' && settings?.site_name_ar ? settings.site_name_ar : (settings?.site_name || t('home'))}</span>
                        </Link>
                        <p className="text-muted-foreground">
                            {settings?.site_description || t('deliveringDeliciousFood')}
                        </p>

                        {/* Social Links */}
                        <div className="flex flex-wrap gap-2">
                            {(settings?.social_links ?? []).map((social: { platform: string; url: string }, idx: number) => {
                                const Icon = PLATFORM_ICONS[social.platform] ?? Globe;
                                return (
                                    <Button key={idx} variant="outline" size="icon" asChild>
                                        <a href={social.url} target="_blank" rel="noopener noreferrer" aria-label={social.platform}>
                                            <Icon className="h-4 w-4" />
                                        </a>
                                    </Button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Links Sections */}
                    {sectionsToUse.map((section, index) => (
                        <div key={index} className="lg:col-span-2 md:col-span-1">
                            <h4 className="font-semibold text-lg mb-4">
                                {getText(section.title, section.titleAr)}
                            </h4>
                            <ul className="space-y-3">
                                {section.links.map((link, linkIndex) => (
                                    <li key={linkIndex}>
                                        <Link
                                            href={link.href}
                                            className="text-muted-foreground hover:text-foreground transition-colors"
                                        >
                                            {getText(link.label, link.labelAr)}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}

                    {/* Contact Info */}
                    <div className="lg:col-span-2 md:col-span-1">
                        <h4 className="font-semibold text-lg mb-4">
                            {t('contactUs')}
                        </h4>
                        <ul className="space-y-3 text-muted-foreground">
                            {settings?.contact_address && (
                                <li className="flex items-start gap-2">
                                    <MapPin className="h-5 w-5 shrink-0 mt-0.5" />
                                    <span>{settings.contact_address}</span>
                                </li>
                            )}
                            {settings?.contact_phone && (
                                <li className="flex items-center gap-2">
                                    <Phone className="h-5 w-5 shrink-0" />
                                    <a href={`tel:${settings.contact_phone}`} className="hover:text-foreground transition-colors">
                                        {settings.contact_phone}
                                    </a>
                                </li>
                            )}
                            {settings?.contact_email && (
                                <li className="flex items-center gap-2">
                                    <Mail className="h-5 w-5 shrink-0" />
                                    <a href={`mailto:${settings.contact_email}`} className="hover:text-foreground transition-colors">
                                        {settings.contact_email}
                                    </a>
                                </li>
                            )}
                        </ul>
                    </div>
                </div>

                <Separator className="my-8" />

                {/* Bottom Bar */}
                <div className="flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-muted-foreground">
                    <p>
                        © {new Date().getFullYear()} {i18n.language === 'ar' && settings?.site_name_ar ? settings.site_name_ar : (settings?.site_name || t('home'))}. {t('allRightsReserved')}.
                    </p>
                </div>
            </div>

            {/* Mobile Bottom Nav Spacer */}
            <div className="h-20 md:hidden" aria-hidden="true" />
        </footer>
    );
}
