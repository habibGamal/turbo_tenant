import axios from 'axios';

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
 * Add an item to the cart
 */
export const addToCart = async (
    params: AddToCartParams,
    callbacks?: CartOperationCallbacks
) => {
    try {
        const response = await axios.post(route('cart.store'), {
            product_id: params.product_id,
            variant_id: params.variant_id ?? null,
            weight_option_value_id: params.weight_option_value_id ?? null,
            quantity: params.quantity ?? '1',
            weight_multiplier: params.weight_multiplier ?? 1,
            extras: params.extras ?? [],
        });

        callbacks?.onSuccess?.(response.data);
        return response.data;
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
        const response = await axios.patch(
            route('cart.update', { itemId: itemId.toString() }),
            {
                quantity: params.quantity,
                weight_multiplier: params.weight_multiplier,
                weight_option_value_id: params.weight_option_value_id,
            }
        );

        callbacks?.onSuccess?.(response.data);
        return response.data;
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
        const response = await axios.delete(
            route('cart.destroy', { itemId: itemId.toString() })
        );

        callbacks?.onSuccess?.(response.data);
        return response.data;
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
        const response = await axios.delete(route('cart.clear'));

        callbacks?.onSuccess?.(response.data);
        return response.data;
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
        const response = await axios.get(route('cart.index'));

        callbacks?.onSuccess?.(response.data);
        return response.data;
    } catch (error) {
        callbacks?.onError?.(error);
        throw error;
    } finally {
        callbacks?.onFinally?.();
    }
};
