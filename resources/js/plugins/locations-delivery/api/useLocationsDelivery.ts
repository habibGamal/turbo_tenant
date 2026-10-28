import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '@/core/api/client';
import {
  Address,
  AddressesResponse,
  AddressPayload,
  BranchesResponse,
  GovernoratesResponse,
  LocationsDeliveryResponse,
  SingleAddressResponse,
} from '../types';

/**
 * Fetch aggregate locations delivery metadata (governorates, branches, addresses).
 */
export function useLocationsDelivery(params?: Record<string, any>) {
  return useQuery<LocationsDeliveryResponse>({
    queryKey: ['locations-delivery', params],
    queryFn: async () => {
      const { data } = await apiClient.get<LocationsDeliveryResponse>('/api/locations-delivery', { params });
      return data;
    },
    staleTime: 1000 * 60 * 2,
  });
}

/**
 * Fetch active governorates with delivery areas.
 */
export function useGovernorates() {
  return useQuery<GovernoratesResponse>({
    queryKey: ['locations-delivery', 'governorates'],
    queryFn: async () => {
      const { data } = await apiClient.get<GovernoratesResponse>('/api/locations-delivery/governorates');
      return data;
    },
    staleTime: 1000 * 60 * 10,
  });
}

/**
 * Fetch active branches.
 */
export function useBranches() {
  return useQuery<BranchesResponse>({
    queryKey: ['locations-delivery', 'branches'],
    queryFn: async () => {
      const { data } = await apiClient.get<BranchesResponse>('/api/locations-delivery/branches');
      return data;
    },
    staleTime: 1000 * 60 * 10,
  });
}

/**
 * Fetch customer delivery addresses.
 */
export function useAddresses() {
  return useQuery<AddressesResponse>({
    queryKey: ['locations-delivery', 'addresses'],
    queryFn: async () => {
      const { data } = await apiClient.get<AddressesResponse>('/api/locations-delivery/addresses');
      return data;
    },
    staleTime: 1000 * 60 * 2,
  });
}

/**
 * Mutation to create or update an address.
 */
export function useSaveAddress() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ id, payload }: { id?: number; payload: AddressPayload }) => {
      if (id) {
        const { data } = await apiClient.patch<SingleAddressResponse>(`/api/locations-delivery/addresses/${id}`, payload);
        return data;
      }
      const { data } = await apiClient.post<SingleAddressResponse>('/api/locations-delivery/addresses', payload);
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['locations-delivery'] });
    },
  });
}

/**
 * Mutation to delete an address.
 */
export function useDeleteAddress() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await apiClient.delete(`/api/locations-delivery/addresses/${id}`);
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['locations-delivery'] });
    },
  });
}
