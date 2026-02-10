var plugin = {
    keepTabOpen: true,
    hideOnStart: true,
    hosts: {
        'exxonandmobilrewardsplus.com': true,
        'rewards.exxon.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://rewards.exxon.com/profile/details';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else {
                    plugin.login(params);
                }

            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("lastPage");

                let error = $('p:contains("Something has gone wrong unexpectedly. We love to solve problems,"):visible');
                if (error.length > 0) {
                    provider.setError(error.text(), true);
                    return
                }

                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form.form-login:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('li[class = "logginout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp($('p.confirm-card-number:contains("Card number:"):visible').text(), /:\s*([\d\s]{8,})/);
        browserAPI.log("number: " + number);
        return typeof (account.properties) != 'undefined'
            && typeof (account.properties.CardNumber) != 'undefined'
            && account.properties.CardNumber !== ''
            && number === account.properties.CardNumber;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('li[class = "logginout"] > a').get(0).click();
            setTimeout(function () {
                plugin.start(params);
            }, 3000);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form.form-login');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }// if (form.length > 0)

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "email"]').val(params.account.login);
        util.sendEvent(form.find('input[name = "email"]').get(0), 'input');

        form.find('input[name = "password"]').val(params.account.password);
        util.sendEvent(form.find('input[name = "password"]').get(0), 'input');


        provider.setNextStep('checkLoginErrors', function () {
            form.next('.btn-login').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        browserAPI.log('location: ' + document.location.href);
        provider.logBody("checkLoginErrorsPage");
        let errors = $('p.alert-error:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            browserAPI.log("[Error]: " + errors.text());
            provider.logBody("errorPage");
            if (
                errors.text().indexOf('Please contact our Customer Service Desk at 1-888-739-2730') !== -1
                // Something has gone wrong unexpectedly. We love to solve problems, so contact our customer service center at 888-739-2730 and we’ll get you back on track.
                || errors.text().indexOf('Something has gone wrong unexpectedly.') !== -1
                || errors.text().indexOf('Your session has expired.') !== -1
            ) {
                provider.setError([errors.text(), util.errorCodes.providerError], true);
                return;
            }
            if (
                util.filter(errors.text()) === 'The email or password you entered is invalid.'
                || util.filter(errors.text()) === 'Oops, looks like your email or password is invalid. Try again.'
            ) {
                provider.setError(errors.text(), true);
                return;
            }
            provider.complete();
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (params.autologin) {
            browserAPI.log("autologin only");
            provider.complete();
            return;
        }

        if ($('strong:contains("Confirm your identity"):visible').length) {

            if (!provider.isMobile) {
                provider.setError(['It seems that Exxon Mobil Rewards+ needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Exxon Mobil Rewards+ authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                return;
            }

            provider.command('show', function () {
                provider.showFader('Message from AwardWallet: In order to log in into this account please identify this device and click the “Submit” button. Once logged in, sit back and relax, we will do the rest.', true);/*review*/

                provider.setNextStep('parse', function () {
                    browserAPI.log("waiting answers...");
                    let counter = 0;
                    let waitingAnswers = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        let error = $('p.alert-error:visible');

                        if (
                            (error.length > 0 && util.filter(error.text()) !== '')
                            || counter > 180
                        ) {
                            clearInterval(waitingAnswers);
                            provider.setError(['Message from AwardWallet: In order to log in into this account please identify this device and click the “Submit” button. Once logged in, sit back and relax, we will do the rest.', util.errorCodes.providerError], true);
                            return;
                        }// if (error.length > 0 && error.text().trim() != '')

                        if ($('li[class = "logginout"]').length > 0) {
                            clearInterval(waitingAnswers);
                            plugin.parse(params);
                        }

                        counter++;
                    }, 500);
                });
            });
            return;
        }// if ($('strong:contains("Confirm your identity"):visible').length)

        if (document.location.href.indexOf('/profile/details') === -1) {
            provider.setNextStep('parse', function () {
                document.location.href = 'https://rewards.exxon.com/profile/details';
            });
            return;
        }

        plugin.parse(params);
    },

    parse: function (params) {
        browserAPI.log("parse");
        provider.logBody("parsePage");
        provider.updateAccountMessage();
        let data = {};
        // Name
        let name = $('input#userName');
        if (name.length > 0) {
            name = util.beautifulName(name.val());
            browserAPI.log("Name: " + name);
            data.Name = name;
        } else
            browserAPI.log("Name is not found");

        const cardNumber = util.findRegExp($('p.confirm-card-number:contains("Card number:"):visible').text(), /:\s*([\d\s]{8,})/);
        if (cardNumber) {
            browserAPI.log("CardNumber: " + cardNumber);
            data.CardNumber = cardNumber.replaceAll(' ', '');
        } else
            browserAPI.log("CardNumber is not found");

        // save data
        params.data.properties = data;
        provider.saveTemp(params.data);

        provider.setNextStep('parseBalance', function () {
            document.location.href = 'https://rewards.exxon.com/points/activity';
        });
    },

    parseBalance: function (params) {
        browserAPI.log("parseBalance");
        provider.updateAccountMessage();
        var data = params.data.properties;
        // Balance - Points Available
        var balance = $('p:contains("Points available")').prev('h2');
        if (balance.length > 0) {
            balance = balance.text();
            browserAPI.log("Balance: " + balance);
            data.Balance = balance;
        }
        // Save properties
        params.account.properties = data;
        provider.saveProperties(params.account.properties);
        provider.complete();
    }
};