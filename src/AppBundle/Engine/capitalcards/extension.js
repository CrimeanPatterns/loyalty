var plugin = {

    hosts: {'servicing.capitalone.com': true, 'login.capitalone.com': true, 'verified.capitalone.com': true, '/\\w+\\.capitalone\\.com/': true},

    getStartingUrl: function (params) {
        if (params.account.login2 == 'CA')
            return 'https://verified.capitalone.com/sic-ui/#/esignin?Product=Card&CountryCode=CA&Locale_Pref=en_CA';
        else
            return 'https://verified.capitalone.com/sic-ui/#/esignin?Product=Card&CountryCode=US&Locale_Pref=en_EN';
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
        if ($('form[name = "signInForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#id-signout-icon-text').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const name = $('p.accountname').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && name
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('#id-signout-icon-text').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[name = "signInForm"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        /*
        form.find('input[data-controlname = "username"]').val(params.account.login);
        form.find('input[data-controlname = "password"]').val(params.account.password);
        */
        let emailInput = form.find('input[data-controlname = "username"], input[id="usernameInputField"]');
        emailInput.val(params.account.login);
        util.sendEvent(emailInput.get(0), 'input');

        let passwordInput = form.find('input[data-controlname = "password"], input[id="pwInputField"]');
        passwordInput.val(params.account.password);
        util.sendEvent(passwordInput.get(0), 'input');

        provider.setNextStep('checkLoginErrors', function(){
            form.find('button.sign-in-button, button[data-testtarget=\"sign-in-submit-button\"]').click();
            /*
            // angularjs
            provider.eval(
                "var scope = angular.element(document.querySelector('form[name = userLogin]')).scope();"
                + "scope.$apply(function(){"
                + "scope.signin('" + params.account.login + "', '" + params.account.password + "');"
                + "});"
            );
            */
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000)
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        let errors = $('p.error-warning:visible, [class *= "textfield__helper--error"]:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }

};