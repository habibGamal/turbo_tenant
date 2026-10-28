import React from 'react';
import { useHeroBanners } from '@/plugins/hero-banners';
import HeroSlider from './components/HeroSlider';
import { HeroBannerSkeleton } from '@/components/ui/page-skeletons';

export default function ThemedHeroBanners() {
  const { data, isLoading } = useHeroBanners();

  if (isLoading) {
    return <HeroBannerSkeleton />;
  }

  if (!data?.data || data.data.length === 0) {
    return null;
  }

  return <HeroSlider slides={data.data as any} />;
}
