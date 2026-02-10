var plugin = {

    hosts: {'www.discovercard.com': true, 'www.discover.com': true, '/\\w+\\.discover\\.com/': true},

    getStartingUrl: function (params) {
        return 'https://www.discovercard.com';
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
        if ($('#login-form-content').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "logout"], a[href *= "logoff"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return false;
        // var number = util.findRegExp( $('').text(), /Account\s*([^<]+)/i);
        // browserAPI.log("number: " + number);
        //     return ((typeof(account.properties) != 'undefined')
        //     && (typeof(account.properties.AccountNumber) != 'undefined')
        //     && (account.properties.AccountNumber != '')
        //     && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a[href *= "logout"], a[href *= "logoff"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#login-form-content');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('#userid-content').val(params.account.login);
            form.find('#password-content').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#log-in-button').click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('p#info-err-msg:visible');
        if (errors.length > 0)
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }
};