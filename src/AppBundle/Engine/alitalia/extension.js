var plugin = {

    hosts: {'www.alitalia.com': true, 'mm.alitalia.com': true, 'beta.alitalia.com': true},

    getStartingUrl: function (params) {
        return 'https://www.alitalia.com/en_us/special-pages/millemiglia-login.html?resource=%2Fcontent%2Falitalia%2Falitalia-us%2Fen%2Fpersonal-area%2Faccount-statement.html&$$login$$=%24%24login%24%24&j_reason=unknown&j_reason_code=unknown';
    },

    start: function (params) {
        // IE not working properly
        if (!!navigator.userAgent.match(/Trident\/\d\./)) {
            provider.eval('jQuery.noConflict()');
        }
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
        if ($('span.binding-mm-full-name').text() != '') {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[id = "form-millemiglia-header-login"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.trim($('input[name=customercode]')).val();
        browserAPI.log("number: " + number);
        return typeof account.properties !== 'undefined'
            && typeof account.properties.Number !== 'undefined'
            && account.properties.Number !== ''
            && number == account.properties.Number;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('#headerButtonLogout').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        //$('a.userMenu__login').get(0).click();
        setTimeout(function() {
            var form = $('form[id = "form-millemiglia-login"]');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");

                // remove leading zeros
                if (params.account.login.length == 10 && /^000/.test(params.account.login)) {
                    browserAPI.log("remove leading zeros");
                    params.account.login = params.account.login.replace(/^000/, '');
                }

                form.find('input[id = "mmcode"]').val(params.account.login);
                util.setInputValue(form.find('input[id = "pincode"]'), params.account.password);
                form.find('#loginSubmit').get(0).click();

                setTimeout(function () {
                    window.scrollTo(0, 0);
                    var captcha = form.find('iframe[src ^= "https://www.google.com/recaptcha/api2/anchor"]:visible');
                    if (captcha.length > 0) {
                        provider.reCaptchaMessage();
                        browserAPI.log("waiting...");
                        var counter = 0;
                        var login = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            if (counter > 120) {
                                clearInterval(login);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                                return;
                            }
                            counter++;
                        }, 1000);

                        form.find('#loginSubmit').click(function () {
                            clearInterval(login);
                            plugin.checkLoginErrors(params);
                        });
                    } else {
                        form.find('#loginSubmit').get(0).click();
                        plugin.checkLoginErrors(params);
                    }
                }, 3000);
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var counter = 0;
        var checkLoginErrors = setInterval(function () {
            var error = $('form .errorOverlay__text:visible, #pin_error:visible, p.fail:visible');
            if (error.length > 0 && util.trim(error.text()) !== '') {
                clearInterval(checkLoginErrors);
                provider.setError(util.trim(error.text()));
            }

            if (counter > 15) {
                clearInterval(checkLoginErrors);
                provider.complete();
            }
            counter++;
        }, 500);
    }
};