import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useCmsPages, useCmsPage, CmsPageItem } from '@/plugins/cms-pages';
import { CmsPageSkeleton } from '@/components/ui/page-skeletons';

export interface ThemedCmsPagesProps {
  slug?: string;
  className?: string;
}

export default function ThemedCmsPages({ slug, className = '' }: ThemedCmsPagesProps) {
  const { i18n } = useTranslation();
  const isAr = i18n.language === 'ar';

  if (slug) {
    return <ThemedSinglePage slug={slug} isAr={isAr} className={className} />;
  }

  return <ThemedPageLinks isAr={isAr} className={className} />;
}

function ThemedSinglePage({ slug, isAr, className }: { slug: string; isAr: boolean; className: string }) {
  const { data, isLoading, error } = useCmsPage(slug);

  if (isLoading) {
    return <CmsPageSkeleton />;
  }

  if (error || !data?.data) {
    return null;
  }

  const page = data.data;
  const title = isAr && page.title_ar ? page.title_ar : page.title;
  const content = isAr && page.content_ar ? page.content_ar : page.content;

  return (
    <div data-testid="cms-page-container" className={`themed-cms-page ${className}`}>
      {/* Hero Section with Gradient */}
      <div className="relative overflow-hidden bg-gradient-to-br from-primary/5 via-background to-background border-b">
        <div className="container relative mx-auto px-4 py-16 md:py-24 text-center">
          <h1 data-testid="cms-page-title" className="text-4xl md:text-5xl font-bold tracking-tight mb-4">
            {title}
          </h1>
          <div className="w-20 h-1 bg-gradient-to-r from-primary/50 via-primary to-primary/50 mx-auto rounded-full" />
        </div>
      </div>

      {/* Main Content */}
      <div className="container mx-auto px-4 py-12 md:py-16">
        <div className="max-w-4xl mx-auto">
          <article className="bg-card rounded-2xl shadow-sm border p-6 md:p-10 lg:p-12">
            {content && (
              <div
                data-testid="cms-page-content"
                className="prose prose-slate dark:prose-invert max-w-none
                  prose-headings:font-bold prose-headings:tracking-tight
                  prose-h2:text-3xl prose-h2:mt-8 prose-h2:mb-4
                  prose-p:text-base prose-p:leading-relaxed prose-p:mb-4
                  prose-a:text-primary hover:prose-a:underline"
                dangerouslySetInnerHTML={{ __html: content }}
              />
            )}
          </article>
        </div>
      </div>
    </div>
  );
}

function ThemedPageLinks({ isAr, className }: { isAr: boolean; className: string }) {
  const { data, isLoading, error } = useCmsPages();

  if (isLoading || error || !data?.data || data.data.length === 0) {
    return null;
  }

  return (
    <div className={`themed-cms-links border-t bg-muted/20 py-8 ${className}`}>
      <div className="container mx-auto px-4">
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-muted-foreground">
          <div className="font-medium text-foreground">
            {isAr ? 'روابط هامة' : 'Important Links'}
          </div>
          <div className="flex flex-wrap gap-6">
            {data.data.map((page: CmsPageItem) => {
              const title = isAr && page.title_ar ? page.title_ar : page.title;
              return (
                <Link
                  key={page.id}
                  to={`/pages/${page.slug}`}
                  className="hover:text-primary transition-colors duration-200"
                >
                  {title}
                </Link>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
