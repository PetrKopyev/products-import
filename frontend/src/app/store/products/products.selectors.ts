import { createFeatureSelector, createSelector } from '@ngrx/store';
import { ProductsState } from './products.reducer';

export const selectProductsState = createFeatureSelector<ProductsState>('products');

export const selectAllProducts = createSelector(
  selectProductsState,
  (state: ProductsState) => state.products,
);

export const selectProductsLoading = createSelector(
  selectProductsState,
  (state: ProductsState) => state.loading,
);

export const selectProductsError = createSelector(
  selectProductsState,
  (state: ProductsState) => state.error,
);

export const selectTotalPages = createSelector(
  selectProductsState,
  (state: ProductsState) => state.totalPages,
);

export const selectProductsTotal = createSelector(
  selectProductsState,
  (state: ProductsState) => state.total,
);

export const selectProductsPage = createSelector(
  selectProductsState,
  (state: ProductsState) => state.page,
);

export const selectProductsLimit = createSelector(
  selectProductsState,
  (state: ProductsState) => state.limit,
);
