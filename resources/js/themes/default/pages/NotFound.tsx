import React from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { SearchX } from 'lucide-react';
import MainLayout from '@/themes/default/layouts/MainLayout';
import { Head } from '@/components/Head';
import { Button } from '@/components/ui/button';

export interface NotFoundProps {
  embedded?: boolean;
}

export default function NotFound({ embedded = false }: NotFoundProps) {
  const { t } = useTranslation();

  const content = (
    <div
      data-testid="not-found-page"
      className="flex flex-col items-center justify-center text-center px-4 py-16 md:py-24 min-h-[60vh]"
    >
      <div className="w-20 h-20 rounded-full bg-muted/60 flex items-center justify-center mb-6">
        <SearchX className="h-10 w-10 text-muted-foreground" />
      </div>
      <h1 className="text-3xl md:text-4xl font-bold tracking-tight text-foreground mb-3">
        {t('pageNotFoundTitle', 'Page Not Found')}
      </h1>
      <p className="text-muted-foreground max-w-md mb-8 text-base">
        {t(
          'pageNotFoundDescription',
          'The page you are looking for does not exist or has been moved.'
        )}
      </p>
      <div className="flex flex-col sm:flex-row items-center gap-3">
        <Button asChild>
          <Link to="/menu">{t('browseMenu', 'Browse Menu')}</Link>
        </Button>
        <Button asChild variant="outline">
          <Link to="/">{t('backToHome', 'Back to Home')}</Link>
        </Button>
      </div>
    </div>
  );

  if (embedded) {
    return content;
  }

  return (
    <MainLayout>
      <Head title={t('pageNotFoundTitle', 'Page Not Found')} />
      {content}
    </MainLayout>
  );
}
