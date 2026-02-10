var plugin = {

    hosts: {'www.foxrentacar.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.foxrentacar.com/en/rewards-program/my-rewards.html';
    },

    start: function (params) {
        // cash back
        if (document.location.href.indexOf('AID=') > 0) {
            provider.setNextStep('start');
            document.location.href = plugin.getStartingUrl(params);
            return;
        }
        setTimeout(function() {
            if (plugin.isLoggedIn()) {
                if (plugin.isSameAccount(params.account))
                    plugin.loginComplete(params);
                else
                    plugin.logout();
            }
            else
                plugin.login(params);
        }, 3000)
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#login a:contains("Login"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#signout a:contains("Signout"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("Can't determine login state");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('div#tsdNumber').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('loadLoginForm');
        $('#signout a:contains("Signout"):visible').get(0).click();
    },

    loadLoginForm: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    login: function (params) {
        browserAPI.log("login");
        $('#login a:contains("Login"):visible').get(0).click();
        var form = $('#login_form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "login-email"]').val(params.account.login);
            form.find('input[name = "login-password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('#login-submit').get(0).click();
            setTimeout(function() {
                plugin.checkLoginErrors(params);
            }, 5000);
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        var errors = $('div.login_error_message:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        if (typeof(params.account.fromPartner) == 'string') {
            setTimeout(provider.close, 1000);
        }
        provider.complete();
    }
}
