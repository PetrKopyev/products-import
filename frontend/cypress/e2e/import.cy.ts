describe('Импорт товаров', () => {
  beforeEach(() => {
    cy.visit('/login');
    cy.get('input[type="email"]').type('admin@example.com');
    cy.get('input[type="password"]').type('admin123');
    cy.contains('button', 'Войти').click();
    cy.url().should('include', '/products');
  });

  it('загружает xlsx и показывает прогресс до завершения', () => {
    cy.visit('/import');
    cy.get('[data-cy="choose-file"]').selectFile('fixtures/import-example.xlsx', {
      force: true,
    });
    cy.get('[data-cy="start-import"]').click();

    // Импорт асинхронный: ждём финального статуса (до 2 минут)
    cy.get('[data-cy="job-status"]', { timeout: 120000 }).contains('завершён');
    cy.contains('Перейти к товарам').click();

    cy.get('[data-cy="products-table"] tbody tr', { timeout: 30000 }).should(
      'have.length.at.least',
      40,
    );
  });

  it('отклоняет файл не-xlsx', () => {
    cy.visit('/import');
    cy.get('[data-cy="choose-file"]').selectFile(
      {
        contents: Cypress.Buffer.from('not a spreadsheet'),
        fileName: 'data.csv',
        mimeType: 'text/csv',
      },
      { force: true },
    );
    cy.get('[data-cy="start-import"]').click();
    cy.contains('Допустим только файл .xlsx').should('be.visible');
  });
});
