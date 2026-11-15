import { ApiSuccess } from '@/core/api/types';

/**
 * TypeScript Interfaces for CMS Pages Plugin
 */

export interface CmsPageItem {
  id: number;
  title: string;
  title_ar?: string | null;
  slug: string;
  content?: string;
  content_ar?: string | null;
  is_active?: boolean;
  created_at?: string;
  updated_at?: string;
}

export type CmsPagesResponse = ApiSuccess<CmsPageItem[]>;
export type CmsPageDetailResponse = ApiSuccess<CmsPageItem>;
