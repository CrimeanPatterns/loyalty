var plugin = {

    hosts: {'www.royalairmaroc.com': true},

    getStartingUrl: function (params) {
        return 'https://www.royalairmaroc.com/us-en/safar-flyer/my-dashboard';
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
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
                } else
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
        if ($('a[href *= "/logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name = "_login_WAR_ramloyaltysafarflyerportlet_fm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.findRegExp($('p:contains("Safar Flyer nº")').text(), /nº\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Number) != 'undefined')
                && (account.properties.Number !== '')
                && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[href *= "/logout"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "_login_WAR_ramairwaysportlet_fm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "_login_WAR_ramairwaysportlet_emailId"]').val(params.account.login);
            form.find('input[name = "_login_WAR_ramairwaysportlet_password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#btn-login').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.alert-error:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }

};
