import { useQuery } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { CheckoutBootstrapData, CheckoutDataResponse } from '../types';

export function useCheckoutData() {
    return useQuery<CheckoutBootstrapData>({
        queryKey: ['checkout-data'],
        queryFn: async () => {
            const { data } = await apiClient.get<CheckoutDataResponse>('/api/checkout-data');
            return data.data;
        },
        staleTime: 1000 * 30, // 30 seconds
    });
}
