var plugin = {

    hosts: {'www.ae.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params){
        return 'https://www.ae.com/us/en/login';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        setTimeout(function () {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        }, 4000);
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
                    if (plugin.isSameAccount(params.account)) {
                        provider.complete();
                    }
                    else
                        plugin.logout(params);
                }
                else {
                    plugin.login(params);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('.qa-real-rewards-number:contains("Member"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('div.login-form > form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.Number) !== 'undefined'
            && account.properties.Number !== ''
            && $('.qa-real-rewards-number:contains("'+ account.properties.Number + '")').length;
    },

    logout: function (params) {
        browserAPI.log("logout");
        $('a.sidetray-account').get(0).click();
        provider.setNextStep("loadLoginForm", function () {
            setTimeout(function () {
                $('.btn-sign-out:eq(0)').get(0).click();
            }, 1000);
            setTimeout(function () {
                plugin.loadLoginForm(params);
            }, 2000);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('div.login-form > form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = username]').val(params.account.login);
            form.find('input[name = password]').val(params.account.password);
            // refs #11326
            util.sendEvent(form.find('input[name = "username"]').get(0), 'input');
            util.sendEvent(form.find('input[name = "password"]').get(0), 'input');

            provider.setNextStep('checkLoginErrors', function () {
                form.find('button.qa-btn-login').click();
                setTimeout(function () {
                    // if 5 sec later we're still on login page
                    // it means login error occurred
                    plugin.checkLoginErrors(params);
                }, 5000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.has-error:visible').filter('.help-block');
        if (errors.length > 0)
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }
};
