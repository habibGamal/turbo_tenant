import { apiClient } from '@/core/api/client';
import { queryClient } from '@/core/api/query-client';
import { ApiSuccess } from '@/core/api/types';
import { Cart } from '@/plugins/cart-engine/types';

export interface ExtraWithQuantity {
    id: number;
    quantity?: number;
}

export interface AddToCartParams {
    product_id: number;
    variant_id?: number | null;
    weight_option_value_id?: number | null;
    quantity?: string;
    weight_multiplier?: number;
    extras?: ExtraWithQuantity[];
}

export interface UpdateCartItemParams {
    quantity?: string;
    weight_multiplier?: number;
    weight_option_value_id?: number;
}

export interface CartOperationCallbacks {
    onSuccess?: (data: any) => void;
    onError?: (error: any) => void;
    onFinally?: () => void;
}

/**
 * Helper to dispatch cart updated event & sync TanStack Query cache
 */
const dispatchCartUpdated = (cart: any) => {
    const count = cart?.items?.length ?? 0;
    if (cart) {
        queryClient.setQueryData(['cart'], cart);
    }
    queryClient.invalidateQueries({ queryKey: ['cart'] });
    window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count } }));
};

/**
 * Add an item to the cart
 */
export const addToCart = async (
    params: AddToCartParams,
    callbacks?: CartOperationCallbacks
) => {
    try {
        const response = await apiClient.post('/api/cart/items', {
            product_id: params.product_id,
            variant_id: params.variant_id ?? null,
            weight_option_value_id: params.weight_option_value_id ?? null,
            quantity: params.quantity ?? '1',
            weight_multiplier: params.weight_multiplier ?? 1,
            extras: params.extras ?? [],
        });

        dispatchCartUpdated(response.data.data);
        callbacks?.onSuccess?.(response.data.data);
        return response.data.data;
    } catch (error) {
        callbacks?.onError?.(error);
        throw error;
    } finally {
        callbacks?.onFinally?.();
    }
};

/**
 * Update cart item quantity
 */
export const updateCartItem = async (
    itemId: number | string,
    params: UpdateCartItemParams,
    callbacks?: CartOperationCallbacks
) => {
    try {
        const response = await apiClient.patch(
            `/api/cart/items/${itemId}`,
            {
                quantity: params.quantity,
                weight_multiplier: params.weight_multiplier,
                weight_option_value_id: params.weight_option_value_id,
            }
        );

        dispatchCartUpdated(response.data.data);
        callbacks?.onSuccess?.(response.data.data);
        return response.data.data;
    } catch (error) {
        callbacks?.onError?.(error);
        throw error;
    } finally {
        callbacks?.onFinally?.();
    }
};

/**
 * Remove an item from the cart
 */
export const removeCartItem = async (
    itemId: number | string,
    callbacks?: CartOperationCallbacks
) => {
    try {
        const response = await apiClient.delete(`/api/cart/items/${itemId}`);

        dispatchCartUpdated(response.data.data);
        callbacks?.onSuccess?.(response.data.data);
        return response.data.data;
    } catch (error) {
        callbacks?.onError?.(error);
        throw error;
    } finally {
        callbacks?.onFinally?.();
    }
};

/**
 * Clear all items from the cart
 */
export const clearCart = async (callbacks?: CartOperationCallbacks) => {
    try {
        const response = await apiClient.delete('/api/cart');

        dispatchCartUpdated(response.data.data);
        callbacks?.onSuccess?.(response.data.data);
        return response.data.data;
    } catch (error) {
        callbacks?.onError?.(error);
        throw error;
    } finally {
        callbacks?.onFinally?.();
    }
};

/**
 * Get current cart data
 */
export const getCart = async (callbacks?: CartOperationCallbacks) => {
    try {
        const response = await apiClient.get('/api/cart');

        callbacks?.onSuccess?.(response.data.data);
        return response.data.data;
    } catch (error) {
        callbacks?.onError?.(error);
        throw error;
    } finally {
        callbacks?.onFinally?.();
    }
};
