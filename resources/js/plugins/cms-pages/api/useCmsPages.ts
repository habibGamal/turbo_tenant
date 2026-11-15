import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { CmsPageDetailResponse, CmsPageItem, CmsPagesResponse } from '../types';

/**
 * Hook to fetch listing of active CMS pages.
 */
export function useCmsPages(params?: Record<string, any>) {
  return useQuery<CmsPageItem[]>({
    queryKey: ['cms-pages', params],
    queryFn: async () => {
      const { data } = await apiClient.get<CmsPagesResponse>('/api/cms-pages', { params });
      return data.data;
    },
    staleTime: 1000 * 60 * 5,
  });
}

/**
 * Hook to fetch a single CMS page by its slug.
 */
export function useCmsPage(slug?: string) {
  return useQuery<CmsPageItem>({
    queryKey: ['cms-pages', 'detail', slug],
    queryFn: async () => {
      if (!slug) {
        throw new Error('Slug is required to fetch CMS page');
      }
      const { data } = await apiClient.get<CmsPageDetailResponse>(`/api/cms-pages/${slug}`);
      return data.data;
    },
    enabled: Boolean(slug),
    staleTime: 1000 * 60 * 5,
  });
}
