import { useMutation } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import { ValidateCouponPayload, ValidateCouponResponse } from '../types';

export function useValidateCoupon() {
  return useMutation<ValidateCouponResponse, Error, ValidateCouponPayload>({
    mutationFn: async (payload: ValidateCouponPayload) => {
      const { data } = await apiClient.post<ValidateCouponResponse>('/coupons/validate', payload);
      return data;
    },
  });
}
