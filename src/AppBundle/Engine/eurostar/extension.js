var plugin = {
    hosts: {
        'accounts.eurostar.com': true,
        'www.eurostar.com'     : true,
        'login.eurostar.com'   : true
    },

    getStartingUrl: function (params) {
        return 'https://www.eurostar.com/customer-dashboard/en?market=uk-en';
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
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                } else
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
        if ($('#main form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href*="/customer-dashboard/"]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('p:contains("Membership number") + p').text();
        browserAPI.log("number: " + number);
        return typeof account.properties !== 'undefined'
               && typeof account.properties.Number !== 'undefined'
               && account.properties.Number !== ''
               && number && number.replace(new RegExp(/\s+/, 'g'), '') === account.properties.Number;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            let logout = $('a[href*="/logout"]:visible');
            if (logout.length) {
                browserAPI.log("LoggedIn");
                logout.get(0).click();
                return true;
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        /*if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://www.eurostar.com/uk-en';
            });
            return;
        }*/

        const form = $('#main form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // reactjs
        provider.eval(
            "function triggerInput(selector, enteredValue) {\n" +
            "      let input = document.querySelector(selector);\n" +
            "      input.dispatchEvent(new Event('focus'));\n" +
            "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
            "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
            "      nativeInputValueSetter.call(input, enteredValue);\n" +
            "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
            "      input.dispatchEvent(inputEvent);\n" +
            "}\n" +
            "triggerInput('#main form input[autocomplete=\"username\"]', '" + params.account.login + "');\n" +
            "triggerInput('#main form input[autocomplete=\"current-password\"]', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            setTimeout(function () {
                form.find('button[type="submit"]').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000);
            }, 1000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div[data-testid="snackbar-error-message"]:visible, [data-testid="text"]:visible, div[role="alert"]:visible p:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.eurostar.com/customer-dashboard/en/bookings-upcoming?market=uk-en';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('a[href*="/booking?pnr=' + confNo + '"]');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

   /*getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form#manage-booking-form');
        if (form.length > 0) {
            form.find('input#manage-booking-form-booking-reference').val(properties.ConfNo);
            form.find('input#manage-booking-form-last-name').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function() {
                form.find('button[type="submit"]').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },*/


    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
