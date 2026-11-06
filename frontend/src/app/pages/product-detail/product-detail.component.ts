import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTableModule } from '@angular/material/table';
import { map, switchMap, tap } from 'rxjs';
import { Product, ProductImage } from '../../models/product.model';
import { ProductService } from '../../services/product.service';
import { ImportService } from '../../services/import.service';

@Component({
  selector: 'app-product-detail',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
    MatProgressSpinnerModule,
    MatTableModule,
  ],
  template: `
    <div class="page-container">
      <a mat-button routerLink="/products"><mat-icon>arrow_back</mat-icon> К списку товаров</a>

      @if (product$ | async; as product) {
        <mat-card class="mt-16">
          <mat-card-content>
            <div class="detail-grid">
              <div class="gallery">
                <div class="gallery-main">
                  @if (mainSrc(product); as src) {
                    <img [src]="src" [alt]="product.name" />
                  } @else {
                    <div class="gallery-empty">
                      <mat-icon>image</mat-icon>
                      <span>{{ product.images.length > 0 ? 'Фото не скачано' : 'Нет изображений' }}</span>
                    </div>
                  }
                  @if (localSources(product).length > 1) {
                    <button
                      class="gallery-nav prev"
                      mat-icon-button
                      aria-label="Предыдущее фото"
                      (click)="prev(product)"
                    >
                      <mat-icon>chevron_left</mat-icon>
                    </button>
                    <button
                      class="gallery-nav next"
                      mat-icon-button
                      aria-label="Следующее фото"
                      (click)="next(product)"
                    >
                      <mat-icon>chevron_right</mat-icon>
                    </button>
                  }
                </div>
                @if (localSources(product).length > 1) {
                  <div class="gallery-thumbs">
                    @for (src of localSources(product); track src) {
                      <button
                        type="button"
                        class="gallery-thumb"
                        [class.active]="$index === activeIndex"
                        [attr.aria-label]="'Фото ' + ($index + 1)"
                        (click)="activeIndex = $index"
                      >
                        <img [src]="src" [alt]="product.name" loading="lazy" />
                      </button>
                    }
                  </div>
                }
                @for (image of externalImages(product); track image.url) {
                  <a
                    class="gallery-external"
                    mat-stroked-button
                    [href]="image.url"
                    target="_blank"
                    rel="noopener"
                  >
                    <mat-icon>open_in_new</mat-icon> Открыть оригинал изображения
                  </a>
                }
              </div>

              <div class="info">
                <h1 class="product-title">{{ product.name }}</h1>
                <p class="product-code">
                  Внешний код: <span>{{ product.externalCode }}</span>
                </p>
                <p class="price-line">
                  <span class="price-value">{{ product.price | number: '1.2-2' }} ₽</span>
                  @if (product.discount !== null) {
                    <span class="discount-badge">скидка {{ product.discount | number: '1.0-0' }}%</span>
                  }
                </p>
                @if (product.description) {
                  <p class="description">{{ product.description }}</p>
                }
                <p class="text-muted">Обновлено: {{ product.updatedAt | date: 'medium' }}</p>
              </div>
            </div>

            <h3>Дополнительные атрибуты</h3>
            @if (product.attributes.length > 0) {
              <table mat-table [dataSource]="product.attributes" class="full-width attributes-table">
                <ng-container matColumnDef="key">
                  <th mat-header-cell *matHeaderCellDef>Поле</th>
                  <td mat-cell *matCellDef="let attribute" class="attr-key">{{ attribute.key }}</td>
                </ng-container>
                <ng-container matColumnDef="value">
                  <th mat-header-cell *matHeaderCellDef>Значение</th>
                  <td mat-cell *matCellDef="let attribute" class="attr-value">{{ attribute.value }}</td>
                </ng-container>
                <tr mat-header-row *matHeaderRowDef="attributeColumns"></tr>
                <tr mat-row *matRowDef="let row; columns: attributeColumns"></tr>
              </table>
            } @else {
              <p class="text-muted">Атрибуты отсутствуют.</p>
            }

            @if (job$ | async; as job) {
              <h3 class="mt-16">Статус импорта</h3>
              <p>
                Задача #{{ job.id }} — <strong>{{ statusLabel(job.status) }}</strong>,
                обработано {{ job.processed }} из {{ job.total }}
              </p>
              <mat-progress-bar
                mode="determinate"
                [value]="job.total > 0 ? (job.processed / job.total) * 100 : 0"
              />
              @if (job.errors.length > 0) {
                <p class="mt-16">Отчёт об ошибках ({{ job.errors.length }}):</p>
                <table mat-table [dataSource]="job.errors" class="full-width">
                  <ng-container matColumnDef="row">
                    <th mat-header-cell *matHeaderCellDef>Строка</th>
                    <td mat-cell *matCellDef="let element">{{ element.row }}</td>
                  </ng-container>
                  <ng-container matColumnDef="externalCode">
                    <th mat-header-cell *matHeaderCellDef>Внешний код</th>
                    <td mat-cell *matCellDef="let element">{{ element.externalCode ?? '—' }}</td>
                  </ng-container>
                  <ng-container matColumnDef="messages">
                    <th mat-header-cell *matHeaderCellDef>Ошибки</th>
                    <td mat-cell *matCellDef="let element">{{ element.messages.join('; ') }}</td>
                  </ng-container>
                  <tr mat-header-row *matHeaderRowDef="errorColumns"></tr>
                  <tr mat-row *matRowDef="let row; columns: errorColumns"></tr>
                </table>
              }
            }
          </mat-card-content>
        </mat-card>
      } @else {
        <div class="loading-box">
          <mat-spinner diameter="40" />
        </div>
      }
    </div>
  `,
  styles: [
    `
      .detail-grid {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: 32px;
        margin-bottom: 24px;
      }
      .gallery {
        display: flex;
        flex-direction: column;
        gap: 12px;
      }
      .gallery-main {
        position: relative;
        width: 100%;
        aspect-ratio: 1;
        border-radius: 14px;
        border: 1px solid #dde4ea;
        background: #ffffff;
        overflow: hidden;
      }
      .gallery-main img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        display: block;
      }
      .gallery-empty {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: #eef3f6;
        color: #9aa7b3;
      }
      .gallery-empty mat-icon {
        font-size: 44px;
        width: 44px;
        height: 44px;
      }
      .gallery-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(255, 255, 255, 0.92);
        box-shadow: 0 2px 8px rgba(49, 59, 72, 0.18);
        color: #313b48;
      }
      .gallery-nav:hover {
        background: #ffffff;
      }
      .gallery-nav.prev {
        left: 10px;
      }
      .gallery-nav.next {
        right: 10px;
      }
      .gallery-thumbs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
      }
      .gallery-thumb {
        width: 64px;
        height: 64px;
        padding: 0;
        border: 2px solid #dde4ea;
        border-radius: 10px;
        background: #ffffff;
        overflow: hidden;
        cursor: pointer;
        transition:
          border-color 0.15s ease,
          transform 0.15s ease;
      }
      .gallery-thumb:hover {
        transform: translateY(-2px);
      }
      .gallery-thumb.active {
        border-color: #30afb8;
        box-shadow: 0 0 0 2px rgba(48, 175, 184, 0.25);
      }
      .gallery-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
      }
      .gallery-external {
        align-self: flex-start;
      }
      .product-title {
        margin: 0 0 6px;
        font-family: 'Montserrat', 'Inter', sans-serif;
        font-size: 22px;
        font-weight: 700;
        line-height: 1.3;
      }
      .product-code {
        margin: 0 0 16px;
        color: rgba(76, 88, 103, 0.75);
      }
      .product-code span {
        color: #313b48;
        font-weight: 600;
      }
      .price-line {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin: 0 0 16px;
      }
      .price-value {
        font-family: 'Montserrat', 'Inter', sans-serif;
        font-size: 30px;
        font-weight: 700;
        color: #313b48;
      }
      .description {
        white-space: pre-line;
        line-height: 1.55;
      }
      .attr-key {
        color: rgba(76, 88, 103, 0.85);
        width: 40%;
      }
      .attr-value {
        font-weight: 600;
      }
      .loading-box {
        display: flex;
        justify-content: center;
        padding: 60px 0;
      }
      @media (max-width: 860px) {
        .detail-grid {
          grid-template-columns: 1fr;
        }
      }
    `,
  ],
})
export class ProductDetailComponent {
  protected readonly attributeColumns = ['key', 'value'];
  protected readonly errorColumns = ['row', 'externalCode', 'messages'];

  protected activeIndex = 0;

  protected readonly product$ = this.route.paramMap.pipe(
    map((params) => Number(params.get('id'))),
    switchMap((id) => this.productService.getProduct(id)),
    tap(() => {
      this.activeIndex = 0;
    }),
    map((response) => response.data),
  );

  /** Статус последней задачи импорта (прогресс + отчёт об ошибках) */
  protected readonly job$ = this.importService.getLatest();

  constructor(
    private readonly route: ActivatedRoute,
    private readonly productService: ProductService,
    private readonly importService: ImportService,
  ) {}

  /** Локальные изображения, доступные для просмотра в галерее */
  protected localSources(product: Product): string[] {
    return product.images.filter((image) => image.path !== null).map((image) => `/images/${image.path}`);
  }

  /** Изображения, которые не удалось скачать — осталась только исходная ссылка */
  protected externalImages(product: Product): ProductImage[] {
    return product.images.filter((image) => image.path === null);
  }

  protected mainSrc(product: Product): string | null {
    const sources = this.localSources(product);
    if (sources.length === 0) {
      return null;
    }
    return sources[this.activeIndex % sources.length];
  }

  protected prev(product: Product): void {
    const count = this.localSources(product).length;
    if (count > 0) {
      this.activeIndex = (this.activeIndex - 1 + count) % count;
    }
  }

  protected next(product: Product): void {
    const count = this.localSources(product).length;
    if (count > 0) {
      this.activeIndex = (this.activeIndex + 1) % count;
    }
  }

  protected statusLabel(status: 'pending' | 'processing' | 'completed' | 'failed'): string {
    const labels: Record<string, string> = {
      pending: 'в очереди',
      processing: 'обрабатывается',
      completed: 'завершён',
      failed: 'ошибка',
    };
    return labels[status] ?? status;
  }
}
