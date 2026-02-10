var plugin = {


    hosts: {'www.fandango.com': true},

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    getStartingUrl: function (params) {
        return 'https://www.fandango.com/accounts/settings';
    },

    start: function (params) {
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= "so=1"]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#Form1').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const name = $('#FirstName').attr('value') + ' ' + $('#LastName').attr('value');
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm');
        document.location.href = 'https://www.fandango.com/accounts/settings?so=1';
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        if ($.msie) {
            setTimeout(function(){
                browserAPI.log("IE version");
            }, 1000)
        }
        provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl();
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('#Form1');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "ctl00$GlobalBody$SignOnControl$UsernameBox"]').val(params.account.login);
        form.find('input[name = "ctl00$GlobalBody$SignOnControl$PasswordBox"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors');
        $('a#ctl00_GlobalBody_SignOnControl_SignInButton').get(0).click();
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('#error-notification-msg:visible, span.js-fcsbl-input-error-msg:visible');

        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params){
        provider.complete();
    }
}