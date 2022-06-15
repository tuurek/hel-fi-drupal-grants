
describe('Login, cookies, Profile, Applicant type, Yleisavustushakemus.', () => {

    let applicationNumber;
    let businessId = '4015026-5'

    before(function () {
        cy.visit('/fi').then(() => {
            cy.url().should('contain', '/avustukset')
            // cy.accept_cookies()
        })
    });

    beforeEach(() => {
        cy.login('admin')

    })

    it('Company selection', () => {
        cy.visit('/select-company')

        cy.get('[data-drupal-selector="edit-company-select"]').select('4015026-5')
        cy.get('[data-drupal-selector="edit-submit"]').click()
    })



    it('Grants profile basic', () => {
        cy.visit('/fi/grants-profile')
        
        cy.get('[data-drupal-selector="edit-foundingyear"]').clear().type('2020')
        cy.get('[data-drupal-selector="edit-companynameshort"]').clear().type('OTH-CPR')
        cy.get('[data-drupal-selector="edit-companyhomepage"]').clear().type('https://cypress.io')        
        cy.get('[data-drupal-selector="edit-businesspurpose"]').clear().type('Make me feel good ipsum...Cypress You are excelling at your hobby. You are precious to your boss and they think you are essential. Your team thinks you are incredible. You are valuable to your partner and they value your input. You are fantastic at your life. You are special to your team and they think you are essential. You are amazing at your work. Your colleague thinks you are stunning. You are valuable to your team and they never want to loose you.')

        cy.get('[data-drupal-selector="edit-submit"]').click().then((value) => {
            cy.expect('.hds-notification__body').to.include(businessId)
            cy.expect('.hds-notification__body').to.include('on tallennettu ja sitä voidaan käyttää hakemuksissa')
        })

    })

    it('Applicant type selection', () => {
        cy.visit('/fi/grants-profile/applicant-type')
        // cy.accept_cookies()
        cy.get('input#edit-applicant-type-registered-community[value="registered_community"]').check({force: true})
        cy.get('[data-drupal-selector="edit-submit"]').click()
    })

    // it('Grants profile address', () => {
    //     cy.visit('/fi/grants-profile')
        
    //     cy.get('#add-addres-modal-form-link').click().then((value) => {
    //         cy.get('[data-drupal-selector="edit-street"]').clear().type('Testikatuosoite 34 F 43')
    //         cy.get('[data-drupal-selector="edit-postcode"]').clear().type('00870')
    //         cy.get('[data-drupal-selector="edit-city"]').clear().type('Porvoo')
    //         cy.get('[data-drupal-selector="edit-country"]').clear().type('Suomi')

    //         cy.get('.ui-dialog button.form-submit').click()

    //     })

    // })


    
    it('Yleisavustushakemus', () => {
        cy.visit('/fi/form/yleisavustushakemus', {qs: {XDEBUG_SESSION: 'PHPSTORM'}})
        // cy.accept_cookies()
        // general
        // cy.get('[data-drupal-selector="edit-finalize-application"]').check()
        // cy.get('[data-drupal-selector="edit-community-official-name-short"]').type('CypressTEST')
        cy.get('[data-drupal-selector="edit-email"]').type('testi@mailiosoite.com')
    
        // company details
        cy.get('[data-drupal-selector="edit-contact-person"]').type('Calle Kontaktihenkilö')
        cy.get('[data-drupal-selector="edit-contact-person-phone-number"]').type('0509821346')
    
        // account number selection / value
        cy.get('[data-drupal-selector="edit-community-address-community-address-select"]').select(1).then((value) => {
            cy.get('[data-drupal-selector="edit-community-address-community-address-select"]').should('have.value', value.val())
        })
    
        // cy.get('[data-drupal-selector="edit-contact-person-street"]').type('Katuosoite 12 a 34')
        // cy.get('[data-drupal-selector="edit-contact-person-post-code"]').type('00870')
        // cy.get('[data-drupal-selector="edit-contact-person-city"]').type('00870')
        // cy.get('[data-drupal-selector="edit-contact-person-country"]').type('00870')
    
    
        // account number selection / value
        cy.get('[data-drupal-selector="edit-bank-account-account-number-select"]').selectNth(1).then((value) => {
            cy.get('[data-drupal-selector="edit-bank-account-account-number-select"]').should('have.value', value.val())
        })
    
        cy.get('[data-drupal-selector="edit-community-officials-items-0-item-community-officials-select"]').select(1).then((value) => {
            cy.get('[data-drupal-selector="edit-community-officials-items-0-item-community-officials-select"]').should('have.value', value.val())
        })
    
        // cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-name"]').type('00870')
        // cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-role"]').select(1).then((value) => {
        //     cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-role"]').should('have.value', value.val())
        // })
        // cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-email"]').type('testi@maili.com')
        // cy.get('[data-drupal-selector="edit-applicant-officials-items-0-item-phone"]').type('00870')
    
        cy.get('[data-drupal-selector="edit-actions-wizard-next"]').click()
    
        cy.get('[data-webform-page="1_hakijan_tiedot"]').should('have.class', 'is-complete')
        cy.get('[data-webform-page="2_avustustiedot"]').should('have.class', 'is-active')
    
        cy.get('[data-drupal-selector="edit-acting-year"]').select(1).then((value) => {
            cy.get('[data-drupal-selector="edit-acting-year"]').should('have.value', new Date().getFullYear())
        })
    
        cy.get('[data-drupal-selector="edit-subventions-items-0-item-amount"]').type('187,21')
        cy.get('[data-drupal-selector="edit-compensation-purpose"]').type('Lorem ipsum doler est')
    
        cy.get('input#edit-olemme-saaneet-muita-avustuksia-kyll[value="Kyllä"]').check({force: true})
    
        cy.get('[data-drupal-selector="edit-myonnetty-avustus-add-submit"]').click()
    
        cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-issuer"]').select(2).then((value) => {
            cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-issuer"]').should('have.value', value.val())
        })
    
        cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-issuer-name"]').type('Mikko Myöntäjä')
        cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-year"]').type('2012')
        cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-amount"]').type('345,65')
        cy.get('[data-drupal-selector="edit-myonnetty-avustus-items-0-item-purpose"]').type('Lorem ipsum doler est')
    
        cy.get('input#edit-olemme-hakeneet-avustuksia-muualta-kuin-helsingin-kaupungilta-kyll[value="Kyllä"]').check({force: true})
        cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-add-submit"]').click()
    
        cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-issuer"]').select(2).then((value) => {
            cy.get('[data-drupal-selector="edit-haettu-avustus-tieto-items-0-item-issuer"]').should('have.value', value.val())
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
    
        cy.get('[data-drupal-selector="edit-community-practices-business-1"]').check({force: true})
    
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
            // cy.get('[data-drupal-selector="application-saved-successfully-link"]').click()
            cy.get('#saved-application-number').invoke('text').then((value) => {
                applicationNumber = value
            })
        })
    })
    
    if(applicationNumber) {
        it('Load new Application', () => {
            cy.visit('/fi/grants-profile/applications/'. applicationNumber)
                .then(() => {
                    cy.get('h3#submission-application-number').should('have.text', applicationNumber);
                })
            // here we can and must test the values on the page.
        });
        
        
        it('Send message to application', () => {
          cy.visit('/fi/grants-profile/applications/'. applicationNumber)
                .then(() => {
                    cy.get('[data-drupal-selector="edit-message"]').type('Testiviesti 123')
                    cy.get('[data-drupal-selector="edit-submit"]').click()
                    cy.wait(2000)
                })
                .then(() => {
                    cy.get('table.message-table').then(() => {
                        console.log('table jeeejee')
                    })
                })
        
            // here we can and must test the values on the page.
        });
    }
})
