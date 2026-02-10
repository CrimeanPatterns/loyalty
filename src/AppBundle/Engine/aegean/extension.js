var plugin = {

    hosts: {
        '/\\w+\\.aegeanair\\.com/': true,
        'a3.frequentflyer.aero': true,
        'e-ticket.aegeanair.com': true
    },

    getStartingUrl: function (params) {
		return 'https://en.aegeanair.com/milesandbonus/my-account/';
    },

    start: function (params) {
        setTimeout(function() {
            if (plugin.isLoggedIn()) {
                if (plugin.isSameAccount(params.account))
                    plugin.loginComplete(params);
                else
                    plugin.logout(params);
            }
            else
                plugin.login(params);
        }, 2000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains(Logout)').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[id = "loginPageFormId"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $('div:contains("Member ID") + div.number').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        $('a:contains(Logout)').get(0).click();
        setTimeout(function() {
            plugin.start(params);
        }, 1000);
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId == 0   ) {
            provider.setNextStep('getConfNoItinerary', function() {
                document.location.href = 'https://en.aegeanair.com/plan/my-booking/';
            });
            return;
        }

        var form = $('form[id = "loginPageFormId"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "Username"]').val(params.account.login);
            form.find('input[name = "Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            // for mobile auto-login
            form.find('button[type = "submit"]').click();
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 5000);
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('.modal:visible .modal-dialog .modal-body > div');
        if (errors.length > 0 && $('a:contains(Logout)').length === 0 && util.filter(errors.text()) != '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId > 0    ) {
            setTimeout(function() {
                provider.setNextStep('toItineraries', function() {
                        document.location.href = 'https://en.aegeanair.com/member/my-bookings/';
                });
            }, 6000);
            return;
        }
        provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form[action *= "MyBooking.axd"]');
        if (form.length > 0) {
            form.find('input[name = "PNR"]').val(properties.ConfNo);
            form.find('input[name = "LastName"]').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function() {
                form.find('button[type = "submit"]').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var button = $('input[name = "REC_LOC"][value *= "'+ confNo +'"] + input + button');
            if (button.length > 0) {
                provider.setNextStep('itLoginComplete', function() {
                    button.get(0).click();
                });
            }// if (button.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    }

};
