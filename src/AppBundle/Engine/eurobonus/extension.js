var plugin = {

    hosts: {
        'sso.scandinavian.net': true,
        'www.flysas.com'      : true,
        'auth.flysas.com'     : true,
    },

    getStartingUrl: function (params) {
        return "https://www.flysas.com/en/profile/";
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else {
                    const loginBtn = $('button[element="login-btn"]');

                    if (loginBtn.length > 0) {
                        return provider.setNextStep('login', function () {
                            loginBtn.click();
                        });
                    }

                    plugin.login(params);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                var login = $('a#user-profile');
                if (login.length > 0)
                    login.click();
            }
            if (isLoggedIn === null && counter > 30) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (
            $('button[element="login-btn"]').find('div[element="login-user"]').text() !== ''
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if (
            $('button[element="login-btn"]').find('span:contains("Login")').length > 0
            || $('form:has(input[name="username"]):visible').length > 0
        ) {
            browserAPI.log("not logged in");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $('span[element="ebNumber"]').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('div[element="login-user"]:visible').get(0).click();
            setTimeout(function () {
                $('a:contains("Log out")').get(0).click();
                setTimeout(function () {
                    plugin.start(params);
                }, 1000);
            }, 1000);
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

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            plugin.goToItinerariesPAge(params, 'getConfNoItinerary');
            return;
        }// if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0)

        let form = $('form:has(input[name="username"]):visible')

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }// if (form.length > 0)

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "username"]').val(params.account.login).change();
        form.find('input[name = "password"]').val(params.account.password).change();
        provider.setNextStep('checkLoginErrors', function () {
            setTimeout(function () {
                form.find('button[name="action"]:visible').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000);
            }, 1000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        let errors = $('div[class *= "-notification-error"]:visible div[element="content"], .ulp-input-error-message:visible');

        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            plugin.goToItinerariesPAge(params, 'toItineraries');
            return;
        }
        provider.complete();
    },

    goToItinerariesPAge: function (params, step) {
        provider.setNextStep(step, function () {
            document.location.href = 'https://www.flysas.com/managemybooking';
        });
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('div[class*="FlightBookingBox__"] strong:contains("' + confNo + '")');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.click();
                });
            }// if (link.length > 0)
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
        var properties = params.account.properties.confFields;
        var form = $('div#my-checkin form:has(input[name = "bookingReference"])');
        if (form.length > 0) {
            form.find('input[name = "bookingReference"]').val(properties.ConfNo);
            util.sendEvent(form.find('input[name = "bookingReference"]').get(0), 'input');
            form.find('input[name = "lastName"]').val(properties.LastName).change();
            util.sendEvent(form.find('input[name = "lastName"]').get(0), 'input');
            provider.setNextStep('itLoginComplete', function () {
                setTimeout(function () {
                    form.find('#CheckinSearchBtn').get(0).click();
                }, 1000)
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }
};
