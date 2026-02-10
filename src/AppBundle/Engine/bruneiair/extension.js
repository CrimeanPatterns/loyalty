var plugin = {
    //keepTabOpen: true,
    hosts: {
        'book-royalbrunei.crane.aero': true
    },

    getStartingUrl: function (params) {
        return 'https://book-royalbrunei.crane.aero/ibe/loyalty/mycard';
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
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[action="/ibe/loyalty"]').find('input[name="username"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a#logout-button:visible, #mobile-logout-button').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('span:contains("Membership No"):eq(0)').next('span.cover-info-value').text(), /^(\d{6,})$/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            let logout = $('a#logout-button');
            if (logout.length)
                logout.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[action="/ibe/loyalty"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name="username"]').val(params.account.login);
            form.find('input[name="password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[value="Log in"]').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('#errorModalText:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            /*provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.hawaiianairlines.com/my-account/my-trips/upcoming-trip-itinerary';
            });*/
            plugin.toItineraries(params);
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('span.pnr-no:contains("'+ confNo +'")').closest('.flight-content').next('.button-wrapper').find('.viewBookingButton');
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
    }
}