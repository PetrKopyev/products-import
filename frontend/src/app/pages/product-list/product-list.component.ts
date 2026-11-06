import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Store } from '@ngrx/store';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTableModule } from '@angular/material/table';
import { combineLatest, debounceTime, distinctUntilChanged, map, Subject, takeUntil } from 'rxjs';
import { loadProducts } from '../../store/products/products.actions';
import {
  selectAllProducts,
  selectProductsError,
  selectProductsLimit,
  selectProductsLoading,
  selectProductsPage,
  selectProductsTotal,
} from '../../store/products/products.selectors';
import { Product } from '../../models/product.model';

@Component({
  selector: 'app-product-list',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatCardModule,
    MatTableModule,
    MatPaginatorModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatProgressSpinnerModule,
  ],
  template: `
    <div class="page-container">
      <mat-card>
        <mat-card-header>
          <mat-card-title>Товары</mat-card-title>
        </mat-card-header>
        <mat-card-content>
          <form [formGroup]="filterForm" class="filter-form" (ngSubmit)="reload(1)">
            <mat-form-field appearance="outline" subscriptSizing="dynamic">
              <mat-label>Наименование</mat-label>
              <input matInput formControlName="name" data-cy="filter-name" />
            </mat-form-field>
            <mat-form-field appearance="outline" subscriptSizing="dynamic">
              <mat-label>Цена от</mat-label>
              <input matInput type="number" formControlName="priceMin" data-cy="filter-price-min" />
            </mat-form-field>
            <mat-form-field appearance="outline" subscriptSizing="dynamic">
              <mat-label>Цена до</mat-label>
              <input matInput type="number" formControlName="priceMax" data-cy="filter-price-max" />
            </mat-form-field>
            <button mat-stroked-button type="submit">
              <mat-icon>search</mat-icon> Найти
            </button>
            <button mat-button type="button" (click)="clearFilters()">Сбросить</button>
          </form>

          @if (vm$ | async; as vm) {
            @if (vm.loading) {
              <div class="loading-box">
                <mat-spinner diameter="40" />
              </div>
            } @else if (vm.error) {
              <p class="text-muted">Ошибка загрузки: {{ vm.error }}</p>
            } @else {
              <table mat-table [dataSource]="vm.products" class="full-width" data-cy="products-table">
                <ng-container matColumnDef="image">
                  <th mat-header-cell *matHeaderCellDef></th>
                  <td mat-cell *matCellDef="let product">
                    @if (imageSrc(product); as src) {
                      <img class="thumb" [src]="src" [alt]="product.name" />
                    } @else {
                      <div class="thumb thumb-placeholder"><mat-icon>image</mat-icon></div>
                    }
                  </td>
                </ng-container>
                <ng-container matColumnDef="name">
                  <th mat-header-cell *matHeaderCellDef>Наименование</th>
                  <td mat-cell *matCellDef="let product">{{ product.name }}</td>
                </ng-container>
                <ng-container matColumnDef="price">
                  <th mat-header-cell *matHeaderCellDef class="sortable-header" (click)="toggleSort()">
                    Цена
                    <mat-icon class="sort-icon">{{ sortIcon() }}</mat-icon>
                  </th>
                  <td mat-cell *matCellDef="let product" class="price-cell">{{ product.price | number: '1.2-2' }} ₽</td>
                </ng-container>
                <ng-container matColumnDef="discount">
                  <th mat-header-cell *matHeaderCellDef class="col-center">Скидка</th>
                  <td mat-cell *matCellDef="let product" class="col-center">
                    @if (product.discount !== null) {
                      <span class="discount-badge">{{ product.discount | number: '1.0-0' }}%</span>
                    } @else {
                      <span class="text-muted">—</span>
                    }
                  </td>
                </ng-container>
                <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
                <tr
                  mat-row
                  *matRowDef="let row; columns: displayedColumns"
                  class="clickable-row"
                  (click)="openProduct(row)"
                ></tr>
              </table>

              <mat-paginator
                [length]="vm.total"
                [pageSize]="vm.limit"
                [pageIndex]="vm.page - 1"
                [pageSizeOptions]="[10, 20, 50]"
                (page)="onPage($event)"
                showFirstLastButtons
              />
            }
          }
        </mat-card-content>
      </mat-card>
    </div>
  `,
  styles: [
    `
      .thumb {
        width: 56px;
        height: 56px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #dde4ea;
        display: block;
        margin: 6px 0;
      }
      .thumb-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #eef3f6;
        border-style: dashed;
        color: #9aa7b3;
      }
      .price-cell {
        font-weight: 600;
      }
      .col-center {
        text-align: center;
      }
      .sortable-header {
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
      }
      .sortable-header:hover {
        color: #0e7c86;
      }
      .sort-icon {
        font-size: 15px;
        width: 15px;
        height: 15px;
        vertical-align: -2px;
        margin-left: 2px;
        color: #30afb8;
      }
      .loading-box {
        display: flex;
        justify-content: center;
        padding: 40px 0;
      }
    `,
  ],
})
export class ProductListComponent implements OnInit, OnDestroy {
  protected readonly displayedColumns = ['image', 'name', 'price', 'discount'];

  protected sort: 'price_asc' | 'price_desc' | '' = '';

  protected readonly vm$ = combineLatest({
    products: this.store.select(selectAllProducts),
    loading: this.store.select(selectProductsLoading),
    error: this.store.select(selectProductsError),
    total: this.store.select(selectProductsTotal),
    page: this.store.select(selectProductsPage),
    limit: this.store.select(selectProductsLimit),
  }).pipe(map((vm) => ({ ...vm })));

  protected readonly filterForm = this.fb.group({
    name: [''],
    priceMin: [null as number | null],
    priceMax: [null as number | null],
  });

  private readonly destroy$ = new Subject<void>();
  private limit = 20;

  constructor(
    private readonly store: Store,
    private readonly fb: FormBuilder,
    private readonly router: Router,
  ) {}

  ngOnInit(): void {
    this.limit = this.store.selectSignal(selectProductsLimit)();
    this.reload(1);

    // Поиск по имени — с дебаунсом
    this.filterForm.controls.name.valueChanges
      .pipe(debounceTime(400), distinctUntilChanged(), takeUntil(this.destroy$))
      .subscribe(() => this.reload(1));
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  reload(page: number, limit = this.limit): void {
    this.limit = limit;
    const { name, priceMin, priceMax } = this.filterForm.getRawValue();
    this.store.dispatch(
      loadProducts({
        page,
        limit,
        name: name?.trim() ? name.trim() : undefined,
        priceMin: this.validNumber(priceMin),
        priceMax: this.validNumber(priceMax),
        sort: this.sort,
      }),
    );
  }

  toggleSort(): void {
    this.sort = this.sort === '' ? 'price_asc' : this.sort === 'price_asc' ? 'price_desc' : '';
    this.reload(1);
  }

  sortIcon(): string {
    if (this.sort === 'price_asc') {
      return 'arrow_upward';
    }
    if (this.sort === 'price_desc') {
      return 'arrow_downward';
    }
    return 'unfold_more';
  }

  onPage(event: PageEvent): void {
    this.reload(event.pageIndex + 1, event.pageSize);
  }

  clearFilters(): void {
    this.filterForm.reset({ name: '', priceMin: null, priceMax: null });
    this.reload(1);
  }

  openProduct(product: Product): void {
    void this.router.navigate(['/products', product.id]);
  }

  imageSrc(product: Product): string | null {
    const local = product.images.find((image) => image.path !== null);
    return local ? `/images/${local.path}` : null;
  }

  private validNumber(value: number | null): number | null {
    return value !== null && Number.isFinite(value) ? value : null;
  }
}
