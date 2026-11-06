import { createReducer, on } from '@ngrx/store';
import { Product } from '../../models/product.model';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

export interface ProductsState {
  products: Product[];
  total: number;
  totalPages: number;
  page: number;
  limit: number;
  loading: boolean;
  error: string | null;
}

export const initialState: ProductsState = {
  products: [],
  total: 0,
  totalPages: 0,
  page: 1,
  limit: 20,
  loading: false,
  error: null,
};

export const productsReducer = createReducer(
  initialState,
  on(loadProducts, (state, { page, limit }): ProductsState => ({
    ...state,
    page,
    limit,
    loading: true,
    error: null,
  })),
  on(
    loadProductsSuccess,
    (state, { products, total, totalPages, page, limit }): ProductsState => ({
      ...state,
      products,
      total,
      totalPages,
      page,
      limit,
      loading: false,
      error: null,
    }),
  ),
  on(loadProductsFailure, (state, { error }): ProductsState => ({
    ...state,
    loading: false,
    error,
  })),
);
