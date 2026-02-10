var plugin = {

    hosts: {'signin.ebay.com': true, 'my.ebay.com': true},

    getStartingUrl: function (params) {
        return 'http://my.ebay.com/ws/eBayISAPI.dll?MyeBay';
    },

    start: function (params) {
        if (plugin.isLoggedIn())
            plugin.logout();
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('td#gh-u a.gh-a').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#SignInForm, form#signin-form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    logout: function () {
        provider.setNextStep('login');
        document.location.href = 'https://signin.ebay.com/ws/eBayISAPI.dll?SignIn';
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#SignInForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#userid').val(params.account.login);
            form.find('input#pass').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.submit();
            });
        } else {
            form = $('form#signin-form');
            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }
            browserAPI.log("submitting saved credentials");
            form.find('input#userid').val(params.account.login);
            provider.setNextStep('passwordForm', function () {
                form.find('#signin-continue-btn').click();
                setTimeout(function () {
                    if ($('#signin-error-msg:visible').length > 0) {
                        plugin.checkLoginErrors(params);
                    }
                }, 2000)
            });
        }
    },

    passwordForm: function (params) {
        browserAPI.log('passwordForm');
        var form = $('form#signin-form');
        if (form.length > 0 && form.find('input#pass').length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#pass').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#sgnBt').click();
            });
        } else {
            if ($('#signin-error-msg:visible').length > 0)
                plugin.checkLoginErrors(params);
            else
                provider.setError(util.errorMessages.passwordFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let error = $('#errf:visible, #signin-error-msg:visible');
        if (error.length > 0 && util.filter(error.text()) !== '')
            provider.setError(error.text());
        else
            provider.complete();
    }

};