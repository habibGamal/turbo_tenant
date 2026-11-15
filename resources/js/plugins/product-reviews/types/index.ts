import { ApiSuccess, Paginated } from '@/core/api/types';

export interface ReviewUser {
  id: number;
  name: string;
}

export interface ReviewItem {
  id: number;
  product_id: number;
  guest_name?: string | null;
  rating: number;
  comment: string;
  images?: string[];
  is_published: boolean;
  created_at: string;
  user?: ReviewUser | null;
}

export type ProductReviewsResponse = Paginated<ReviewItem>;
export type SingleReviewResponse = ApiSuccess<ReviewItem>;

export interface SubmitReviewPayload {
  rating: number;
  comment: string;
  guest_name?: string;
  guest_email?: string;
  images?: File[];
}
