import { CommonModule } from '@angular/common';
import { Component } from '@angular/core';
import {
  FormBuilder,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { HttpErrorResponse } from '@angular/common/http';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatProgressBarModule,
  ],
  template: `
    <div class="page-container login-container">
      <mat-card>
        <mat-card-header>
          <mat-card-title>Вход</mat-card-title>
          <mat-card-subtitle>Импорт товаров</mat-card-subtitle>
        </mat-card-header>
        <mat-card-content>
          @if (loading) {
            <mat-progress-bar mode="indeterminate" />
          }
          <form [formGroup]="form" (ngSubmit)="submit()" class="login-form">
            <mat-form-field appearance="outline" class="full-width">
              <mat-label>Email</mat-label>
              <input matInput type="email" formControlName="email" autocomplete="username" />
              @if (form.controls.email.hasError('required')) {
                <mat-error>Укажите email</mat-error>
              } @else if (form.controls.email.hasError('email')) {
                <mat-error>Некорректный email</mat-error>
              }
            </mat-form-field>

            <mat-form-field appearance="outline" class="full-width">
              <mat-label>Пароль</mat-label>
              <input matInput type="password" formControlName="password" autocomplete="current-password" />
              @if (form.controls.password.hasError('required')) {
                <mat-error>Укажите пароль</mat-error>
              }
            </mat-form-field>

            @if (error) {
              <p class="login-error">{{ error }}</p>
            }

            <button mat-raised-button color="primary" type="submit" [disabled]="form.invalid || loading">
              Войти
            </button>
          </form>
        </mat-card-content>
      </mat-card>
    </div>
  `,
  styles: [
    `
      .login-container {
        max-width: 420px;
        margin-top: 8vh;
      }
      .login-form {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-top: 12px;
      }
      .login-error {
        color: #c62828;
        margin: 0 0 8px;
      }
    `,
  ],
})
export class LoginComponent {
  protected loading = false;
  protected error: string | null = null;

  protected readonly form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
  });

  constructor(
    private readonly fb: FormBuilder,
    private readonly auth: AuthService,
    private readonly router: Router,
    private readonly route: ActivatedRoute,
  ) {}

  submit(): void {
    if (this.form.invalid) {
      return;
    }

    this.loading = true;
    this.error = null;

    const { email, password } = this.form.getRawValue();

    this.auth.login(email ?? '', password ?? '').subscribe({
      next: () => {
        const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl');
        void this.router.navigateByUrl(returnUrl ?? '/products');
      },
      error: (error: unknown) => {
        this.loading = false;
        this.error =
          error instanceof HttpErrorResponse && error.error?.message
            ? error.error.message
            : 'Ошибка входа. Проверьте соединение.';
      },
    });
  }
}
