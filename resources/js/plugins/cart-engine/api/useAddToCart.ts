import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { apiClient } from '@/core/api/client';
import { AddToCartPayload, CartApiResponse } from '../types';
import { CART_QUERY_KEY } from './useCart';

export interface UseAddToCartOptions {
    suppressToast?: boolean;
}

export function useAddToCart(options?: UseAddToCartOptions) {
    const queryClient = useQueryClient();
    const { t, i18n } = useTranslation();
    let navigate: ReturnType<typeof useNavigate> | null = null;
    try {
        navigate = useNavigate();
    } catch {
        navigate = null;
    }

    return useMutation<CartApiResponse, Error, AddToCartPayload>({
        mutationFn: async (payload: AddToCartPayload) => {
            const { data } = await apiClient.post<CartApiResponse>('/api/cart/items', payload);
            return data;
        },
        onSuccess: (data, variables) => {
            queryClient.setQueryData(CART_QUERY_KEY, data.cart);
            queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY });

            // Dispatch event for backward compatibility with non-React listeners
            const count = data.cart.items.length;
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count, cart: data.cart } }));

            if (!options?.suppressToast) {
                const isRTL = i18n.language === 'ar';
                const addedItem = data.cart?.items?.find((item) => item.product_id === variables.product_id);
                const itemName = addedItem?.product
                    ? (isRTL && addedItem.product.name_ar ? addedItem.product.name_ar : addedItem.product.name)
                    : '';

                const toastMessage = itemName
                    ? t('addedToCart', { name: itemName, defaultValue: `Added ${itemName} to cart` })
                    : t('viewCart', 'عناصر في السلة');

                toast.success(toastMessage, {
                    action: {
                        label: t('viewCart', 'عرض السلة'),
                        onClick: () => {
                            if (navigate) {
                                navigate('/cart');
                            } else {
                                window.location.href = '/cart';
                            }
                        },
                    },
                });
            }
        },
    });
}
