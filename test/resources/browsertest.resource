*** Settings ***
Documentation       A resource file with reusable keywords and variables.
Library             String

*** Variables ***
${TEST_BASEURL}     		%{TEST_BASEURL}	#Base url to test, set to environment variables when testing
