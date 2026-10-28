/**
 * TypeScript Interfaces for HeroBanners Plugin
 */

export interface HeroSlide {
    id: number;
    title: string;
    title_ar?: string;
    subtitle: string;
    subtitle_ar?: string;
    badge?: string;
    badge_ar?: string;
    cta_text: string;
    cta_text_ar?: string;
    cta_link: string;
    secondary_cta_text?: string;
    secondary_cta_text_ar?: string;
    secondary_cta_link?: string;
    image?: string;
    gradient?: string;
    icon?: string;
    sort_order?: number;
    is_active?: boolean;
}

export type HeroBannerItem = HeroSlide;

export interface HeroBannersResponse {
    status: string;
    data: HeroSlide[];
}
