import React from 'react';
import { useTranslation } from 'react-i18next';
import { useCmsPages, useCmsPage } from '../api/useCmsPages';
import { CmsPageItem } from '../types';

export interface DefaultCmsPagesProps {
  slug?: string;
  className?: string;
}

export default function DefaultCmsPages({ slug, className = '' }: DefaultCmsPagesProps) {
  const { i18n } = useTranslation();
  const isAr = i18n.language === 'ar';

  // If a specific slug is requested, render that page's content
  if (slug) {
    return <SinglePageFallback slug={slug} isAr={isAr} className={className} />;
  }

  // Otherwise, render list of links to active CMS pages
  return <PageListFallback isAr={isAr} className={className} />;
}

function SinglePageFallback({ slug, isAr, className }: { slug: string; isAr: boolean; className: string }) {
  const { data: page, isLoading, error } = useCmsPage(slug);

  if (isLoading) {
    return (
      <div className={`py-6 text-center text-sm text-muted-foreground animate-pulse ${className}`}>
        {isAr ? 'جاري التحميل...' : 'Loading page...'}
      </div>
    );
  }

  if (error || !page) {
    return null;
  }

  const title = isAr && page.title_ar ? page.title_ar : page.title;
  const content = isAr && page.content_ar ? page.content_ar : page.content;

  return (
    <article className={`w-full my-6 p-6 border rounded-lg bg-card text-card-foreground ${className}`}>
      <h1 className="text-2xl font-bold tracking-tight mb-4">{title}</h1>
      {content && (
        <div
          className="prose dark:prose-invert max-w-none text-muted-foreground"
          dangerouslySetInnerHTML={{ __html: content }}
        />
      )}
    </article>
  );
}

function PageListFallback({ isAr, className }: { isAr: boolean; className: string }) {
  const { data: pages, isLoading, error } = useCmsPages();

  if (isLoading) {
    return (
      <div className={`py-4 text-center text-sm text-muted-foreground animate-pulse ${className}`}>
        {isAr ? 'جاري تحميل الصفحات...' : 'Loading pages...'}
      </div>
    );
  }

  if (error || !pages || pages.length === 0) {
    return null;
  }

  return (
    <div className={`w-full my-4 p-4 border rounded-lg bg-card text-card-foreground ${className}`}>
      <h3 className="font-semibold text-base mb-3">
        {isAr ? 'الصفحات والمعلومات' : 'Pages & Policies'}
      </h3>
      <ul className="flex flex-wrap gap-4 text-sm">
        {pages.map((page: CmsPageItem) => {
          const title = isAr && page.title_ar ? page.title_ar : page.title;
          return (
            <li key={page.id}>
              <a
                href={`/pages/${page.slug}`}
                className="text-primary hover:underline underline-offset-4"
              >
                {title}
              </a>
            </li>
          );
        })}
      </ul>
    </div>
  );
}
