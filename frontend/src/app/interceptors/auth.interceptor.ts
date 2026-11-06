import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';

/**
 * Добавляет JWT-заголовок; 401 → разлогин + редирект на /login,
 * 5xx → глобальный SnackBar с ошибкой.
 */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const snackBar = inject(MatSnackBar);

  const token = auth.token;
  const authReq = token
    ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } })
    : req;

  return next(authReq).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        if (error.status === 401) {
          auth.logout();
          void router.navigate(['/login']);
        } else if (error.status >= 500) {
          snackBar.open('Ошибка сервера. Попробуйте позже.', 'Закрыть', {
            duration: 5000,
          });
        }
      }

      return throwError(() => error);
    }),
  );
};
