import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { AuthService } from './services/auth.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, MatToolbarModule, MatButtonModule, MatIconModule],
  template: `
    @if (auth.isAuthenticated()) {
      <mat-toolbar color="primary">
        <span class="brand-chip"><mat-icon>inventory_2</mat-icon></span>
        <span class="app-title">Импорт товаров</span>
        <span class="spacer"></span>
        <a mat-button routerLink="/import" routerLinkActive="active">
          <mat-icon>upload_file</mat-icon> Импорт
        </a>
        <a mat-button routerLink="/products" routerLinkActive="active">
          <mat-icon>inventory_2</mat-icon> Товары
        </a>
        <button mat-button (click)="logout()">
          <mat-icon>logout</mat-icon> Выйти
        </button>
      </mat-toolbar>
    }
    <router-outlet />
  `,
  styles: [
    `
      mat-toolbar {
        gap: 8px;
      }
      mat-toolbar .spacer {
        margin-right: 8px;
      }
      .spacer {
        flex: 1 1 auto;
      }
      .brand-chip {
        width: 34px;
        height: 34px;
        margin-right: 10px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        background: linear-gradient(135deg, #30afb8 0%, #38b0df 100%);
        box-shadow: 0 3px 8px rgba(48, 175, 184, 0.3);
      }
      .brand-chip mat-icon {
        color: #ffffff;
        font-size: 19px;
        width: 19px;
        height: 19px;
      }
      .app-title {
        font-family: 'Montserrat', 'Inter', sans-serif;
        font-weight: 700;
        font-size: 18px;
        letter-spacing: 0.01em;
      }
      a.active {
        background: rgba(255, 255, 255, 0.15);
      }
    `,
  ],
})
export class AppComponent {
  constructor(public readonly auth: AuthService) {}

  logout(): void {
    this.auth.logout();
    window.location.href = '/login';
  }
}
