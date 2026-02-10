var plugin = {
    hosts: {
        'copaair.com'             : true,
        'www.copaair.com'         : true,
        'connectmiles.copaair.com': true,
        'members.copaair.com'     : true,
        'login.copaair.com'       : true,
    },

    getStartingUrl: function (params) {
        return 'https://members.copaair.com/en/hub';
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
                    else {
                        provider.setNextStep('logout', function () {
                            document.location.href = 'https://www.copaair.com/en-gs/';
                        });
                        // plugin.logout(params);
                    }
                }
                else {
                    // plugin.login(params);
                    plugin.goToLoginForm(params);
                }
            }// if (isLoggedIn !== null)

            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 15)
            counter++;
        }, 500);
    },

    goToLoginForm: function (params) {
        provider.setNextStep('login', function () {
            document.location.href = 'https://www.copaair.com/api/auth/login?lng=en';
        });
    },

    isLoggedIn: function () {
        browserAPI.log("function isLoggedIn");
        if ($('input#username:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#account_number:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp( $('#account_number').text(), /(\w+)/i);
        browserAPI.log("account number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) !== 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            const logout = $('button[data-ga="Header/Top Links/Login"]');
            if (logout.length) {
                logout.get(0).click();

                setTimeout(function () {
                    $('button:contains("Log out")').click();
                }, 2000);
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");

        setTimeout(function () {
            const form = $('form[data-form-primary="true"]');
            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            browserAPI.log("submitting saved credentials");

            provider.eval(
                "function triggerInput(selector, enteredValue) {\n" +
                "      let input = document.querySelector(selector);\n" +
                "      input.dispatchEvent(new Event('focus'));\n" +
                "      input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));\n" +
                "      let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;\n" +
                "      nativeInputValueSetter.call(input, enteredValue);\n" +
                "      let inputEvent = new Event(\"input\", { bubbles: true });\n" +
                "      input.dispatchEvent(inputEvent);\n" +
                "}\n" +
                "triggerInput('input[name = \"username\"]', '" + params.account.login + "');\n" +
                "triggerInput('input[name = \"password\"]', '" + params.account.password + "');"
            );

            // form.find('input[name = "username"]').val(params.account.login);
            // form.find('input[name = "password"]').val(params.account.password);

            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[name="action"]').click();

                let captcha = $('iframe[src *= "https://www.google.com/recaptcha/api2/bframe"]:visible');
                if (captcha.length > 0) {
                    provider.reCaptchaMessage();
                    browserAPI.log("captcha waiting");
                    let counter = 0;
                    let login = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        let errors = $('.ulp-input-error-message:visible');
                        if (errors.length > 0) {
                            clearInterval(login);
                            plugin.checkLoginErrors(params);
                            return;
                        }
                        if (counter > 120) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                            return;
                        }
                        counter++;
                    }, 1000);
                }
            });
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        setTimeout(function () {
            let errors = $('.ulp-input-error-message:visible');

            if (errors.length > 0 && util.filter(errors.text()) !== '') {
                provider.setError(util.filter(errors.text()));
                return;
            }

            provider.complete();
        }, 4000);
    }

};
