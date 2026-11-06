import { defineConfig } from 'cypress';

export default defineConfig({
  e2e: {
    // По умолчанию — docker-стек (make up, порт 80).
    // Против локального dev (ng serve + php -S) запускать:
    //   CYPRESS_BASE_URL=http://localhost:4200 npx cypress run
    baseUrl: process.env['CYPRESS_BASE_URL'] ?? 'http://localhost',
    supportFile: false,
    video: false,
    defaultCommandTimeout: 10000,
  },
});
