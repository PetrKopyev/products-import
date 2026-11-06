import { createAction, props } from '@ngrx/store';
import { Product } from '../../models/product.model';

export const loadProducts = createAction(
  '[Products] Load',
  props<{
    page: number;
    limit: number;
    name?: string;
    priceMin?: number | null;
    priceMax?: number | null;
  }>(),
);

export const loadProductsSuccess = createAction(
  '[Products] Load Success',
  props<{
    products: Product[];
    total: number;
    totalPages: number;
    page: number;
    limit: number;
  }>(),
);

export const loadProductsFailure = createAction(
  '[Products] Load Failure',
  props<{ error: string }>(),
);
