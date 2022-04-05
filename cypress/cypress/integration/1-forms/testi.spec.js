
describe('Site load, cookies, login', () => {
    beforeEach(function () {
        cy.login('admin')
    });

    it('Redirect to company selection', () => {
        cy.visit('/fi/grants-profile')
        // cy.get('#edit-company-select').select('4015026-5')
        // cy.get('#edit-submit').click()
    })


    // it('Fill yleisavustushakemus', () => {
    //     cy.visit('/fi/form/yleisavustushakemus', {qs: {XDEBUG_SESSION:'PHPSTORM'}})
    //
    //     // general
    //     cy.get('[data-drupal-selector="edit-finalize-application"]').check()
    //     cy.get('[data-drupal-selector="edit-applicant-type"]').select('1')
    //     cy.get('[data-drupal-selector="edit-community-official-name-short"]').type('CypressTEST')
    //     cy.get('[data-drupal-selector="edit-homepage"]').type('https://www.yle.fi')
    //     cy.get('[data-drupal-selector="edit-email"]').type('testi@mailiosoite.com')
    //
    //     // company details
    //     cy.get('[data-drupal-selector="edit-contact-person-street"]').type('Katuosoite 12 a 34')
    //     cy.get('[data-drupal-selector="edit-contact-person-post-code"]').type('00870')
    //     cy.get('[data-drupal-selector="edit-contact-person-city"]').type('00870')
    //     cy.get('[data-drupal-selector="edit-contact-person-country"]').type('00870')
    //     cy.get('[data-drupal-selector="edit-contact-person"]').type('00870')
    //     cy.get('[data-drupal-selector="edit-contact-person-phone-number"]').type('00870')
    //
    //     // account number selection / value
    //     cy.get('[data-drupal-selector="edit-account-number-select"]').select(1).then((value) => {
    //         cy.get('[data-drupal-selector="edit-account-number"]').should('have.value',value.val())
    //     })
    //
    //     cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-name"]').type('00870')
    //
    //     cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-role"]').select(1).then((value) => {
    //         cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-role"]').should('have.value',value.val())
    //     })
    //
    //     cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-email"]').type('testi@maili.com')
    //     cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-phone"]').type('00870')
    //
    //     cy.get('[data-drupal-selector="edit-actions-wizard-next"]').click()
    //
    //     cy.get('[data-webform-page="1_hakijan_tiedot"]').should('have.class', 'is-complete')
    //     cy.get('[data-webform-page="2_avustustiedot"]').should('have.class', 'is-active')
    //
    //     cy.get('[data-drupal-selector="edit-acting-year"]').select(1).then((value) => {
    //         cy.get('[data-drupal-selector="edit-acting-year"]').should('have.value',new Date().getFullYear())
    //     })
    //
    //     cy.get('[data-drupal-selector="edit-subventions-items-0-item-amount"]').type('187,21')
    //     cy.get('[data-drupal-selector="edit-compensation-purpose"]').type('Lorem ipsum doler est')
    //
    //     cy.get('input#edit-olemme-saaneet-muita-avustuksia-kyll[value="Kyllä"]').check({force: true})
    //
    //     cy.get('[data-drupal-selector="edit-myonnetty-avustus-add-submit"]').click()
    //
    //     cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-issuer"]').select(2).then((value) => {
    //         cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-issuer"]').should('have.value',value.val())
    //     })
    //
    //     cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-issuer-name"]').type('Mikko Myöntäjä')
    //     cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-year"]').type('2012')
    //     cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-amount"]').type('345,65')
    //     cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-purpose"]').type('Lorem ipsum doler est')
    //
    //     cy.get('input#edit-olemme-hakeneet-avustuksia-muualta-kuin-helsingin-kaupungilta-kyll[value="Kyllä"]').check({force: true})
    //     cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-add-submit"]').click()
    //
    //     cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-issuer"]').select(2).then((value) => {
    //         cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-issuer"]').should('have.value',value.val())
    //     })
    //
    //     cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-issuer-name"]').type('Mikko Myöntäjä')
    //     cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-year"]').type('2012')
    //     cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-amount"]').type('345,65')
    //     cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-purpose"]').type('Lorem ipsum doler est')
    //
    //     cy.get('[data-drupal-selector="edit-benefits-loans"]').type('Lorem ipsum doler est')
    //     cy.get('[data-drupal-selector="edit-benefits-premises"]').type('Lorem ipsum doler est')
    //
    //     cy.get('input#edit-compensation-boolean-true[value="true"]').check({force: true})
    //
    //     cy.get('[data-drupal-selector="edit-compensation-explanation"]').type('Lorem ipsum doler est')
    //
    //     cy.get('[data-drupal-selector="edit-actions-wizard-next"]').click()
    //
    //     cy.get('[data-webform-page="1_hakijan_tiedot"]').should('have.class', 'is-complete')
    //     cy.get('[data-webform-page="2_avustustiedot"]').should('have.class', 'is-complete')
    //     cy.get('[data-webform-page="3_yhteison_tiedot"]').should('have.class', 'is-active')
    //
    //     cy.get('[data-drupal-selector="edit-community-purpose"]').type('Lorem ipsum doler est')
    //
    //     // cy.get('input#edit-community-practices-business-0[value="0"]').check({force: true})
    //     cy.get('input#edit-community-practices-business-no[value="No"]').check({force: true})
    //
    //
    //     cy.get('[data-drupal-selector="edit-fee-person"]').type('539,30')
    //     cy.get('[data-drupal-selector="edit-fee-community"]').type('539,30')
    //     cy.get('[data-drupal-selector="edit-fee-community"]').type('539,30')
    //     cy.get('[data-drupal-selector="edit-members-applicant-person-local"]').type('327')
    //     cy.get('[data-drupal-selector="edit-members-applicant-person-global"]').type('3227')
    //     cy.get('[data-drupal-selector="edit-members-applicant-community-local"]').type('227')
    //     cy.get('[data-drupal-selector="edit-members-applicant-community-global"]').type('227')
    //
    //     cy.get('[data-drupal-selector="edit-actions-wizard-next"]').click()
    //
    //     cy.get('[data-webform-page="1_hakijan_tiedot"]').should('have.class', 'is-complete')
    //     cy.get('[data-webform-page="2_avustustiedot"]').should('have.class', 'is-complete')
    //     cy.get('[data-webform-page="3_yhteison_tiedot"]').should('have.class', 'is-complete')
    //     cy.get('[data-webform-page="lisatiedot_ja_liitteet"]').should('have.class', 'is-active')
    //
    //     cy.get('[data-drupal-selector="edit-additional-information"]').type('Lorem ipsum doler est')
    //
    //     cy.get('[data-drupal-selector="edit-vahvistettu-tilinpaatos-attachment-upload"]').selectFile('./cypress/files/Testitiedosto3.pdf', {force: true}).then(() => {
    //         cy.get('[data-drupal-selector="edit-vahvistettu-tilinpaatos-isdeliveredlater"]').uncheck()
    //         cy.get('[data-drupal-selector="edit-vahvistettu-tilinpaatos-isincludedinotherfile"]').uncheck()
    //     })
    //
    //     cy.wait(2000)
    //
    //     cy.get('[data-drupal-selector="edit-vahvistettu-toimintakertomus-isdeliveredlater"]').check()
    //     cy.get('[data-drupal-selector="edit-vahvistettu-toimintakertomus-isincludedinotherfile"]').check()
    //
    //     cy.get('[data-drupal-selector="edit-vahvistettu-tilin-tai-toiminnantarkastuskertomus-attachment-upload"]').selectFile('./cypress/files/Testitiedosto1.docx', {force: true}).then(() => {
    //         cy.get('[data-drupal-selector="edit-vahvistettu-tilin-tai-toiminnantarkastuskertomus-isincludedinotherfile"]').uncheck()
    //         cy.get('[data-drupal-selector="edit-vahvistettu-tilin-tai-toiminnantarkastuskertomus-isdeliveredlater"]').uncheck()
    //     })
    //
    //     cy.wait(2000)
    //
    //     cy.get('[data-drupal-selector="edit-toimintasuunnitelma-attachment-upload"]').selectFile('./cypress/files/Testitiedosto2.doc', {force: true}).then(() => {
    //         cy.get('[data-drupal-selector="edit-toimintasuunnitelma-isdeliveredlater"]').uncheck()
    //         cy.get('[data-drupal-selector="edit-toimintasuunnitelma-isincludedinotherfile"]').uncheck()
    //     })
    //
    //     cy.wait(2000)
    //
    //     cy.get('[data-drupal-selector="edit-vuosikokouksen-poytakirja-isdeliveredlater"]').check()
    //     cy.get('[data-drupal-selector="edit-talousarvio-isdeliveredlater"]').check()
    //
    //     cy.get('[data-drupal-selector="edit-actions-preview-next"]').click()
    //
    //     cy.get('[data-webform-page="1_hakijan_tiedot"]').should('have.class', 'is-complete')
    //     cy.get('[data-webform-page="2_avustustiedot"]').should('have.class', 'is-complete')
    //     cy.get('[data-webform-page="3_yhteison_tiedot"]').should('have.class', 'is-complete')
    //     cy.get('[data-webform-page="lisatiedot_ja_liitteet"]').should('have.class', 'is-complete')
    //
    //     cy.get('[data-drupal-selector="edit-actions-submit"]').click()
    //
    //     cy.get('[data-drupal-selector="application-saved-successfully-link"]').should('exist').then(() => {
    //         cy.get('[data-drupal-selector="application-saved-successfully-link"]').click()
    //     })
    //
    // })


    it('Fill yleisavustushakemus 2', () => {
        cy.visit('/fi/form/yleisavustushakemus', {qs: {XDEBUG_SESSION:'PHPSTORM'}})

        // general
        cy.get('[data-drupal-selector="edit-finalize-application"]').check()
        cy.get('[data-drupal-selector="edit-applicant-type"]').select('1')
        cy.get('[data-drupal-selector="edit-community-official-name-short"]').type('CypressTEST')
        cy.get('[data-drupal-selector="edit-homepage"]').type('https://www.yle.fi')
        cy.get('[data-drupal-selector="edit-email"]').type('testi@mailiosoite.com')

        // company details
        cy.get('[data-drupal-selector="edit-contact-person-street"]').type('Katuosoite 12 a 34')
        cy.get('[data-drupal-selector="edit-contact-person-post-code"]').type('00870')
        cy.get('[data-drupal-selector="edit-contact-person-city"]').type('00870')
        cy.get('[data-drupal-selector="edit-contact-person-country"]').type('00870')
        cy.get('[data-drupal-selector="edit-contact-person"]').type('00870')
        cy.get('[data-drupal-selector="edit-contact-person-phone-number"]').type('00870')

        // account number selection / value
        cy.get('[data-drupal-selector="edit-account-number-select"]').select(1).then((value) => {
            cy.get('[data-drupal-selector="edit-account-number"]').should('have.value',value.val())
        })

        cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-name"]').type('00870')

        cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-role"]').select(1).then((value) => {
            cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-role"]').should('have.value',value.val())
        })

        cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-email"]').type('testi@maili.com')
        cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-phone"]').type('00870')

        cy.get('[data-drupal-selector="edit-actions-wizard-next"]').click()

        cy.get('[data-webform-page="1_hakijan_tiedot"]').should('have.class', 'is-complete')
        cy.get('[data-webform-page="2_avustustiedot"]').should('have.class', 'is-active')

        cy.get('[data-drupal-selector="edit-acting-year"]').select(1).then((value) => {
            cy.get('[data-drupal-selector="edit-acting-year"]').should('have.value',new Date().getFullYear())
        })

        cy.get('[data-drupal-selector="edit-subventions-items-0-item-amount"]').type('187,21')
        cy.get('[data-drupal-selector="edit-compensation-purpose"]').type('Lorem ipsum doler est')

        cy.get('input#edit-olemme-saaneet-muita-avustuksia-kyll[value="Kyllä"]').check({force: true})

        cy.get('[data-drupal-selector="edit-myonnetty-avustus-add-submit"]').click()

        cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-issuer"]').select(2).then((value) => {
            cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-issuer"]').should('have.value',value.val())
        })

        cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-issuer-name"]').type('Mikko Myöntäjä')
        cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-year"]').type('2012')
        cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-amount"]').type('345,65')
        cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-purpose"]').type('Lorem ipsum doler est')

        cy.get('input#edit-olemme-hakeneet-avustuksia-muualta-kuin-helsingin-kaupungilta-kyll[value="Kyllä"]').check({force: true})
        cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-add-submit"]').click()

        cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-issuer"]').select(2).then((value) => {
            cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-issuer"]').should('have.value',value.val())
        })

        cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-issuer-name"]').type('Mikko Myöntäjä')
        cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-year"]').type('2012')
        cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-amount"]').type('345,65')
        cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-purpose"]').type('Lorem ipsum doler est')

        cy.get('[data-drupal-selector="edit-benefits-loans"]').type('Lorem ipsum doler est')
        cy.get('[data-drupal-selector="edit-benefits-premises"]').type('Lorem ipsum doler est')

        cy.get('input#edit-compensation-boolean-true[value="true"]').check({force: true})

        cy.get('[data-drupal-selector="edit-compensation-explanation"]').type('Lorem ipsum doler est')

        cy.get('[data-drupal-selector="edit-actions-wizard-next"]').click()

        cy.get('[data-webform-page="1_hakijan_tiedot"]').should('have.class', 'is-complete')
        cy.get('[data-webform-page="2_avustustiedot"]').should('have.class', 'is-complete')
        cy.get('[data-webform-page="3_yhteison_tiedot"]').should('have.class', 'is-active')

        cy.get('[id="edit-community-purpose--description"]').should('exist')
        cy.get('[id="edit-community-practices-business--description"]').should('exist')



        cy.get('[data-drupal-selector="edit-fee-person"]').type('539,30')
        cy.get('[data-drupal-selector="edit-fee-community"]').type('539,30')
        cy.get('[data-drupal-selector="edit-fee-community"]').type('539,30')
        cy.get('[data-drupal-selector="edit-members-applicant-person-local"]').type('327')
        cy.get('[data-drupal-selector="edit-members-applicant-person-global"]').type('3227')
        cy.get('[data-drupal-selector="edit-members-applicant-community-local"]').type('227')
        cy.get('[data-drupal-selector="edit-members-applicant-community-global"]').type('227')

        cy.get('[data-drupal-selector="edit-actions-wizard-next"]').click()

        cy.get('[data-webform-page="1_hakijan_tiedot"]').should('have.class', 'is-complete')
        cy.get('[data-webform-page="2_avustustiedot"]').should('have.class', 'is-complete')
        cy.get('[data-webform-page="3_yhteison_tiedot"]').should('have.class', 'is-complete')
        cy.get('[data-webform-page="lisatiedot_ja_liitteet"]').should('have.class', 'is-active')

        cy.get('[data-drupal-selector="edit-additional-information"]').type('Lorem ipsum doler est')

        cy.get('[data-drupal-selector="edit-vahvistettu-tilinpaatos-attachment-upload"]').selectFile('./cypress/files/Testitiedosto3.pdf', {force: true}).then(() => {
            cy.get('[data-drupal-selector="edit-vahvistettu-tilinpaatos-isdeliveredlater"]').uncheck()
            cy.get('[data-drupal-selector="edit-vahvistettu-tilinpaatos-isincludedinotherfile"]').uncheck()
        })

        cy.wait(2000)

        cy.get('[data-drupal-selector="edit-vahvistettu-toimintakertomus-isdeliveredlater"]').check()
        cy.get('[data-drupal-selector="edit-vahvistettu-toimintakertomus-isincludedinotherfile"]').check()

        cy.get('[data-drupal-selector="edit-vahvistettu-tilin-tai-toiminnantarkastuskertomus-attachment-upload"]').selectFile('./cypress/files/Testitiedosto1.docx', {force: true}).then(() => {
            cy.get('[data-drupal-selector="edit-vahvistettu-tilin-tai-toiminnantarkastuskertomus-isincludedinotherfile"]').uncheck()
            cy.get('[data-drupal-selector="edit-vahvistettu-tilin-tai-toiminnantarkastuskertomus-isdeliveredlater"]').uncheck()
        })

        cy.wait(2000)

        cy.get('[data-drupal-selector="edit-toimintasuunnitelma-attachment-upload"]').selectFile('./cypress/files/Testitiedosto2.doc', {force: true}).then(() => {
            cy.get('[data-drupal-selector="edit-toimintasuunnitelma-isdeliveredlater"]').uncheck()
            cy.get('[data-drupal-selector="edit-toimintasuunnitelma-isincludedinotherfile"]').uncheck()
        })

        cy.wait(2000)

        cy.get('[data-drupal-selector="edit-vuosikokouksen-poytakirja-isdeliveredlater"]').check()
        cy.get('[data-drupal-selector="edit-talousarvio-isdeliveredlater"]').check()

        cy.get('[data-drupal-selector="edit-actions-preview-next"]').click()

        cy.get('[data-webform-page="1_hakijan_tiedot"]').should('have.class', 'is-complete')
        cy.get('[data-webform-page="2_avustustiedot"]').should('have.class', 'is-complete')
        cy.get('[data-webform-page="3_yhteison_tiedot"]').should('have.class', 'is-complete')
        cy.get('[data-webform-page="lisatiedot_ja_liitteet"]').should('have.class', 'is-complete')

        cy.get('[data-drupal-selector="edit-actions-submit"]').click()

        cy.get('[data-drupal-selector="application-saved-successfully-link"]').should('exist').then(() => {
            cy.get('[data-drupal-selector="application-saved-successfully-link"]').click()
        })

    })
})
