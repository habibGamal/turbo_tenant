import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ComboPackage, SingleComboPackageResponse } from '../types';

export function usePackage(id?: string | number) {
  return useQuery<ComboPackage>({
    queryKey: ['combo-packages', id],
    queryFn: async () => {
      if (!id) {
        throw new Error('Package ID is required');
      }
      const { data } = await apiClient.get<SingleComboPackageResponse>(`/api/packages/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
    staleTime: 1000 * 60 * 5,
  });
}
