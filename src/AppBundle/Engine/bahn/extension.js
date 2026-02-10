var plugin = {

    hosts: {
        'www.bahn.de': true,
        'fahrkarten.bahn.de': true,
        'accounts.bahn.de': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        let link = 'https://fahrkarten.bahn.de/privatkunde/start/start.post?scope=login';
        if (params.account.login2 === 'Business') link = 'https://fahrkarten.bahn.de/grosskunde/start/kmu_start.post?scope=login#stay';
        return link;
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
                    plugin.login(params);
            }

            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }

            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");

        if ($('#kc-form-login:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('.logout a').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $('#kundennummer').text();
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.AccountNumber) != 'undefined')
                && (account.properties.AccountNumber !== '')
                && number
                && (number === account.properties.AccountNumber));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.querySelector('.logout a').click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");

        if (
            typeof (params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary');
            document.location.href = 'https://fahrkarten.bahn.de/privatkunde/start/start.post?from_page=meinebahn&scope=bahnatsuche#stay';
            return;
        }

        let form = $('#kc-form-login');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('#username').val(params.account.login);
        form.find('#password').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            if (form.find('#hcaptcha-container').length > 0) {
                provider.reCaptchaMessage();
                let counter = 0;
                let login = setInterval(function () {
                    browserAPI.log("waiting... " + counter);
                    let errors = form.find('.message-error .kc-feedback-text:visible');
                    if (errors.length > 0 && util.filter(errors.text()) !== '') {
                        clearInterval(login);
                        plugin.checkLoginErrors(params);
                    }
                    if (counter > 120) {
                        clearInterval(login);
                        provider.setError(util.errorMessages.captchaErrorMessage, true);
                    }
                    counter++;
                }, 1000);
            }
            else {
                browserAPI.log("captcha is not found");
                form.find('#kc-login').get(0).click();
            }
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('.message-error .kc-feedback-text:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (typeof (params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.querySelector('a[href*="buchungsrueckschau/brs_uebersicht.go"]').click();
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        let counter = 0;
        let toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let link = $('a[href *= "' + params.account.properties.confirmationNumber + '"]');
            browserAPI.log('link ' + link);

            if (link.length) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
                return;
            }

            if (counter > 20) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }

            counter++;
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        let properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'form#formular',
            success: function () {
                let form = $('form#formular');
                form.find('input#auftragsnr').val(properties.ConfNo);
                form.find('input#reisenderNachname').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function () {
                    document.getElementById('button.suchen').click();
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            },
            timeout: 10
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};