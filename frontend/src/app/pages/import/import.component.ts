import { CommonModule } from '@angular/common';
import { Component, ElementRef, ViewChild } from '@angular/core';
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
    <div class="page-container import-container">
      <mat-card>
        <mat-card-header>
          <mat-card-title>Импорт товаров</mat-card-title>
          <mat-card-subtitle>Загрузите .xlsx файл с товарами — данные попадут в очередь и появятся в каталоге</mat-card-subtitle>
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

          <div
            class="drop-zone"
            [class.drop-zone-active]="dragOver"
            [class.drop-zone-disabled]="isRunning"
            [class.has-file]="selectedFile !== null"
            (click)="!isRunning && fileInput.click()"
            (keydown.enter)="!isRunning && fileInput.click()"
            (dragover)="onDragOver($event)"
            (dragleave)="dragOver = false"
            (drop)="onDrop($event)"
            role="button"
            tabindex="0"
            data-cy="choose-file"
          >
            @if (selectedFile) {
              <button
                class="drop-remove"
                mat-icon-button
                type="button"
                aria-label="Убрать выбранный файл"
                (click)="clearFile($event)"
              >
                <mat-icon>close</mat-icon>
              </button>
              <div class="drop-icon drop-icon-ok"><mat-icon>check</mat-icon></div>
              <p class="drop-title">{{ selectedFile.name }}</p>
              <p class="drop-hint">Файл выбран ({{ formatSize(selectedFile.size) }}) — нажмите «Загрузить и импортировать»</p>
            } @else {
              <div class="drop-icon"><mat-icon>upload_file</mat-icon></div>
              <p class="drop-title">Перетащите .xlsx файл сюда</p>
              <p class="drop-hint">или нажмите, чтобы выбрать на компьютере</p>
            }
          </div>

          <div class="import-actions">
            <button
              mat-raised-button
              color="primary"
              [disabled]="!selectedFile || isRunning"
              (click)="upload()"
              data-cy="start-import"
            >
              <mat-icon>cloud_upload</mat-icon>
              Загрузить и импортировать
            </button>
            @if (isRunning) {
              <span class="text-muted import-running"><mat-icon class="spin">sync</mat-icon> Импорт выполняется…</span>
            }
          </div>

          @if (job) {
            <div class="job-card" data-cy="job-status">
              <div class="job-head">
                <span class="job-status-badge" [class]="'job-status-' + job.status">
                  <mat-icon>{{ jobIcon(job.status) }}</mat-icon> {{ statusLabel(job.status) }}
                </span>
                <span class="text-muted">
                  @if (job.status === 'pending' || job.status === 'processing') {
                    Обработано {{ job.processed }} из {{ job.total }}
                  } @else {
                    Обработано {{ job.processed }} из {{ job.total }} строк
                  }
                </span>
              </div>
              <mat-progress-bar
                mode="determinate"
                [value]="job.total > 0 ? (job.processed / job.total) * 100 : 0"
              />
              @if (job.status === 'completed' || job.status === 'failed') {
                <div class="job-result">
                  @if (job.errors.length > 0) {
                    <p class="job-result-title">
                      <mat-icon>warning</mat-icon> Импорт завершён с ошибками ({{ job.errors.length }})
                    </p>
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
                    <p class="job-ok">
                      <mat-icon>check_circle</mat-icon> Импорт завершён без ошибок.
                    </p>
                  }
                  <div class="job-actions">
                    <a mat-stroked-button routerLink="/products" data-cy="go-to-products">
                      <mat-icon>inventory_2</mat-icon> Перейти к товарам
                    </a>
                    <button mat-button (click)="reset()"><mat-icon>refresh</mat-icon> Новый импорт</button>
                  </div>
                </div>
              }
            </div>
          }
        </mat-card-content>
      </mat-card>
    </div>
  `,
  styles: [
    `
      .import-container {
        max-width: 860px;
      }
      .drop-zone {
        margin-top: 20px;
        padding: 40px 24px;
        border: 2px dashed #b9dfe3;
        border-radius: 16px;
        background: #f7fbfc;
        text-align: center;
        cursor: pointer;
        transition:
          border-color 0.15s ease,
          background 0.15s ease;
      }
      .drop-zone:hover,
      .drop-zone-active {
        border-color: #30afb8;
        background: #eff8f9;
      }
      /* Файл выбран — явное отличное состояние */
      .drop-zone.has-file {
        border-style: solid;
        border-color: #30afb8;
        background: linear-gradient(135deg, #eaf8f6 0%, #e6f4fb 100%);
        cursor: default;
      }
      .drop-zone.has-file:hover {
        background: linear-gradient(135deg, #e2f4f1 0%, #dceffa 100%);
      }
      .drop-remove {
        position: absolute;
        top: 8px;
        right: 8px;
        color: #4c5867;
      }
      .drop-zone {
        position: relative;
      }
      .drop-icon-ok {
        background: linear-gradient(135deg, #34b37a 0%, #2e9d6c 100%);
        box-shadow: 0 6px 16px rgba(52, 179, 122, 0.35);
      }
      .drop-zone-disabled {
        opacity: 0.6;
        pointer-events: none;
      }
      .drop-icon {
        width: 56px;
        height: 56px;
        margin: 0 auto 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: linear-gradient(135deg, #30afb8 0%, #38b0df 100%);
        box-shadow: 0 6px 16px rgba(48, 175, 184, 0.3);
      }
      .drop-icon mat-icon {
        color: #ffffff;
        font-size: 28px;
        width: 28px;
        height: 28px;
      }
      .drop-title {
        margin: 0 0 4px;
        font-family: 'Montserrat', 'Inter', sans-serif;
        font-size: 17px;
        font-weight: 600;
        color: #313b48;
        word-break: break-all;
      }
      .drop-hint {
        margin: 0;
        color: rgba(76, 88, 103, 0.75);
        font-size: 13px;
      }
      .import-actions {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-top: 20px;
      }
      .import-running {
        display: inline-flex;
        align-items: center;
        gap: 6px;
      }
      .import-running mat-icon {
        font-size: 18px;
        width: 18px;
        height: 18px;
        animation: spin 1.2s linear infinite;
      }
      @keyframes spin {
        to {
          transform: rotate(360deg);
        }
      }
      .job-card {
        margin-top: 24px;
        padding: 16px;
        border: 1px solid #dde4ea;
        border-radius: 14px;
        background: #fbfdfe;
      }
      .job-head {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
        flex-wrap: wrap;
      }
      .job-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 600;
        background: #e9edf1;
        color: #4c5867;
      }
      .job-status-badge mat-icon {
        font-size: 16px;
        width: 16px;
        height: 16px;
      }
      .job-status-processing,
      .job-status-pending {
        background: #d3eefb;
        color: #083344;
      }
      .job-status-completed {
        background: #d6f0e4;
        color: #14532d;
      }
      .job-status-failed {
        background: #fbe0df;
        color: #7f1d1d;
      }
      .job-result {
        margin-top: 14px;
      }
      .job-result-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0 0 10px;
        color: #92610e;
        font-weight: 600;
      }
      .job-ok {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #2e7d32;
        margin: 0;
      }
      .job-actions {
        display: flex;
        gap: 12px;
        margin-top: 14px;
        flex-wrap: wrap;
      }
    `,
  ],
})
export class ImportComponent {
  protected selectedFile: File | null = null;
  protected job: ImportJob | null = null;
  protected isRunning = false;
  protected dragOver = false;
  protected readonly errorColumns = ['row', 'externalCode', 'messages'];

  @ViewChild('fileInput') private readonly fileInput?: ElementRef<HTMLInputElement>;

  private pollSubscription: Subscription | null = null;

  constructor(
    private readonly importService: ImportService,
    private readonly snackBar: MatSnackBar,
  ) {}

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedFile = input.files?.[0] ?? null;
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.dragOver = true;
  }

  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragOver = false;
    if (this.isRunning) {
      return;
    }
    const file = event.dataTransfer?.files?.[0] ?? null;
    if (file && /\.xlsx$/i.test(file.name)) {
      this.selectedFile = file;
    } else if (file) {
      this.snackBar.open('Нужен файл с расширением .xlsx', 'Закрыть', { duration: 4000 });
    }
  }

  clearFile(event: MouseEvent): void {
    event.stopPropagation();
    this.selectedFile = null;
    if (this.fileInput) {
      this.fileInput.nativeElement.value = '';
    }
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

  protected formatSize(bytes: number): string {
    if (bytes < 1024) {
      return `${bytes} Б`;
    }
    if (bytes < 1024 * 1024) {
      return `${(bytes / 1024).toFixed(1)} КБ`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} МБ`;
  }

  protected jobIcon(status: ImportJob['status']): string {
    const icons: Record<ImportJob['status'], string> = {
      pending: 'schedule',
      processing: 'sync',
      completed: 'check_circle',
      failed: 'error',
    };
    return icons[status];
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
