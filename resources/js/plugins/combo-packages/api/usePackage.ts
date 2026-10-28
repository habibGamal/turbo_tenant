import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ComboPackage } from '../types';

export function usePackage(id?: string | number) {
  return useQuery<ComboPackage>({
    queryKey: ['combo-packages', id],
    queryFn: async () => {
      if (!id) throw new Error('Package ID is required');
      const { data } = await apiClient.get<{ status: string; data: ComboPackage }>(`/packages/${id}`);
      return data.data;
    },
    enabled: !!id,
    staleTime: 1000 * 60 * 5,
  });
}
