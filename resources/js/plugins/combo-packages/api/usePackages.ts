import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ComboPackagesResponse, ComboPackage } from '../types';

export function usePackages() {
  return useQuery<ComboPackage[]>({
    queryKey: ['combo-packages'],
    queryFn: async () => {
      const { data } = await apiClient.get<ComboPackagesResponse>('/packages');
      return data.data;
    },
    staleTime: 1000 * 60 * 5,
  });
}
