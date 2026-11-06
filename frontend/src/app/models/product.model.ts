export interface ProductAttribute {
  key: string;
  value: string;
}

export interface ProductImage {
  id: number;
  /** Исходная ссылка из xlsx */
  url: string;
  /** Локальный файл (отдаётся по /images/{path}) */
  path: string | null;
}

export interface Product {
  id: number;
  externalCode: string;
  name: string;
  description: string | null;
  price: number;
  /** Скидка в %, вычисленная при импорте */
  discount: number | null;
  createdAt: string;
  updatedAt: string;
  attributes: ProductAttribute[];
  images: ProductImage[];
}

export interface ProductListMeta {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
}

export interface ProductListResponse {
  data: Product[];
  meta: ProductListMeta;
}
