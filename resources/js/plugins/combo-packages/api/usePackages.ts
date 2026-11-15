import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ComboPackage, ComboPackagesResponse } from '../types';

export function usePackages(params?: { product_id?: number }) {
  return useQuery<ComboPackage[]>({
    queryKey: ['combo-packages', params],
    queryFn: async () => {
      const { data } = await apiClient.get<ComboPackagesResponse>('/api/packages', { params });
      return data.data;
    },
    staleTime: 1000 * 60 * 5,
  });
}
