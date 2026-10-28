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

export interface CmsPagesResponse {
  status: string;
  data: CmsPageItem[];
}

export interface CmsPageDetailResponse {
  status: string;
  data: CmsPageItem;
}
