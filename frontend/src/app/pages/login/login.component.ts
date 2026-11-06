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
import { MatIconModule } from '@angular/material/icon';
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
    MatIconModule,
    MatProgressBarModule,
  ],
  template: `
    <div class="login-wrap">
      <mat-card class="login-card">
        <mat-card-content>
          <div class="brand-mark">
            <mat-icon>inventory_2</mat-icon>
          </div>
          <h1 class="login-title">Вход</h1>
          <p class="login-subtitle">Импорт товаров</p>
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

            <button class="login-submit" mat-raised-button color="primary" type="submit" [disabled]="form.invalid || loading">
              Войти
            </button>
          </form>
        </mat-card-content>
      </mat-card>
    </div>
  `,
  styles: [
    `
      .login-wrap {
        min-height: calc(100vh - 120px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px 16px;
      }
      .login-card {
        width: 100%;
        max-width: 400px;
        padding: 12px 8px;
      }
      .brand-mark {
        width: 56px;
        height: 56px;
        margin: 8px auto 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 16px;
        background: linear-gradient(135deg, #30afb8 0%, #38b0df 100%);
        box-shadow: 0 6px 16px rgba(48, 175, 184, 0.35);
      }
      .brand-mark mat-icon {
        color: #ffffff;
        font-size: 28px;
        width: 28px;
        height: 28px;
      }
      .login-title {
        margin: 0;
        text-align: center;
        font-family: 'Montserrat', 'Inter', sans-serif;
        font-size: 26px;
        font-weight: 700;
      }
      .login-subtitle {
        margin: 4px 0 20px;
        text-align: center;
        color: rgba(76, 88, 103, 0.75);
      }
      .login-form {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-top: 12px;
      }
      .login-submit {
        margin-top: 12px;
        padding: 22px 0;
        font-size: 15px;
      }
      .login-error {
        color: #d64541;
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
