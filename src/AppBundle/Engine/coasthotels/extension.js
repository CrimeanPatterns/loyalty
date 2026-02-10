var plugin = {

    hosts: {
        'www.coasthotels.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.coasthotels.com/guest-portal/sign-in';
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
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('div#gms-form-login form:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a.js-gms-logout-action').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp($('p:has(strong:contains("Member Number:"))').text(), /:\s*([^<]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && number
            && (number == account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a.js-gms-logout-action').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        /*
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary');
            document.location.href = 'https://www.coasthotels.com';
            return;
        }
        */
        let form = $('div#gms-form-login form:visible');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }// if (form.length > 0)
        browserAPI.log("submitting saved credentials");
        form.find('input[id = "email"]').val(params.account.login);
        form.find('input[id = "password"]').val(params.account.password);

        // vue.js
        provider.eval(
            'function createNewEvent(eventName) {' +
            'var event;' +
            'if (typeof(Event) === "function") {' +
            '    event = new Event(eventName);' +
            '} else {' +
            '    event = document.createEvent("Event");' +
            '    event.initEvent(eventName, true, true);' +
            '}' +
            'return event;' +
            '}'+
            'var email = document.querySelector(\'input[id = "email"]\');' +
            'email.dispatchEvent(createNewEvent(\'input\')); email.dispatchEvent(createNewEvent(\'change\'));' +
            'var pass = document.querySelector(\'input[id = "password"]\');' +
            'pass.dispatchEvent(createNewEvent(\'input\')); pass.dispatchEvent(createNewEvent(\'change\'));'
        );

        provider.setNextStep('checkLoginErrors', function () {
            form.find('button.btn-secondary').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.error:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        /*
        if (typeof (params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.coasthotels.com/trips';
            });
            return;
        }
        */
        provider.complete();
    },

    /*
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
            }// if (link)
            if (counter > 20) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }// if (counter > 20)
            counter++;
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        let properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'form#findReservationForm',
            success: function () {
                let form = $('form#findReservationForm');
                form.find('input[name *= "ConfirmationNumber"]').val(properties.ConfNo);
                form.find('input[name *= "LastName"]').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function () {
                    $('input[name = "btnSubmit"]').get(0).click();
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
    */

};