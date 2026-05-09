describe('Autenticación Tests', () => {
  beforeEach(() => {
    cy.visit('/')
  })

  it('debe mostrar formulario de login', () => {
    cy.get('input[type="email"]').should('be.visible')
    cy.get('input[type="password"]').should('be.visible')
    cy.get('button[type="submit"]').should('contain', 'Login')
  })

  it('debe permitir login con credenciales válidas', () => {
    cy.get('input[type="email"]').type('admin@barberia.local')
    cy.get('input[type="password"]').type('password')
    cy.get('button[type="submit"]').click()
    
    cy.url().should('include', '/dashboard')
  })

  it('debe mostrar error con credenciales inválidas', () => {
    cy.get('input[type="email"]').type('invalid@email.com')
    cy.get('input[type="password"]').type('wrongpass')
    cy.get('button[type="submit"]').click()
    
    cy.get('.alert-error, [role="alert"]').should('be.visible')
  })
})
