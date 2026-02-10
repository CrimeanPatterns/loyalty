var plugin = {

    hosts: {'www.cityairporttrain.com': true},

    getStartingUrl: function (params) {
        return 'https://www.cityairporttrain.com/en/bonusclub/my-credits';
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout(params);
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Logout"):visible').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[action *= "login"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        const name = util.findRegExp( $('p[class *= "welcome name"]').text(), /([^<\!]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && (name === account.properties.Name));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function() {
            $('a[onclick *= "logout"]:eq(0)').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[action *= "login"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return false;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "email"]').val(params.account.login);
        form.find('input[name = "password"]').val(params.account.password);
        // // refs #11326
        util.sendEvent(form.find('input[name = "email"]').get(0), 'change');
        util.sendEvent(form.find('input[name = "password"]').get(0), 'change');

        provider.setNextStep('checkLoginErrors', function() {
            form.find('#form-submit').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors();
            }, 5000)
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('input.error:visible');
        if (errors.length > 0)
            provider.setError("Your credentials are invalid");
        else
            provider.complete();
    }
};