
var plugin = {
    // keepTabOpen: true,
    hosts: {
        'flydubai.com': true,
        'www.flydubai.com': true,
        'openrewards.flydubai.com': true,
        'flights.flydubai.com': true
    },

    getStartingUrl: function (params) {
        return 'https://openrewards.flydubai.com/en/';
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
                        provider.complete();
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
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[action *= "/en/login"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('span.ffp-db--code').text(), /(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://openrewards.flydubai.com/en/comp/account/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId == 0       ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = 'https://www.flydubai.com/en/#';
            });
            return;
        }

        var form = $('form[action *= "/en/login"]:first');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "Username"]').val(params.account.login);
            form.find('input[name = "Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type = "submit"]').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var tab = $('a[data-gtm-eventlabel = "Manage_booking"]');
        if (tab.length > 0) {
            tab.get(0).click();
        }

        var properties = params.account.properties.confFields;
        var form = $('form[name = "manage-a-booking-form"]');
        if (form.length > 0) {
            form.find('input[name = "bookingReference"]').val(properties.ConfNo);
            form.find('input[name = "lastName"]').val(properties.LastName);
            form.find('input[name = "checkbox-authorised"]').click();
            provider.setNextStep('itLoginComplete', function() {
                form.find('button[type = "submit"]').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId > 0    ) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://openrewards.flydubai.com/en/';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('span:contains("' + confNo + '")');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div#error-tooltip-bubble');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    }

};

