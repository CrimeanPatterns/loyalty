var plugin = {

    hosts: {'mystuff.buildabear.com': true, 'www.buildabear.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.buildabear.com/on/demandware.store/Sites-buildabear-us-Site/en_US/WorkshopRewards-Show';
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
                        plugin.loginComplete(params);
                    else
                        plugin.logout();
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
        if ($('form[name = "login-form"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('h3:contains("Rewards #:") + p').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('h3:contains("Rewards #:") + p').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && (number === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.buildabear.com/on/demandware.store/Sites-buildabear-us-Site/en_US/Login-Logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[name = "login-form"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "loginEmail"]').val(params.account.login);
        form.find('input[name = "loginPassword"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button:contains("Login")').click();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.error-form:eq(0)');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
}
