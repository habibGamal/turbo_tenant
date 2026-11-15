import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { Cart, UpdateCartItemPayload } from '../types';
import { CART_QUERY_KEY } from './useCart';

export interface UpdateItemArgs {
    itemId: number | string;
    payload: UpdateCartItemPayload;
}

interface MutationContext {
    previousCart?: Cart;
}

export function useUpdateCartItem() {
    const queryClient = useQueryClient();

    return useMutation<Cart, Error, UpdateItemArgs, MutationContext>({
        mutationFn: async ({ itemId, payload }: UpdateItemArgs) => {
            const { data } = await apiClient.patch<ApiSuccess<Cart>>(
                `/api/cart/items/${itemId}`,
                payload
            );
            return data.data;
        },
        onMutate: async ({ itemId, payload }) => {
            // Cancel ongoing cart queries to avoid race conditions
            await queryClient.cancelQueries({ queryKey: CART_QUERY_KEY });

            // Capture snapshot of previous cart state for rollback
            const previousCart = queryClient.getQueryData<Cart>(CART_QUERY_KEY);

            // Optimistically update the target line item
            if (previousCart) {
                queryClient.setQueryData<Cart>(CART_QUERY_KEY, {
                    ...previousCart,
                    items: previousCart.items.map((item) => {
                        if (String(item.id) === String(itemId)) {
                            return {
                                ...item,
                                ...(payload.quantity !== undefined
                                    ? { quantity: typeof payload.quantity === 'string' ? parseFloat(payload.quantity) : payload.quantity }
                                    : {}),
                                ...(payload.weight_multiplier !== undefined
                                    ? { weight_multiplier: payload.weight_multiplier }
                                    : {}),
                            };
                        }
                        return item;
                    }),
                });
            }

            return { previousCart };
        },
        onError: (_error, _variables, context) => {
            // Roll back to previous snapshot on validation error or stock rejection
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
