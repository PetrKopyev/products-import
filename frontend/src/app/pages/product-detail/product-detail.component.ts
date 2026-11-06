import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTableModule } from '@angular/material/table';
import { map, switchMap } from 'rxjs';
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
    MatChipsModule,
    MatProgressBarModule,
    MatProgressSpinnerModule,
    MatTableModule,
  ],
  template: `
    <div class="page-container">
      <a mat-button routerLink="/products"><mat-icon>arrow_back</mat-icon> К списку товаров</a>

      @if (product$ | async; as product) {
        <mat-card class="mt-16">
          <mat-card-header>
            <mat-card-title>{{ product.name }}</mat-card-title>
            <mat-card-subtitle>Внешний код: {{ product.externalCode }}</mat-card-subtitle>
          </mat-card-header>
          <mat-card-content>
            <div class="detail-grid">
              <div class="product-images">
                @for (image of product.images; track image.id) {
                  @if (image.path; as path) {
                    <img [src]="'/images/' + path" [alt]="product.name" loading="lazy" />
                  } @else {
                    <a [href]="image.url" target="_blank" rel="noopener">{{ image.url }}</a>
                  }
                }
              </div>
              <div>
                <p class="price">
                  {{ product.price | number: '1.2-2' }} ₽
                  @if (product.discount !== null) {
                    <mat-chip-set class="discount-chip">
                      <mat-chip highlighted>скидка {{ product.discount | number: '1.2-2' }}%</mat-chip>
                    </mat-chip-set>
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
                  <td mat-cell *matCellDef="let attribute">{{ attribute.key }}</td>
                </ng-container>
                <ng-container matColumnDef="value">
                  <th mat-header-cell *matHeaderCellDef>Значение</th>
                  <td mat-cell *matCellDef="let attribute">{{ attribute.value }}</td>
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
        grid-template-columns: 320px 1fr;
        gap: 24px;
        margin-bottom: 24px;
      }
      .product-images {
        display: flex;
        flex-direction: column;
        gap: 12px;
      }
      .product-images img {
        width: 100%;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
      }
      .price {
        font-size: 28px;
        display: flex;
        align-items: center;
        gap: 12px;
      }
      .description {
        white-space: pre-line;
      }
      .loading-box {
        display: flex;
        justify-content: center;
        padding: 60px 0;
      }
      @media (max-width: 760px) {
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

  protected readonly product$ = this.route.paramMap.pipe(
    map((params) => Number(params.get('id'))),
    switchMap((id) => this.productService.getProduct(id)),
    map((response) => response.data),
  );

  /** Статус последней задачи импорта (прогресс + отчёт об ошибках) */
  protected readonly job$ = this.importService.getLatest();

  constructor(
    private readonly route: ActivatedRoute,
    private readonly productService: ProductService,
    private readonly importService: ImportService,
  ) {}

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
