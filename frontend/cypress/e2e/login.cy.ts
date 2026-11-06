describe('Аутентификация', () => {
  it('редирект неавторизованного на /login и вход', () => {
    cy.visit('/products');
    cy.url().should('include', '/login');

    cy.get('input[type="email"]').type('admin@example.com');
    cy.get('input[type="password"]').type('admin123');
    cy.contains('button', 'Войти').click();

    cy.url().should('include', '/products');
    cy.contains('Товары').should('be.visible');
  });

  it('неверный пароль показывает ошибку', () => {
    cy.visit('/login');
    cy.get('input[type="email"]').type('admin@example.com');
    cy.get('input[type="password"]').type('wrong-password');
    cy.contains('button', 'Войти').click();
    cy.contains('Неверный email или пароль').should('be.visible');
  });
});
