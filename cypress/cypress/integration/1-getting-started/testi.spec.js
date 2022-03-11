
describe('Site load, cookies, login', () => {
    beforeEach(function () {
        cy.login('admin')
        cy.visit('/fi/grants-profile')

        cy.get('#edit-company-select').select('4015026-5')
        cy.get('#edit-submit').click()
    });

    it('Redirect to company selection', () => {

    })


    it('Fill yleisavustushakemus', () => {
        cy.visit('/fi/form/yleisavustushakemus')
    })
})
