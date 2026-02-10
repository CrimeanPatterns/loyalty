var plugin = {

    hosts: {
        'www.azamara.com': true,
    },

    getStartingUrl: function (params) {
        return "https://www.azamara.com/account/upcoming-cruises";
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
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 1000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href="/logout"]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[action *= "/login"]').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.filter($('div:contains("Azamara Circle #")').next('div:contains("' + account.properties.ClubNumber + '")').text());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.ClubNumber) != 'undefined')
            && (account.properties.ClubNumber !== '')
            && number
            && (number === account.properties.ClubNumber) );
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href="/logout"]:visible').get(0).click();
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
        let form = $('form[action *= "/login"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "identifier"]').val(params.account.login);
        util.sendEvent(form.find('input[name = "identifier"]').get(0), 'input');
        form.find('input[name = "credentials.passcode"]').val(params.account.password);
        util.sendEvent(form.find('input[name = "credentials.passcode"]').get(0), 'input');
        provider.setNextStep('checkLoginErrors', function () {
            provider.eval('$(\'input[value = "Sign in"]\').get(0).click();');
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('p[role="alert"]:visible:eq(0), div[role="alert"]');

        if (errors.length > 0 && util.filter(errors.text()) !== "") {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            plugin.toItineraries(params);
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('.reservation-number:contains("' + confNo + '")').closest('.cruise-container').find('a.btn-reservation:contains("Manage my Reservation")');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
            }// if (link.length > 0)
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
