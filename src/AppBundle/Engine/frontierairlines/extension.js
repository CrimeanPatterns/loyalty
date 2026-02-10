var plugin = {

    hosts: {'www.flyfrontier.com': true, 'virtuallythere.com': true, 'booking.flyfrontier.com': true},
    clearCache: true,

    getStartingUrl: function () {
        return 'https://booking.flyfrontier.com/myFrontier/Profile';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
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
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('.user-logged-in:visible').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        if ($('.user-not-logged-in:visible').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.Number) !== 'undefined'
            && account.properties.Number != ''
            && $('.member-number:contains("' + account.properties.Number + '")').length;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('.logout-container .button:contains("LOG OUT")').get(0).click();
            setTimeout(function() {
                plugin.loadLoginForm();
            }, 5000);
        });
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("logging in");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.flyfrontier.com/travel/my-trips/";
            });
            return;
        }
        var logged = $('.user-not-logged-in:visible');
        if ($('.slider-visible:visible').length == 0 && logged.length > 0)
           logged.click();
        setTimeout(function () {
            var form = $('.slider-visible');
            if (form.length > 0) {
                form.find('input[name = "email"]').val(params.account.login).trigger('input');
                form.find('input[name = "password"]').val(params.account.password).trigger('input');
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
                'var email = document.querySelector(\'.slider-visible input[data-vv-scope="login-fields"][name = "email"]\');' +
                'email.dispatchEvent(createNewEvent(\'input\')); email.dispatchEvent(createNewEvent(\'change\'));' +
                'var pass = document.querySelector(\'.slider-visible input[data-vv-scope="login-fields"][name = "password"]\');' +
                'pass.dispatchEvent(createNewEvent(\'input\')); pass.dispatchEvent(createNewEvent(\'change\'));'
                );
                browserAPI.log("submitting saved credentials");
                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        form.find('.button[name = "submit"]').get(0).click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 5000);
                    }, 2000);
                });
            } else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('.login-error .error-message');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            if (document.location.href.indexOf('com/myFrontier/Profile') === -1) {
                provider.setNextStep('toItineraries', function () {
                    document.location.href = "https://booking.flyfrontier.com/myFrontier/Profile";
                });
            } else
                plugin.toItineraries(params);
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        //var confNo = params.account.properties.confirmationNumber;
        var viewBooking = $('.upcoming-trips-view-booking-button:contains("VIEW")');
        if (viewBooking.length) {
            provider.eval("var windowOpen = window.open; window.open = function(url){windowOpen(url, '_self');}");
            provider.setNextStep('itLoginComplete', function () {
                viewBooking.get(0).click();
                /*
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
                    'var email = document.querySelector(\'.upcoming-trips-view-booking-button\');' +
                    'email.dispatchEvent(createNewEvent(\'click\'));'
                );
                */
            });
        }
        else
            provider.setError(util.errorMessages.itineraryNotFound);
    },

    getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
        var properties = params.account.properties.confFields;
        setTimeout(function() {
            var form = $('#checkIn');
            if (form.length > 0) {
                form.find('input[name = "ConfirmationCode"]').val(properties.ConfNo);
                form.find('input[name = "passengerLastName"]').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function () {
                    form.find('#searchBookingButton').get(0).click();
                });
            }
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }, 2000)
    },

    itLoginComplete: function(params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }
};


