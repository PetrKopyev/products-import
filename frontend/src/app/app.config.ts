import { ApplicationConfig, LOCALE_ID, provideZoneChangeDetection } from '@angular/core';
import { registerLocaleData } from '@angular/common';
import localeRu from '@angular/common/locales/ru';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideAnimations } from '@angular/platform-browser/animations';
import { provideStore } from '@ngrx/store';
import { provideEffects } from '@ngrx/effects';

import { routes } from './app.routes';
import { productsReducer } from './store/products/products.reducer';
import { ProductsEffects } from './store/products/products.effects';
import { authInterceptor } from './interceptors/auth.interceptor';
import { MatPaginatorIntl } from '@angular/material/paginator';

registerLocaleData(localeRu);

/** Русские подписи пагинатора */
function ruPaginatorIntl(): MatPaginatorIntl {
  const paginator = new MatPaginatorIntl();
  paginator.itemsPerPageLabel = 'Товаров на странице:';
  paginator.nextPageLabel = 'Следующая страница';
  paginator.previousPageLabel = 'Предыдущая страница';
  paginator.firstPageLabel = 'Первая страница';
  paginator.lastPageLabel = 'Последняя страница';
  paginator.getRangeLabel = (page: number, size: number, length: number): string => {
    if (length === 0) {
      return '0 из 0';
    }
    const start = page * size + 1;
    const end = Math.min((page + 1) * size, length);
    return `${start} – ${end} из ${length}`;
  };
  return paginator;
}

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes),
    provideHttpClient(withInterceptors([authInterceptor])),
    provideAnimations(),
    provideStore({ products: productsReducer }),
    provideEffects(ProductsEffects),
    { provide: LOCALE_ID, useValue: 'ru' },
    { provide: MatPaginatorIntl, useFactory: ruPaginatorIntl },
  ],
};
