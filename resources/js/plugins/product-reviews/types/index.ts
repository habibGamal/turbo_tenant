export interface ReviewUser {
  id: number;
  name: string;
}

export interface ReviewItem {
  id: number;
  product_id: number;
  user_id?: number | null;
  guest_name?: string | null;
  guest_email?: string | null;
  rating: number;
  comment: string;
  images?: string[];
  is_published: boolean;
  created_at: string;
  user?: ReviewUser | null;
}

export interface ProductReviewsResponse {
  status: string;
  data: ReviewItem[];
  total: number;
  current_page: number;
  last_page: number;
}

export interface SubmitReviewPayload {
  rating: number;
  comment: string;
  guest_name?: string;
  guest_email?: string;
  images?: File[];
}
