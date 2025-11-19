import React from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import {
    UtensilsCrossed,
    Mail,
    Phone,
    MapPin,
    Facebook,
    Instagram,
    Twitter
} from 'lucide-react';
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

    const defaultSections: FooterSection[] = [
        {
            title: t('quickLinks'),
            titleAr: t('quickLinks'),
            links: [
                { label: t('home'), labelAr: t('home'), href: '/' },
                { label: t('menu'), labelAr: t('menu'), href: '/menu' },
                { label: t('about'), labelAr: t('about'), href: '/about' },
                { label: t('contact'), labelAr: t('contact'), href: '/contact' },
            ],
        },
        {
            title: t('support'),
            titleAr: t('support'),
            links: [
                { label: t('faq'), labelAr: t('faq'), href: '/faq' },
                { label: t('deliveryInfo'), labelAr: t('deliveryInfo'), href: '/delivery' },
                { label: t('trackOrder'), labelAr: t('trackOrder'), href: '/orders' },
                { label: t('returns'), labelAr: t('returns'), href: '/returns' },
            ],
        },
        {
            title: t('legal'),
            titleAr: t('legal'),
            links: [
                { label: t('privacyPolicy'), labelAr: t('privacyPolicy'), href: '/privacy' },
                { label: t('termsOfService'), labelAr: t('termsOfService'), href: '/terms' },
                { label: t('cookiePolicy'), labelAr: t('cookiePolicy'), href: '/cookies' },
            ],
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
        <footer className="border-t bg-muted/30 mt-20">
            {/* Newsletter Section */}
            {showNewsletter && (
                <div className="border-b">
                    <div className="container mx-auto px-4 py-12">
                        <div className="max-w-2xl mx-auto text-center space-y-4">
                            <h3 className="text-2xl md:text-3xl font-bold">
                                {t('subscribeNewsletter')}
                            </h3>
                            <p className="text-muted-foreground">
                                {t('getLatestOffers')}
                            </p>
                            <form onSubmit={handleNewsletterSubmit} className="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
                                <Input
                                    type="email"
                                    placeholder={t('yourEmail')}
                                    className="flex-1"
                                    required
                                />
                                <Button type="submit" className="sm:w-auto">
                                    {t('subscribe')}
                                </Button>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* Main Footer Content */}
            <div className="container mx-auto px-4 py-12">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8">
                    {/* Brand Section */}
                    <div className="lg:col-span-4 space-y-4">
                        <Link href="/" className="flex items-center gap-2">
                            <div className="h-10 w-10 rounded-lg bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center">
                                <UtensilsCrossed className="h-6 w-6 text-primary-foreground" />
                            </div>
                            <span className="text-xl font-bold">{t('home')}</span>
                        </Link>
                        <p className="text-muted-foreground">
                            {t('deliveringDeliciousFood')}
                        </p>

                        {/* Social Links */}
                        <div className="flex gap-2">
                            <Button variant="outline" size="icon" asChild>
                                <a href="https://facebook.com" target="_blank" rel="noopener noreferrer">
                                    <Facebook className="h-4 w-4" />
                                </a>
                            </Button>
                            <Button variant="outline" size="icon" asChild>
                                <a href="https://instagram.com" target="_blank" rel="noopener noreferrer">
                                    <Instagram className="h-4 w-4" />
                                </a>
                            </Button>
                            <Button variant="outline" size="icon" asChild>
                                <a href="https://twitter.com" target="_blank" rel="noopener noreferrer">
                                    <Twitter className="h-4 w-4" />
                                </a>
                            </Button>
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
                            <li className="flex items-start gap-2">
                                <MapPin className="h-5 w-5 shrink-0 mt-0.5" />
                                <span>123 Restaurant St, City, State 12345</span>
                            </li>
                            <li className="flex items-center gap-2">
                                <Phone className="h-5 w-5 shrink-0" />
                                <a href="tel:+15551234567" className="hover:text-foreground transition-colors">
                                    +1 (555) 123-4567
                                </a>
                            </li>
                            <li className="flex items-center gap-2">
                                <Mail className="h-5 w-5 shrink-0" />
                                <a href="mailto:info@restaurant.com" className="hover:text-foreground transition-colors">
                                    info@restaurant.com
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <Separator className="my-8" />

                {/* Bottom Bar */}
                <div className="flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-muted-foreground">
                    <p>
                        © {new Date().getFullYear()} {t('home')}. {t('allRightsReserved')}.
                    </p>
                    <div className="flex gap-6">
                        <Link href="/privacy" className="hover:text-foreground transition-colors">
                            {t('privacy')}
                        </Link>
                        <Link href="/terms" className="hover:text-foreground transition-colors">
                            {t('terms')}
                        </Link>
                        <Link href="/cookies" className="hover:text-foreground transition-colors">
                            {t('cookies')}
                        </Link>
                    </div>
                </div>
            </div>

            {/* Mobile Bottom Nav Spacer */}
            <div className="h-20 md:hidden" aria-hidden="true" />
        </footer>
    );
}
