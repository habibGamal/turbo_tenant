/**
 * The single JSON envelope every storefront API endpoint returns.
 * Backend mirror: app/Http/Responses/ApiResponse.php.
 */

export interface PaginationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export interface ApiSuccess<T, M = Record<string, unknown>> {
  status: 'success';
  data: T;
  message?: string;
  meta?: M;
}

export interface ApiError {
  status: 'error';
  message: string;
  /** Field-level validation messages (422). */
  errors?: Record<string, string[]>;
}

export type Paginated<T> = ApiSuccess<T[], PaginationMeta> & { meta: PaginationMeta };
