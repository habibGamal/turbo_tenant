import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { AddToCartPayload, CartApiResponse } from '../types';
import { CART_QUERY_KEY } from './useCart';

export function useAddToCart() {
    const queryClient = useQueryClient();

    return useMutation<CartApiResponse, Error, AddToCartPayload>({
        mutationFn: async (payload: AddToCartPayload) => {
            const { data } = await apiClient.post<CartApiResponse>('/api/cart/items', payload);
            return data;
        },
        onSuccess: (data) => {
            queryClient.setQueryData(CART_QUERY_KEY, data.cart);
            queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY });

            // Dispatch event for backward compatibility with non-React listeners
            const count = data.cart.items.length;
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count } }));
        },
    });
}
