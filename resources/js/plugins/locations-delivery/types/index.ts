/**
 * TypeScript Interfaces for LocationsDelivery Plugin
 */

export interface Governorate {
  id: number;
  name: string;
  name_ar?: string | null;
  is_active: boolean;
  sort_order: number;
  areas?: Area[];
}

export interface Area {
  id: number;
  governorate_id: number;
  branch_id?: number | null;
  name: string;
  name_ar?: string | null;
  shipping_cost: number;
  is_active: boolean;
  sort_order: number;
  governorate?: Governorate;
}

export interface Branch {
  id: number;
  name: string;
  link?: string | null;
  is_active: boolean;
}

export interface Address {
  id: number;
  user_id?: number;
  area_id: number;
  phone_number: string;
  street: string;
  building: string;
  floor: string;
  apartment: string;
  notes?: string | null;
  is_default: boolean;
  created_at?: string;
  updated_at?: string;
  area?: Area;
}

export interface AddressPayload {
  area_id: number | string;
  phone_number: string;
  street: string;
  building: string;
  floor: string;
  apartment: string;
  notes?: string;
  is_default?: boolean;
}

export interface LocationsDeliveryData {
  addresses: Address[];
  governorates: Governorate[];
  branches: Branch[];
}

export interface LocationsDeliveryResponse {
  status: string;
  data: LocationsDeliveryData;
}

export interface AddressesResponse {
  status: string;
  data: Address[];
}

export interface SingleAddressResponse {
  status: string;
  message?: string;
  data: Address;
}

export interface GovernoratesResponse {
  status: string;
  data: Governorate[];
}

export interface BranchesResponse {
  status: string;
  data: Branch[];
}
