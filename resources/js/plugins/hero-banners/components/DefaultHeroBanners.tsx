import React from 'react';
import { useHeroBanners } from '../api/useHeroBanners';

export default function DefaultHeroBanners() {
  const { data, isLoading, error } = useHeroBanners();

  if (isLoading) {
    return <div className="py-8 text-center text-sm text-muted-foreground animate-pulse">Loading hero banners...</div>;
  }

  if (error || !data?.data || data.data.length === 0) {
    return null;
  }

  const slide = data.data[0];

  return (
    <div className="w-full my-6 p-8 border rounded-lg bg-card text-card-foreground">
      <h2 className="text-2xl font-bold mb-2">{slide.title}</h2>
      <p className="text-muted-foreground mb-4">{slide.subtitle}</p>
      {slide.cta_link && (
        <a href={slide.cta_link} className="inline-block px-4 py-2 bg-primary text-primary-foreground rounded">
          {slide.cta_text || 'Learn More'}
        </a>
      )}
    </div>
  );
}
