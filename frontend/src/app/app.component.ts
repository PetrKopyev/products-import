import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatTabsModule } from '@angular/material/tabs';
import { AuthService } from './services/auth.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatToolbarModule,
    MatButtonModule,
    MatIconModule,
    MatMenuModule,
    MatTabsModule,
  ],
  template: `
    @if (auth.isAuthenticated()) {
      <mat-toolbar color="primary">
        <span class="brand-chip"><mat-icon>inventory_2</mat-icon></span>
        <span class="app-title">Импорт товаров</span>
        <span class="spacer"></span>
        <button mat-icon-button [matMenuTriggerFor]="userMenu" aria-label="Меню пользователя">
          <mat-icon>account_circle</mat-icon>
        </button>
        <mat-menu #userMenu="matMenu">
          <button mat-menu-item (click)="logout()">
            <mat-icon>logout</mat-icon>
            <span>Выйти</span>
          </button>
        </mat-menu>
      </mat-toolbar>
      <nav mat-tab-nav-bar mat-stretch-tabs="false" class="top-nav" [tabPanel]="tabPanel">
        @for (link of navLinks; track link.path) {
          <a
            mat-tab-link
            [routerLink]="link.path"
            routerLinkActive
            #rla="routerLinkActive"
            [active]="rla.isActive"
            [routerLinkActiveOptions]="{ exact: link.exact }"
          >
            <mat-icon>{{ link.icon }}</mat-icon> {{ link.label }}
          </a>
        }
      </nav>
      <mat-tab-nav-panel #tabPanel />
    }
    <router-outlet />
  `,
  styles: [
    `
      mat-toolbar .spacer {
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
      mat-toolbar button[mat-icon-button] {
        color: #4c5867;
      }
      .top-nav {
        background: #ffffff;
        border-bottom: 1px solid #e3e9ee;
      }
      .top-nav a[mat-tab-link] {
        color: #4c5867;
        font-weight: 500;
      }
      .top-nav a[mat-tab-link] mat-icon {
        margin-right: 6px;
        font-size: 19px;
        width: 19px;
        height: 19px;
      }
      .top-nav a[mat-tab-link].mdc-tab--active {
        color: #0e7c86;
      }
    `,
  ],
})
export class AppComponent {
  protected readonly navLinks = [
    { path: '/import', label: 'Импорт', icon: 'upload_file', exact: false },
    { path: '/products', label: 'Товары', icon: 'inventory_2', exact: false },
  ];

  constructor(public readonly auth: AuthService) {}

  logout(): void {
    this.auth.logout();
    window.location.href = '/login';
  }
}
