import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { emitCommerceEvent } from '@/core/analytics';
import { PlaceOrderPayload, PlaceOrderResponse, PlaceOrderSuccessData } from '../types';

export function usePlaceOrder() {
    const queryClient = useQueryClient();

    return useMutation<PlaceOrderSuccessData, Error, PlaceOrderPayload>({
        // Do not queue a purchase for automatic replay when connectivity returns.
        networkMode: 'always',
        mutationFn: async (payload: PlaceOrderPayload) => {
            const { data } = await apiClient.post<PlaceOrderResponse>('/api/orders/place', payload);
            return data.data;
        },
        onSuccess: (data) => {
            queryClient.invalidateQueries({ queryKey: ['orders'] });
            queryClient.invalidateQueries({ queryKey: ['cart'] });
            queryClient.invalidateQueries({ queryKey: ['locations-delivery'] });

            if (data.requires_payment && data.tracking_token) {
                try {
                    sessionStorage.setItem(
                        `guest:tracking:${data.order_id}`,
                        JSON.stringify({
                            order_number: data.order_number,
                            tracking_token: data.tracking_token,
                        })
                    );
                } catch {
                    // ignore sessionStorage quota or private browsing errors
                }
            }

            if (data.tracking) {
                if (!data.requires_payment) {
                    emitCommerceEvent('Purchase', { tracking: data.tracking });
                } else {
                    try {
                        sessionStorage.setItem(`meta:purchase:${data.order_id}`, JSON.stringify(data.tracking));
                    } catch {
                        // ignore sessionStorage quota or private browsing errors
                    }
                }
            }
        },
    });
}
