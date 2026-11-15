import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { Cart } from '../types';
import { CART_QUERY_KEY } from './useCart';

interface MutationContext {
    previousCart?: Cart;
}

export function useRemoveCartItem() {
    const queryClient = useQueryClient();

    return useMutation<Cart, Error, number | string, MutationContext>({
        mutationFn: async (itemId: number | string) => {
            const { data } = await apiClient.delete<ApiSuccess<Cart>>(
                `/api/cart/items/${itemId}`
            );
            return data.data;
        },
        onMutate: async (itemId) => {
            // Cancel ongoing queries
            await queryClient.cancelQueries({ queryKey: CART_QUERY_KEY });

            // Capture snapshot of previous cart
            const previousCart = queryClient.getQueryData<Cart>(CART_QUERY_KEY);

            // Optimistically remove the item from cache
            if (previousCart) {
                queryClient.setQueryData<Cart>(CART_QUERY_KEY, {
                    ...previousCart,
                    items: previousCart.items.filter((item) => String(item.id) !== String(itemId)),
                });
            }

            return { previousCart };
        },
        onError: (_error, _variables, context) => {
            // Roll back to previous snapshot on error
            if (context?.previousCart) {
                queryClient.setQueryData(CART_QUERY_KEY, context.previousCart);
            }
        },
        onSuccess: (cart) => {
            queryClient.setQueryData(CART_QUERY_KEY, cart);

            const count = cart.items.length;
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count } }));
        },
        onSettled: () => {
            queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY });
        },
    });
}
