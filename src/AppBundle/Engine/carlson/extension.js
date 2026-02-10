var plugin = {

    hosts: {
        'www.radissonhotels.com': true,
        'www.radissonhotelsamericas.com': true,
        'www.radisson.com': true
    },

    domain : 'radissonhotels',

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {

        if (params.account.login2 === 'Americas') {
            plugin.domain = 'radissonhotelsamericas';
        }

        return 'https://www.' + plugin.domain + '.com/en-us/radisson-rewards/login';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
                        plugin.logout();
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
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form.js-landing-login-form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a.btn-logout:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp( $('span.member-number').text(), /(\d+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && number
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a.btn-logout:visible').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.' + plugin.domain + '.com/en-us/reservation/search';
            });
            return;
        }
        let form = $('form.js-landing-login-form');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        util.setInputValue( form.find('input[name = "user"]'), params.account.login );
        form.find('input[name = "user"]').focus();
        util.setInputValue( form.find('input[name = "password"]'), params.account.password );
        form.find('input[name = "password"]').focus();
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[type = "submit"]').click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");

        let errors = $('ul[role = "alert"]:visible > li:eq(0), div.modal-body--alert:visible strong.modal-body__title');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.' + plugin.domain + '.com/en-us/radisson-rewards/secure/my-reservations';
            });
            return;
        }

        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");

        let link = $('form:has(input[name="bookingId"][value = "' + params.account.properties.confirmationNumber + '"])');
        if (link.length > 0) {
            return provider.setNextStep('itLoginComplete', function () {
                link.submit();
            });
        }

        provider.setError(util.errorMessages.itineraryNotFound);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        let confFields = params.account.properties.confFields;
        let form = $('form#booking-management-search');

        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }

        form.find('input[name = "bookingId"]').val(confFields.ConfNo);
        form.find('input[name = "firstName"]').val(confFields.FirstName);
        form.find('input[name = "lastName"]').val(confFields.LastName);
        provider.setNextStep('itLoginComplete', function () {
            form.find('button[type="submit"]').get(0).click();
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log("getConfNoItinerary");
        provider.complete();
    }
};
