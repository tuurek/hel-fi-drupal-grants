// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

/**
 * Login with different user roles(considered: "administrator" and "editor")
 */
Cypress.Commands.add('login', (type) => {
    cy.session(type, () => {

        cy.visit('/fi')
        cy.get('button.eu-cookie-compliance-default-button').click()

        let perms = {};
        switch (type) {
            case 'admin':
                perms = {
                    name: "cypress_user@testi.com",
                    pass: "cypress_user@testi.com",
                    // name: Cypress.env('cyAdminUser'),
                    // pass: Cypress.env('cyAdminPassword'),
                };
                break;
            case 'editor':
                perms = {
                    name: "cypress_user@testi.com",
                    pass: "cypress_user@testi.com",
                    // name: Cypress.env('cyAdminUser'),
                    // pass: Cypress.env('cyAdminPassword'),
                };
                break;
        }
        cy.request({
            method: 'POST',
            url: '/user/login',
            form: true,
            body: {
                ...perms,
                form_id: 'user_login_form',
            },
        });
    })

});
