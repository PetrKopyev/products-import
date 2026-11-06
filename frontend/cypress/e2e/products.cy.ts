describe('Список и карточка товаров', () => {
  beforeEach(() => {
    cy.visit('/login');
    cy.get('input[type="email"]').type('admin@example.com');
    cy.get('input[type="password"]').type('admin123');
    cy.contains('button', 'Войти').click();
  });

  it('список с пагинацией и переход на карточку', () => {
    cy.visit('/products');

    cy.get('[data-cy="products-table"] tbody tr').should('have.length.at.least', 1);
    cy.get('.mat-mdc-paginator').should('be.visible');

    // Фильтр по названию
    cy.get('[data-cy="filter-name"]').type('Футболка');
    cy.get('[data-cy="products-table"] tbody tr', { timeout: 10000 }).should(
      'have.length.at.least',
      1,
    );

    // Карточка товара
    cy.get('[data-cy="products-table"] tbody tr').first().click();
    cy.url().should('match', /\/products\/\d+/);
    cy.contains('Дополнительные атрибуты').should('be.visible');
    cy.contains('Внешний код').should('be.visible');
  });

  it('фильтр по диапазону цены', () => {
    cy.visit('/products');
    cy.get('[data-cy="filter-price-min"]').type('1000');
    cy.get('[data-cy="filter-price-max"]').type('5000');
    cy.contains('button', 'Найти').click();
    cy.get('[data-cy="products-table"] tbody tr', { timeout: 10000 }).should(
      'have.length.at.least',
      1,
    );
  });
});
