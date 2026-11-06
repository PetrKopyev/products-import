import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Product, ProductListResponse } from '../models/product.model';

export interface ProductQuery {
  page: number;
  limit: number;
  name?: string;
  priceMin?: number | null;
  priceMax?: number | null;
  sort?: 'price_asc' | 'price_desc' | '';
}

@Injectable({ providedIn: 'root' })
export class ProductService {
  constructor(private readonly http: HttpClient) {}

  getProducts(query: ProductQuery): Observable<ProductListResponse> {
    let params = new HttpParams()
      .set('page', String(query.page))
      .set('limit', String(query.limit));

    if (query.name) {
      params = params.set('name', query.name);
    }
    if (query.priceMin !== null && query.priceMin !== undefined) {
      params = params.set('price_min', String(query.priceMin));
    }
    if (query.priceMax !== null && query.priceMax !== undefined) {
      params = params.set('price_max', String(query.priceMax));
    }
    if (query.sort) {
      params = params.set('sort', query.sort);
    }

    return this.http.get<ProductListResponse>('/api/products', { params });
  }

  getProduct(id: number): Observable<{ data: Product }> {
    return this.http.get<{ data: Product }>(`/api/products/${id}`);
  }
}
