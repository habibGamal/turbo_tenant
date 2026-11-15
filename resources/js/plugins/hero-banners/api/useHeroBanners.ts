import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { HeroBannersResponse, HeroSlide } from '../types';

export function useHeroBanners(params?: Record<string, any>) {
  return useQuery<HeroSlide[]>({
    queryKey: ['hero-banners', params],
    queryFn: async () => {
      const { data } = await apiClient.get<HeroBannersResponse>('/api/hero-banners', { params });
      return data.data;
    },
    staleTime: 1000 * 60 * 5,
  });
}
