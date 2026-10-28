export interface PackageGroupItem {
  id: number;
  product_id: number;
  variant_id?: number | null;
  sort_order: number;
  product?: {
    id: number;
    name: string;
    image?: string;
  };
  variant?: {
    id: number;
    name: string;
    price: number;
  };
}

export interface PackageGroup {
  id: number;
  package_id: number;
  name: string;
  min_quantity: number;
  max_quantity: number;
  sort_order: number;
  items: PackageGroupItem[];
}

export interface ComboPackage {
  id: number;
  name: string;
  name_ar?: string;
  description?: string;
  price: number;
  image?: string;
  is_active: boolean;
  sort_order: number;
  groups?: PackageGroup[];
}

export interface ComboPackagesResponse {
  status: string;
  data: ComboPackage[];
}
