import { useMutation } from '@tanstack/react-query';
import { AxiosError } from 'axios';
import { apiClient } from '@/core/api/client';
import { ApiError, ApiSuccess } from '@/core/api/types';
import { ValidateCouponData, ValidateCouponPayload } from '../types';

export function useValidateCoupon() {
  return useMutation<ValidateCouponData, AxiosError<ApiError>, ValidateCouponPayload>({
    mutationFn: async (payload: ValidateCouponPayload) => {
      const response = await apiClient.post<ApiSuccess<ValidateCouponData>>('/coupons/validate', {
        code: payload.code,
        address_id: payload.address_id,
        guest_phone: payload.guest_phone,
      });
      return response.data.data;
    },
  });
}
