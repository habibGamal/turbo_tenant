import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { HeroBannersResponse } from '../types';

export function useHeroBanners(params?: Record<string, any>) {
  return useQuery<HeroBannersResponse>({
    queryKey: ['hero-banners', params],
    queryFn: async () => {
      const { data } = await apiClient.get<HeroBannersResponse>('/api/hero-banners', { params });
      return data;
    },
    staleTime: 1000 * 60 * 5,
  });
}
