var plugin = {

    hosts: {
        'www.dsw.com': true,
        'www.dsw.ca': true,
        'townshoes.ca': true,
        '/\\w+\\.townshoes.ca/': true
    },

    getStartingUrl: function (params) {
        if (params.account.login2 == 'Canada')
            return 'https://www.dsw.ca/en/ca/sign-in';
        else
            return 'https://www.dsw.com/en/us/sign-in';
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log('start');
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 20)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log('isLoggedIn');
        if (params.account.login2 == 'Canada') {
            if ($('form.page-sign-in__form:visible').length) {
                browserAPI.log('isLoggedIn: false');
                return false;
            }
        }// if (params.account.login2 == 'Canada')
        else {
            if ($('form[name = "signInpageForm"]:visible').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
        }
        if ($('a:contains("Sign Out"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        var number = util.findRegExp( $('div.my-profile__dashboard__header__top__show-member:contains("Member #"), span.my-dashboard__loyalty-number').text(), /#\s*([^<]+)/i);
        browserAPI.log("number: " + number);
            return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log('logout');
        $('a:contains("Sign Out")').get(0).click();
        setTimeout(function () {
            plugin.loadLoginForm(params);
        }, 2000);
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "signInpageForm"], form.page-sign-in__form:visible');
        if (form.length > 0) {
            browserAPI.log("reloadWithDebugInfo");
            provider.setNextStep('submitLoginForm', function () {
                // angularjs
                // provider.eval("angular.reloadWithDebugInfo();");
                provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }
        provider.setError(util.errorMessages.loginFormNotFound);
    },

    submitLoginForm: function (params) {
        browserAPI.log("submitLoginForm");
        setTimeout(function () {
            var form = $('form[name = "signInpageForm"], form.page-sign-in__form:visible');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                if (params.account.login2 == 'Canada') {
                    // angularjs
                    provider.eval(
                        "var scope = angular.element(document.querySelector('form.page-sign-in__form')).scope();"
                        + "scope.$apply(function(){"
                        + "scope.vm.form.get('login').value = '" + params.account.login + "'; "
                        + "scope.vm.form.get('password').value = '" + params.account.password + "';"
                        + "});"
                    );
                }
                else {
                    // angularjs
                    provider.eval(
                        "var scope = angular.element(document.querySelector('form[name = signInpageForm]')).scope();"
                        + "scope.$apply(function(){"
                        + "scope.vm.login = '" + params.account.login + "'; "
                        + "scope.vm.password = '" + params.account.password + "';"
                        + "});"
                    );
                }
                form.find('button').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000)
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 2000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.inline-server-error:visible, div.notifier_failure:visible');
        if (errors.length > 0 && '' != util.trim(errors.text()))
            provider.setError(util.trim(errors.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};
