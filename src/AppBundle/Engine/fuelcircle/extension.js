var plugin = {
    hosts: {'fuelcircle.com': true},

    getStartingUrl: function (params) {
        return 'http://fuelcircle.com';
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
        if ($('form[action = "http://fuelcircle.com/"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("Can't determine login state");
        provider.setError(["Can't determine login state", util.errorCodes.providerError]);
    },

    isSameAccount: function (account) {
        // browserAPI.log("account: " + JSON.stringify(account));
        // browserAPI.log("account properties: " + JSON.stringify(account.properties));
        var name = util.beautifulName( $('.name').text() );
        browserAPI.log("name from site: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name == account.properties.Name));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'http://fuelcircle.com/logout/';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[action = "http://fuelcircle.com/"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                $('button[name = "login"]').click();
            });
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError(["Login form not found", util.errorCodes.providerError]);
        }
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.alert-danger');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

}