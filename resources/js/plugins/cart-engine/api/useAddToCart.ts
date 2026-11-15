import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { emitCommerceEvent } from '@/core/analytics';
import { cartItemToCommerceItem } from '../utils/cart-item-converter';
import { AddToCartPayload, Cart } from '../types';
import { CART_QUERY_KEY } from './useCart';

export interface UseAddToCartOptions {
    suppressToast?: boolean;
    track?: boolean;
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

    return useMutation<Cart, Error, AddToCartPayload>({
        mutationFn: async (payload: AddToCartPayload) => {
            const { data } = await apiClient.post<ApiSuccess<Cart>>('/api/cart/items', payload);
            return data.data;
        },
        onSuccess: (cart, variables) => {
            queryClient.setQueryData(CART_QUERY_KEY, cart);
            queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY });

            // Dispatch event for backward compatibility with non-React listeners
            const count = cart.items.length;
            window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count, cart } }));

            if (options?.track !== false) {
                const addedItem = cart.items?.find((item) =>
                    item.product_id === variables.product_id &&
                    (variables.variant_id ? item.variant?.id === variables.variant_id : true) &&
                    (variables.weight_option_value_id ? item.weight_option_value?.id === variables.weight_option_value_id : true)
                ) || cart.items?.find((item) => item.product_id === variables.product_id);

                if (addedItem) {
                    const commerceItem = cartItemToCommerceItem(addedItem, {
                        quantity: variables.quantity,
                        weight_multiplier: variables.weight_multiplier,
                        variant_id: variables.variant_id,
                    });
                    const totalValue = commerceItem.item_price * commerceItem.quantity;

                    emitCommerceEvent('AddToCart', {
                        items: [commerceItem],
                        value: totalValue,
                        content_type: 'product',
                    });
                } else {
                    const quantityAdded = variables.weight_multiplier
                        ? Number(variables.weight_multiplier)
                        : (variables.quantity ? Number(variables.quantity) : 1);

                    emitCommerceEvent('AddToCart', {
                        items: [
                            {
                                product_id: variables.product_id ?? variables.package_id ?? 0,
                                variant_id: variables.variant_id ?? null,
                                quantity: quantityAdded,
                                item_price: 0,
                            },
                        ],
                        value: 0,
                        content_type: 'product',
                    });
                }
            }

            if (!options?.suppressToast) {
                const isRTL = i18n.language === 'ar';
                const addedItem = cart.items?.find((item) => item.product_id === variables.product_id);
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
