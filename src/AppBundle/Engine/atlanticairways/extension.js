var plugin = {
    hosts: {'www.atlanticairways.com': true},

    getStartingUrl: function (params) {
        return 'https://www.atlanticairways.com/en-us/s%C3%BAlubonus/';
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
        if ($('form[name = "loginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.ngLoyaltyTabButton:contains("Log out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp($('.member-bonusnumber:eq(0)').text(), /^\d+$/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
        && (typeof(account.properties.BonusNumber) != 'undefined')
        && (account.properties.BonusNumber != '')
        && (number == account.properties.BonusNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            var logout = $('.ngLoyaltyTabButton:contains("Log out")');
            if (logout.length) {
                logout.get(0).click();
            }
            setTimeout(function () {
                document.location.href = plugin.getStartingUrl();
            }, 2000);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                plugin.getConfNoItinerary(params);
                document.location.href = 'https://www.atlanticairways.com/en/';
            });
            return;
        }
        var form = $('form[name = "loginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // angularjs
            provider.eval("var scope = angular.element(document.querySelector('form[name = \"loginForm\"]')).scope();"
             + "scope.loginData.userName = '" + params.account.login + "';"
             + "scope.loginData.password = '" + params.account.password + "';"
             + "scope.loginForm.$valid = true;"
             + "scope.login(scope.loginForm);"
             );

            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 2000);
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.error:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var btn = $('button:contains("My booking")');
        if (btn.length)
            btn.get(0).click();
        var form = $('div[ng-show *= "== \'change\'"]');
        if (form.length > 0) {

            // angularjs
            provider.eval(
                "var form = $('div[ng-show *= \"== \\'change\\'\"]');"
                + "var scope = angular.element(form.find('input[placeholder = \"Last name\"]').get(0)).scope();"
                + "scope.searchboxService.changeTicketModel.lastname = '" + properties.LastName + "';"
                + "var scope = angular.element(form.find('input[placeholder = \"Reservation number\"').get(0)).scope();"
                + "scope.searchboxService.changeTicketModel.pnr = '" + properties.ConfNo + "';"
            );
            form.get(0).click();
            provider.setNextStep('itLoginComplete', function () {
                provider.eval(
                    "var scope = angular.element(form.find('button:contains(\"Find reservation\")').get(0)).scope();"
                    + "scope.searchboxService.submitChangeTicket();"
                );
            });
            // form.find('input[placeholder = "Last name"]').val(properties.ConfNo);
            // form.find('input[placeholder = "Reservation number"]').val(properties.LastName);
            // provider.setNextStep('itLoginComplete', function() {
            //     form.find('button[value = "Find reservation"]').click();
            // });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};
