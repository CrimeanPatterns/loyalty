var plugin = {
    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.84 Safari/537.36',
    hosts : {
        'pfchangs.com'     : true,
        'www.pfchangs.com' : true
    },

    getStartingUrl: function (params) {
        return 'https://www.pfchangs.com/account/overview';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function() {
            document.location.href = 'https://www.pfchangs.com/account/sign-in?utm_source=web&utm_medium=navigation&utm_content=main_nav';
        });
    },

    start: function (params) {
        browserAPI.log('start');
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
                } else {
                    plugin.loadLoginForm(params);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 1000);
    },

    isLoggedIn: function () {
        browserAPI.log('isLoggedIn');
        if ($('a#logout-btn').length) {
            browserAPI.log('isLogged: true');
            return true;
        }
        if ($('a#login-btn').length) {
            browserAPI.log('isLogged: false');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        var number = $('div[ng-if="localUser.printedCardNumber"] span.ng-binding').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && number
            && (number == account.properties.CardNumber));
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('start', function() {
            $('a#logout-btn').get(0).click();
            setTimeout(function () {
                plugin.start(params);
            }, 2000);
        });
    },

    login: function (params) {
        browserAPI.log('login');
        var login = $('a:contains("Sign in here")');
        if (login.length)
            login.get(0).click();
        setTimeout(function () {
            var form = $('div.formWrapper:visible');
            if (form.length) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name="tmpSignInEmail"]').val(params.account.login);
                form.find('input[name="password"]').val(params.account.password);
                provider.eval(
                    "var scope = angular.element(document.querySelector('[name=\"tmpSignInEmail\"]')).scope();"
                    + "scope.$apply(function(){"
                    + "scope.$root.tmpSignInEmail = '" + params.account.login + "';"
                    + "});"
                );
                provider.eval(
                    "var scope = angular.element(document.querySelector('[name=\"password\"]')).scope();"
                    + "scope.$apply(function(){"
                    + "scope.password = '" + params.account.password + "';"
                    + "});"
                );
                provider.setNextStep('checkLoginErrors', function () {
                    $('button[value="sign-in"]').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000);
                });
            } else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 2000);
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        var error = $('.errorContainer:visible');
        if (error.length == 0)
            error = $('div.jsAccountUpdate:visible, div.jsModalPassword:visible, div.jsModalUpgraded:visible, div.jsModalAccountNotFound:visible').find('h3 + div.-fontCenter');
        if (error.length && util.filter(error.text()) != '')
            provider.setError(util.filter(error.text()));
        else
            provider.complete();
    }

};
