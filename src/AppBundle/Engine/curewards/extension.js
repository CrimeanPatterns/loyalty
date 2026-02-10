var plugin = {

    hosts: {'www.curewards.com': true},

    getStartingUrl: function (params) {
        return 'https://www.curewards.com/myaccount/points';
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
        if ($('#formLogin').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=logoff]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp( $('span.account-num').text(), /Account\s*([\d]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && number
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.curewards.com/authentication/account/logoff';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#formLogin');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "LoginUserName"]').val(params.account.login);
            form.find('input[name = "LoginPassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                var captcha = form.find('div.g-recaptcha:visible');
                if (captcha && captcha.length > 0) {
                    provider.reCaptchaMessage();
                    browserAPI.log("waiting...");
                    var counter = 0;
                    var login = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        if (counter > 180) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                        }
                        counter++;
                    }, 500);
                }// if (captcha && captcha.length > 0)
                else {
                    browserAPI.log("captcha is not found");
                    form.find("#loginButton").get(0).click();
                }
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('.validation-summary-errors');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}