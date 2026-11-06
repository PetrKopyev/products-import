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
        <span>Импорт товаров</span>
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
      .spacer {
        flex: 1 1 auto;
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
