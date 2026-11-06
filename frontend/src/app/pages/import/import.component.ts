import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatTableModule } from '@angular/material/table';
import { MatSnackBar } from '@angular/material/snack-bar';
import { HttpErrorResponse } from '@angular/common/http';
import { Subscription, interval, switchMap, takeWhile, throwError, timeout } from 'rxjs';
import { ImportJob } from '../../models/import.model';
import { ImportService } from '../../services/import.service';

@Component({
  selector: 'app-import',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
    MatTableModule,
  ],
  template: `
    <div class="page-container">
      <mat-card>
        <mat-card-header>
          <mat-card-title>Импорт товаров</mat-card-title>
          <mat-card-subtitle>Загрузите .xlsx файл с товарами</mat-card-subtitle>
        </mat-card-header>
        <mat-card-content>
          <input
            #fileInput
            type="file"
            accept=".xlsx"
            hidden
            (change)="onFileSelected($event)"
            data-cy="file-input"
          />
          <div class="upload-row">
            <button
              mat-raised-button
              color="primary"
              [disabled]="isRunning"
              (click)="fileInput.click()"
              data-cy="choose-file"
            >
              <mat-icon>upload_file</mat-icon>
              Выбрать файл
            </button>
            @if (selectedFile) {
              <span class="text-muted">{{ selectedFile.name }}</span>
              <button mat-flat-button color="accent" [disabled]="isRunning" (click)="upload()" data-cy="start-import">
                Загрузить
              </button>
            }
          </div>

          @if (job) {
            <div class="mt-16" data-cy="job-status">
              <p>
                Статус:
                <strong>{{ statusLabel(job.status) }}</strong>
                — обработано {{ job.processed }} из {{ job.total }}
              </p>
              <mat-progress-bar
                mode="determinate"
                [value]="job.total > 0 ? (job.processed / job.total) * 100 : 0"
              />
            </div>
          }

          @if (job && (job.status === 'completed' || job.status === 'failed')) {
            <div class="mt-16">
              @if (job.errors.length > 0) {
                <h3>Отчёт об ошибках ({{ job.errors.length }})</h3>
                <table mat-table [dataSource]="job.errors" class="full-width" data-cy="error-report">
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
              } @else {
                <p class="import-ok">
                  <mat-icon>check_circle</mat-icon> Импорт завершён без ошибок.
                </p>
              }
              <a mat-button routerLink="/products" data-cy="go-to-products">Перейти к товарам</a>
              <button mat-button (click)="reset()">Новый импорт</button>
            </div>
          }
        </mat-card-content>
      </mat-card>
    </div>
  `,
  styles: [
    `
      .upload-row {
        display: flex;
        gap: 16px;
        align-items: center;
        flex-wrap: wrap;
      }
      .import-ok {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #2e7d32;
      }
    `,
  ],
})
export class ImportComponent {
  protected selectedFile: File | null = null;
  protected job: ImportJob | null = null;
  protected isRunning = false;
  protected readonly errorColumns = ['row', 'externalCode', 'messages'];

  private pollSubscription: Subscription | null = null;

  constructor(
    private readonly importService: ImportService,
    private readonly snackBar: MatSnackBar,
  ) {}

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedFile = input.files?.[0] ?? null;
  }

  upload(): void {
    if (!this.selectedFile || this.isRunning) {
      return;
    }

    this.isRunning = true;
    this.job = null;

    this.pollSubscription = this.importService
      .upload(this.selectedFile)
      .pipe(
        switchMap((start) =>
          interval(800).pipe(
            switchMap(() => this.importService.getStatus(start.jobId)),
            takeWhile(
              (job) => job.status === 'pending' || job.status === 'processing',
              true, // включительно: отдаём финальный статус
            ),
            // страховка от бесконечного ожидания (worker недоступен и т.п.)
            timeout({
              first: 5 * 60 * 1000,
              with: () => throwError(() => new Error('import-timeout')),
            }),
          ),
        ),
      )
      .subscribe({
        next: (job) => {
          this.job = job;
          if (job.status === 'completed' || job.status === 'failed') {
            this.isRunning = false;
          }
        },
        error: (error: unknown) => {
          this.isRunning = false;
          const message =
            error instanceof Error && error.message === 'import-timeout'
              ? 'Импорт занимает слишком много времени. Проверьте статус позже.'
              : error instanceof HttpErrorResponse && error.error?.message
                ? error.error.message
                : 'Ошибка импорта';
          this.snackBar.open(message, 'Закрыть', { duration: 6000 });
        },
      });
  }

  reset(): void {
    this.pollSubscription?.unsubscribe();
    this.pollSubscription = null;
    this.job = null;
    this.selectedFile = null;
    this.isRunning = false;
  }

  protected statusLabel(status: ImportJob['status']): string {
    const labels: Record<ImportJob['status'], string> = {
      pending: 'в очереди',
      processing: 'обрабатывается',
      completed: 'завершён',
      failed: 'ошибка',
    };
    return labels[status];
  }
}
