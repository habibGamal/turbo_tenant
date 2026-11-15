import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { apiClient } from '@/core/api/client';
import { ApiSuccess } from '@/core/api/types';
import { AddPackageToCartPayload, Cart } from '@/plugins/cart-engine/types';
import { CART_QUERY_KEY } from '@/plugins/cart-engine/api/useCart';
import { ComboPackage } from '../types';

export interface UseAddPackageToCartOptions {
  suppressToast?: boolean;
  package?: ComboPackage;
}

export interface MappedCartErrors {
  general?: string;
  groupErrors: Record<number, string>;
  itemErrors: Record<number, string>;
}

/**
 * Maps a Laravel 422 validation error object to group-level, component-level, and general messages.
 */
export function mapCartErrorsToGroups(
  error: any,
  pkg?: ComboPackage,
  payload?: AddPackageToCartPayload
): MappedCartErrors {
  const result: MappedCartErrors = {
    groupErrors: {},
    itemErrors: {},
  };

  const responseData = error?.response?.data;
  if (!responseData) {
    if (error?.message) {
      result.general = error.message;
    }
    return result;
  }

  if (responseData.message) {
    result.general = responseData.message;
  }

  const errors = responseData.errors;
  if (!errors || typeof errors !== 'object') {
    return result;
  }

  // Map each error key
  for (const [key, rawMessages] of Object.entries(errors)) {
    const messages = Array.isArray(rawMessages) ? rawMessages : [String(rawMessages)];
    const message = messages[0];
    if (!message) continue;

    // Check if key references package_id or quantity
    if (key === 'package_id' || key === 'quantity') {
      result.general = message;
      continue;
    }

    // Check if key references a group directly, e.g. "groups.1" or "group_1"
    const groupMatch = key.match(/(?:group|groups)[\._](\d+)/i);
    if (groupMatch) {
      const groupId = parseInt(groupMatch[1], 10);
      result.groupErrors[groupId] = message;
      continue;
    }

    // Check if key references a selection index, e.g. "selections.0.extras", "selections.1.notes"
    const selectionMatch = key.match(/^selections\.(\d+)(?:\.(.*))?$/);
    if (selectionMatch) {
      const selIdx = parseInt(selectionMatch[1], 10);
      const selItem = payload?.selections?.[selIdx];
      if (selItem) {
        result.itemErrors[selItem.package_item_id] = message;
        continue;
      }
      if (pkg) {
        result.general = result.general || message;
        continue;
      }
    }

    // Fall back to general
    if (!result.general) {
      result.general = message;
    }
  }

  return result;
}

export function useAddPackageToCart(options?: UseAddPackageToCartOptions) {
  const queryClient = useQueryClient();
  const { t, i18n } = useTranslation();
  let navigate: ReturnType<typeof useNavigate> | null = null;
  try {
    navigate = useNavigate();
  } catch {
    navigate = null;
  }

  return useMutation<Cart, any, AddPackageToCartPayload>({
    mutationFn: async (payload: AddPackageToCartPayload) => {
      const { data } = await apiClient.post<ApiSuccess<Cart>>('/api/cart/items', payload);
      return data.data;
    },
    onSuccess: (cart, variables) => {
      queryClient.setQueryData(CART_QUERY_KEY, cart);
      queryClient.invalidateQueries({ queryKey: CART_QUERY_KEY });

      const count = cart.items.length;
      window.dispatchEvent(new CustomEvent('cart-updated', { detail: { count, cart } }));

      if (!options?.suppressToast) {
        const isRTL = i18n.language === 'ar';
        const pkgName = options?.package
          ? (isRTL && options.package.name_ar ? options.package.name_ar : options.package.name)
          : t('combo', 'Combo');

        const toastMessage = t('packageAddedToCart', {
          name: pkgName,
          defaultValue: `Added ${pkgName} to cart`,
        });

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
