import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { PlaceOrderPayload, PlaceOrderResponse } from '../types';

export function usePlaceOrder() {
    const queryClient = useQueryClient();

    return useMutation<PlaceOrderResponse, Error, PlaceOrderPayload>({
        mutationFn: async (payload: PlaceOrderPayload) => {
            const { data } = await apiClient.post<PlaceOrderResponse>('/api/orders/place', payload);
            return data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ['orders'] });
            queryClient.invalidateQueries({ queryKey: ['cart'] });
            queryClient.invalidateQueries({ queryKey: ['checkout-data'] });
        },
    });
}
