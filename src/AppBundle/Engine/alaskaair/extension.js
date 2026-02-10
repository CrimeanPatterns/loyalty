var plugin = {

    hosts: {
        'www.alaskaair.com': true,
        'www.mrrebates.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.alaskaair.com/?SITE_PREF=full';
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
                    provider.setNextStep('checkAccountNumber', function () {
                        document.location.href = 'https://www.alaskaair.com/www2/ssl/myalaskaair/myalaskaair.aspx';
                    });
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

    checkAccountNumber: function (params) {
        browserAPI.log("checkAccountNumber");
        if (plugin.isSameAccount(params.account))
            plugin.loginComplete(params);
        else
            plugin.logout();
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (util.stristr($('a#navbar-greeting-link:visible').text().trim(), 'Sign in')) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('a#navSignOut').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp( $('p:contains("Mileage Plan number:")').text(), /Mileage Plan Number:\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('login', function () {
            document.location.href = 'https://www.alaskaair.com/www2/ssl/myalaskaair/myalaskaair.aspx?CurrentForm=UCSignOut&lid=signOut';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            if ($('#frmViewPnr').length === 0)
                provider.setNextStep('getConfNoItinerary', function () {
                    document.location.href = plugin.getStartingUrl(params);
                });
            else
                plugin.getConfNoItinerary(params);
            return;
        }
        const form = $('form[action *= "signin"]');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        $('a#navbar-greeting-link').get(0).click();
        browserAPI.log("submitting saved credentials");
        form.find('input[name = "UserId"]').val(params.account.login);
        form.find('input[name = "Password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            if ($('#sign-in-btn').length > 0)
                $('#sign-in-btn').click();
            else
                form.find('input[value = "signInWidget"]').click();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.errorText');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()).replace(/^Error/, ''));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.alaskaair.com/www2/ssl/myalaskaair/myalaskaair.aspx?view=trips&lid=header:myTrips';
            });
            return;
        }
        if (typeof(params.account.fromPartner) == 'string') {
            setTimeout(provider.close, 1000);
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        const link = $('auro-hyperlink[href*="reservation-lookup"][href *= "RECLOC=' + params.account.properties.confirmationNumber + '&"]');

        if (link.length === 0) {
            provider.setError(util.errorMessages.itineraryNotFound);
            return;
        }

        provider.setNextStep('itLoginComplete', function () {
            document.location.href = link.attr('href');
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        const properties = params.account.properties.confFields;
        const form = $('#frmViewPnr');

        if (form.length === 0) {
            provider.setError(util.errorMessages.itineraryFormNotFound);
            return;
        }

        form.find('input[name="TravelerLastName"]').val(properties.LastName);
        form.find('input[name="CodeOrNumber"]').val(properties.ConfNo);
        provider.setNextStep('itLoginComplete', function () {
            form.find('#submitPNR').click();
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
