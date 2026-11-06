import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { LoginResponse, User } from '../models/auth.model';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private static readonly TOKEN_KEY = 'products-import-token';
  private static readonly USER_KEY = 'products-import-user';

  constructor(private readonly http: HttpClient) {}

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>('/api/auth/login', { email, password })
      .pipe(
        tap((response) => {
          localStorage.setItem(AuthService.TOKEN_KEY, response.token);
          localStorage.setItem(AuthService.USER_KEY, JSON.stringify(response.user));
        }),
      );
  }

  logout(): void {
    localStorage.removeItem(AuthService.TOKEN_KEY);
    localStorage.removeItem(AuthService.USER_KEY);
  }

  get token(): string | null {
    return localStorage.getItem(AuthService.TOKEN_KEY);
  }

  get currentUser(): User | null {
    const raw = localStorage.getItem(AuthService.USER_KEY);
    if (!raw) {
      return null;
    }
    try {
      return JSON.parse(raw) as User;
    } catch {
      return null;
    }
  }

  isAuthenticated(): boolean {
    return this.token !== null;
  }
}
