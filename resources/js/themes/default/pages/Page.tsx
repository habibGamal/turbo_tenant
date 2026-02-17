import React from 'react';
import { Head } from '@inertiajs/react';

interface PageProps {
    page: {
        title: string;
        content: string;
        title_ar?: string;
        content_ar?: string;
    };
}

import MainLayout from '@/themes/default/layouts/MainLayout';
import { useTranslation } from 'react-i18next';

export default function Page({ page }: PageProps) {
    const { t, i18n } = useTranslation();
    const title = i18n.language === 'ar' && page.title_ar ? page.title_ar : page.title;
    const content = i18n.language === 'ar' && page.content_ar ? page.content_ar : page.content;

    return (
        <MainLayout>
            <Head title={title} />

            {/* Hero Section with Gradient */}
            <div className="relative overflow-hidden bg-gradient-to-br from-primary/5 via-background to-background border-b">
                {/* Decorative Elements */}
                <div className="absolute inset-0 bg-grid-pattern opacity-[0.02]" />
                <div className="absolute top-0 right-0 w-96 h-96 bg-primary/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2" />
                <div className="absolute bottom-0 left-0 w-96 h-96 bg-primary/5 rounded-full blur-3xl translate-y-1/2 -translate-x-1/2" />

                <div className="container relative mx-auto px-4 py-16 md:py-24">
                    <div className="max-w-4xl mx-auto text-center">
                        <h1 className="text-4xl md:text-5xl lg:text-6xl font-bold tracking-tight mb-4">
                            {title}
                        </h1>
                        <div className="w-20 h-1 bg-gradient-to-r from-primary/50 via-primary to-primary/50 mx-auto rounded-full" />
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <div className="container mx-auto px-4 py-12 md:py-16">
                <div className="max-w-4xl mx-auto">
                    {/* Content Card */}
                    <article className="bg-card rounded-2xl shadow-sm border overflow-hidden transition-shadow hover:shadow-md">
                        {/* Content Body */}
                        <div className="p-6 md:p-10 lg:p-12">
                            <div
                                className="prose prose-slate dark:prose-invert max-w-none
                                    prose-headings:font-bold prose-headings:tracking-tight
                                    prose-h2:text-3xl prose-h2:mt-12 prose-h2:mb-6 prose-h2:pb-3 prose-h2:border-b prose-h2:border-border
                                    prose-h3:text-2xl prose-h3:mt-8 prose-h3:mb-4
                                    prose-h4:text-xl prose-h4:mt-6 prose-h4:mb-3
                                    prose-p:text-base prose-p:leading-relaxed prose-p:mb-4
                                    prose-a:text-primary prose-a:no-underline prose-a:font-medium hover:prose-a:underline
                                    prose-strong:text-foreground prose-strong:font-semibold
                                    prose-ul:my-6 prose-ul:space-y-2
                                    prose-ol:my-6 prose-ol:space-y-2
                                    prose-li:text-base prose-li:leading-relaxed
                                    prose-blockquote:border-l-4 prose-blockquote:border-primary/30 prose-blockquote:pl-6 prose-blockquote:italic prose-blockquote:text-muted-foreground
                                    prose-code:text-sm prose-code:bg-muted prose-code:px-1.5 prose-code:py-0.5 prose-code:rounded prose-code:font-mono prose-code:before:content-none prose-code:after:content-none
                                    prose-pre:bg-muted prose-pre:border prose-pre:border-border
                                    prose-img:rounded-lg prose-img:shadow-md
                                    prose-hr:border-border prose-hr:my-8"
                                dangerouslySetInnerHTML={{ __html: content }}
                            />
                        </div>

                        {/* Bottom Accent */}
                        <div className="h-1 bg-gradient-to-r from-transparent via-primary/20 to-transparent" />
                    </article>

                    {/* Optional: Back to Top Button for long content */}
                    {/* <div className="mt-8 text-center">
                        <button
                            onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
                            className="inline-flex items-center gap-2 px-6 py-3 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors rounded-lg hover:bg-muted/50"
                            aria-label={t("back_to_top")}
                        >
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" />
                            </svg>
                            {t("back_to_top")}
                        </button>
                    </div> */}
                </div>
            </div>

            {/* Background Grid Pattern CSS (inline for simplicity) */}
            <style>{`
                .bg-grid-pattern {
                    background-image:
                        linear-gradient(to right, currentColor 1px, transparent 1px),
                        linear-gradient(to bottom, currentColor 1px, transparent 1px);
                    background-size: 4rem 4rem;
                }
            `}</style>
        </MainLayout>
    );
}
