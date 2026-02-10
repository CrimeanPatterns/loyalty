var plugin = {

    hosts: {
        'www.frasershospitality.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.frasershospitality.com/en/fraser-world/account/#!tab5';
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
            if (isLoggedIn !== null && counter > 3) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#login-page-btn:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
		
        if ($('a.member-logout:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
		const number = $('p.memebership-number').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.MembershipNumber) != 'undefined')
            && (account.properties.MembershipNumber != '')
            && (number == account.properties.MembershipNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a.member-logout:visible').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[id ="login-form"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "loginPageEmail"]').val(params.account.login);
        form.find('input[name = "loginPagePass"]').val(params.account.password);
        setTimeout(function () {
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#login-page-btn').get(0).click();
            });
            util.waitFor({
                selector: 'div[class *= "validation-error"]:visible',
                success: function(){
                    plugin.checkLoginErrors(params);
                },
                fail: function() {
                }
            });
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div[class *= "validation-error"]:visible, .validation-summary-errors li');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('itLoginComplete', function() {
                document.location.href = 'https://www.frasershospitality.com/en/fraser-world/account/#!tab1';
            });
            return;
        }

        provider.complete();
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
