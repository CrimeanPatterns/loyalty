var plugin = {

    hosts: {'www.acehardware.com': true},

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.acehardware.com/myaccount';
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
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        var number = $('.rewards-number');
        if (number.length && number.text() !== '') {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('.mz-utilitynav-link.loggedOut').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.Number) !== 'undefined'
            && account.properties.Number !== ''
            && $('.rewards-number:contains(' + account.properties.Number + ')').length;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep("start", function () {
            document.location.href = "https://www.acehardware.com/logout?returnUrl=/myaccount";
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function () {
            var logged = $('.mz-utilitynav-link.loggedOut');
            if (logged.length)
                logged.get(0).click();
            var form = $('.signin-register-container.show form[name = "mz-loginform"]:visible');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "email"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button:contains("Sign In")').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors();
                    }, 5000);
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 2000);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('.mz-validationmessage:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }
};