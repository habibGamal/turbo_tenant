/**
 * TypeScript Interfaces for CustomerFavorites Plugin
 */

export interface CustomerFavoriteProduct {
  id: number;
  name: string;
  name_ar?: string;
  description?: string;
  image?: string;
  price: number;
  base_price?: number;
  price_after_discount?: number;
  category?: string;
  rating?: number;
  is_active?: boolean;
}

export interface CustomerFavoritesData {
  enabled: boolean;
  count: number;
  products: CustomerFavoriteProduct[];
}

export interface CustomerFavoritesApiResponse {
  status: 'success' | 'error';
  data: CustomerFavoritesData;
  message?: string;
}

export interface CustomerFavoritesSyncResponse {
  status: 'success' | 'error';
  message: string;
  data: {
    synced_ids: number[];
  };
}
