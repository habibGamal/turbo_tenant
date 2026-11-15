export interface MetaPixelConfig {
  pixelId?: string | null;
  currency?: string | null;
  debugMode?: boolean;
  serverEventsEnabled?: boolean;
}

export interface MetaUserData {
  em?: string | null;
  external_id?: string | null;
}

export interface MetaCustomData {
  value?: number;
  currency?: string;
  content_name?: string;
  content_category?: string;
  content_type?: 'product' | 'product_group' | string;
  content_ids?: string[];
  contents?: Array<{
    id: string;
    quantity: number;
    item_price?: number;
  }>;
  num_items?: number;
  search_string?: string;
  status?: boolean | string;
  order_id?: string | number;
  [key: string]: any;
}

export interface MetaRelayPayload {
  event_name: string;
  event_id: string;
  event_source_url: string;
  custom_data?: MetaCustomData;
}
