var plugin = {

    hosts: {'www.express.com': true},
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.express.com/my-account';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
                        plugin.loginComplete(params);
                    else
                        plugin.logout();
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
        if ($('#login-form-email-addr:visible').parents('form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "/account/signout.jsp"]:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var expressNextID = $('span:contains("EXPRESS NEXT I.D.")');
        if (expressNextID.length === 0)
            return false;
        var number = util.trim(expressNextID[0].nextSibling.nodeValue);
        browserAPI.log("number: " + number);
        return ((typeof account.properties !== 'undefined')
            && (typeof account.properties.ExpressNextID !== 'undefined')
            && (account.properties.ExpressNextID !== '')
            && number
            && (number == account.properties.ExpressNextID));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.express.com/account/signout.jsp';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#login-form-email-addr:visible').parents('form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            util.sendEvent(form.find('input[name = "email"]').get(0), 'input');
            util.sendEvent(form.find('input[name = "password"]').get(0), 'input');
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
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

                        form.find('button').click(function () {
                            clearInterval(login);
                            setTimeout(function () {
                                plugin.checkLoginErrors(params);
                            }, 5000);
                        });
                    }
                    else
                        form.find('button').get(0).click();
                }, 2000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var counter = 0;
        var checkLoginErrors = setInterval(function () {
            var errors = $('div#rvn-note-NaN:visible');
            if (errors.length > 0 && util.trim(errors.text()) !== '') {
                clearInterval(checkLoginErrors);
                provider.setError(errors.text());
            }
            if (counter > 10) {
                clearInterval(checkLoginErrors);
                plugin.loginComplete(params);
            }
            counter++;
        }, 500);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};