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

        cy.visit('/user/login')
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

Cypress.Commands.add('accept_cookies', (type) => {
    // cy.get('body')
    //     .then(($body) => {
    //         // synchronously query from body
    //         // to find which element was created
    //         if ($body.find('div.eu-cookie-compliance-banner').length) {
    //             // input was found, do something else here
    //             return 'banner'
    //         }
    //
    //         // else assume it was textarea
    //         return 'nobanner'
    //     })
    //     .then(($selector) => {
    //         console.log($selector)
    //         if ($selector === 'banner') {
    //             cy.get('button.eu-cookie-compliance-default-button').click()
    //         }
    //     })
    cy.get('button.eu-cookie-compliance-default-button').click()
});

Cypress.Commands.add(
    'selectNth',
    {prevSubject: 'element'},
    (subject, pos) => {
        cy.wrap(subject)
            .children('option')
            .eq(pos)
            .then(e => {
                cy.wrap(subject).select(e.val())
            })
    }
)

Cypress.SelectorPlayground.defaults({
    onElement: ($el) => {
        const customId = $el.attr('data-drupal-selector')

        if (customId) {
            return `[data-drupal-selector="${customId}"]`
        }
    },
})
