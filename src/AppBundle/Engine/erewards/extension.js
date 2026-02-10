var plugin = {

    hosts: {
        'www.e-rewards.com': true,
        'www.e-rewards.com.br': true,
        'www.e-rewards.fr': true,
        'www.e-rewards.de': true,
        'www.e-rewards.com.mx': true,
        'www.e-rewards.nl': true,
        'www.e-rewards.es': true,
        'www.e-rewards.in': true,
        'www.e-rewards.se': true,
        'www.e-rewards.ch': true,
        'www.e-rewards.co.uk': true,
        'www.e-rewards.dk': true,
        'www.e-rewards.com.au': true,
        'www.e-rewards.ca': true
    },

    getStartingUrl: function (params) {
        if (['', null].indexOf(params.account.login3) !== -1)
            params.account.login3 = 'com';

        if (['com.au', 'ca', 'com.br', 'co.uk', 'de', 'com.mx', 'es', 'com', 'fr', 'nl'].indexOf(params.account.login3) !== -1) {
            return 'https://www.e-rewards.' + params.account.login3 + '/launch/login';
        }

        var url = 'https://www.e-rewards.' + params.account.login3 + '/reviewaccount.do';
        browserAPI.log("Domain => " + params.account.login3);
        browserAPI.log("url => " + url);
        return url;
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
        if ($('a[href *="Logout"]').attr('href')) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name = "logonForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        // mobile
        if ($('button[id = "menuLogout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        // Australia
        if ($('form[name = "loginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[ng-click="authHeader.logout()"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        var name = $('td#top tr:eq(1) td:eq(1)').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("logout");
        if (['com.au'].indexOf(params.account.login3) !== -1) {
            provider.setNextStep('loadLoginForm', function () {
                $('a[ng-click="authHeader.logout()"]').get(0).click();
            });
            return;
        }

        if (params.account.login3 == '')
            params.account.login3 = 'com';
        var url = 'https://www.e-rewards.' + params.account.login3 + '/Logout.do';
        browserAPI.log("Domain => " + params.account.login3);
        browserAPI.log("url => " + url);
        provider.setNextStep('loadLoginForm', function () {
            if (provider.isMobile)
                $('button[id = "menuLogout"]').get(0).click();
            else
                document.location.href = url;
        });
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // Australia
        var form = $('form[name = "loginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // form.find('input#username').val(params.account.login);
            // form.find('input#password').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                // angularjs
                provider.eval('var scope = angular.element(document.querySelector(\'form[name="loginForm"]\')).scope();' +
                      'scope.vm.username = "' + params.account.login + '";' +
                      'scope.vm.password = "' + params.account.password + '";' +
                      'scope.vm.login();'
                );
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000)
            });

            return;
        }

        form = $('form[name = "logonForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "logonHelper.email"]').val(params.account.login);
            form.find('input[name = "logonHelper.password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[value = "Log in"]').get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $("#login-error-msg, div.has-error:visible, span[ng-message=\"error_invalidCredentials\"]:visible");
        if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }
};