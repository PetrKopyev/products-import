import { Injectable } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, map, mergeMap, of } from 'rxjs';
import { ProductService } from '../../services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

@Injectable()
export class ProductsEffects {
  loadProducts$ = createEffect(() =>
    this.actions$.pipe(
      ofType(loadProducts),
      mergeMap((action) =>
        this.productService
          .getProducts({
            page: action.page,
            limit: action.limit,
            name: action.name,
            priceMin: action.priceMin,
            priceMax: action.priceMax,
          })
          .pipe(
            map((response) =>
              loadProductsSuccess({
                products: response.data,
                total: response.meta.total,
                totalPages: response.meta.totalPages,
                page: response.meta.page,
                limit: response.meta.limit,
              }),
            ),
            catchError((error: unknown) =>
              of(
                loadProductsFailure({
                  error: error instanceof Error ? error.message : 'Ошибка загрузки товаров',
                }),
              ),
            ),
          ),
      ),
    ),
  );

  constructor(
    private readonly actions$: Actions,
    private readonly productService: ProductService,
  ) {}
}
