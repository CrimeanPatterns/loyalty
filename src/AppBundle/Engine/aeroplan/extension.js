var plugin = {

    hosts: {
        'www.aircanada.com': true,
    },

    getStartingUrl: function (params) {
        return "https://www.aircanada.com/aeroplan/member/dashboard?lang=en-CA";
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
                        plugin.logout();
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Sign out")').length > 0 && $('div.points:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#gigya-login-form:visible').length > 0 && $('input[value = "Sign in"]').length) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.filter($('div.aeroplan:eq(0)').text()).replaceAll(' ', '');
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && number
            && (number === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Sign out")').get(0).click();
        });
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook';
            });
            setTimeout(function() {
                plugin.getConfNoItinerary(params);
            }, 2000);
            return;
        }

        let form = $('form#gigya-login-form');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        let login = params.account.login.replace(/\s/g, "");
        util.setInputValue( form.find('input[name = "username"]'), login);
        util.setInputValue( form.find('input[name = "password"]'), params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('input[value = "Sign in"]').get(0).click();
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 10000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('.gigya-error-msg-active:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== "") {
            provider.setError(util.filter(errors.text()));
            return;
        }
        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.aircanada.com/ca/en/aco/home.html#/retrievepnr';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        let confNo = params.account.properties.confirmationNumber.toUpperCase();
        let link = $('div[id *= "booking_"]').find('span.booking-refrence:contains("' + confNo + '")');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function () {
                link.get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.itineraryNotFound);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        let properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'form.bkmg-my-bookings-form',
            success: function(form) {
                let input1 = form.find('input[name = "bkmgMyBookings_bookingRefNumber"]');
                let input2 = form.find('input[name = "bkmgMyBookings_lastName"]');
                input1.val(properties.ConfNo);
                input2.val(properties.LastName);
                util.sendEvent(input1.get(0), 'input');
                util.sendEvent(input2.get(0), 'input');
                provider.setNextStep('itLoginComplete', function () {
                    form.find('button[type = "submit"]:visible').click();
                });
            },
            fail: function() {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            }
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

};
