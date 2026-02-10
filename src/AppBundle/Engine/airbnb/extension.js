var plugin = {

    hosts: {'/www\\.airbnb\\.\\w+/': true},

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.airbnb.com/dashboard';
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
        if ($('form[data-testid="auth-form"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[aria-label="Account"], button#headerNavUserButton').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = $('a[title="Show profile"] img').attr('alt');
        browserAPI.log("name: " + name);
        return typeof account.properties !== 'undefined'
            && typeof account.properties.Name !== 'undefined'
            && account.properties.Name !== ''
            && name !== ''
            && account.properties.Name.indexOf(name) !== -1;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loginForm', function () {
            if (provider.isMobile) {
                document.querySelector('button[aria-label="Main navigation menu"]').click();
                setTimeout(function () {
                    document.querySelector('form[action="/logout"] button').click();
                }, 1000);
            } else {
                let account = $('button#headerNavUserButton');
                if (account.length) {
                    account.get(0).click();
                    setTimeout(function () {
                        $('ul#headerNavUserMenu').find('form > button[type="submit"]').get(0).click();
                    }, 1000);
                }
            }
        });
    },

    loginForm: function (params) {
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let notYouBtn = $('button:contains("Use another account"):visible');
        browserAPI.log("not you: " + notYouBtn.length);

        if (notYouBtn.length > 0) {
            browserAPI.log("click 'not you'");
            notYouBtn.get(0).click();
        }

        util.waitFor({
            timeout: 5,
            selector: 'input[name = "user[email]"], button:contains("Continue with email"):visible',
            success: function(elem) {
                browserAPI.log("login success");
                const btn = $('button:contains("Continue with email"):visible');
                if (btn.length > 0) {
                    btn.get(0).click();
                }
                setTimeout(function () {
                    setLogin();
                }, 2000);
            },
            fail: function() {
                browserAPI.log("login fail");
                setLogin();
            }
        });

        function setLogin() {
            browserAPI.log("submitting saved credentials");
            var loginInput = $('input[id = "email-login-email"]');
            loginInput.val(params.account.login);
            util.sendEvent(loginInput.get(0), 'click');
            util.sendEvent(loginInput.get(0), 'input');
            util.sendEvent(loginInput.get(0), 'change');
            util.sendEvent(loginInput.get(0), 'blur');
            // reactjs
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
                "triggerInput('input[id = \"email-login-email\"]', '" + params.account.login + "');"
            );

            let account = $('button:contains("Continue"):visible');

            if (account.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            account.get(0).click();
        }

        util.waitFor({
            selector: 'input[id = "email-signup-password"]:visible',
            success: function(elem) {
                elem.get(0).click();
                setPassword();
            },
            fail: function() {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        });

        function setPassword() {
            var passInput = $('input[id = "email-signup-password"]');
            passInput.val(params.account.password);
            util.sendEvent(passInput.get(0), 'click');
            util.sendEvent(passInput.get(0), 'input');
            util.sendEvent(passInput.get(0), 'change');
            util.sendEvent(passInput.get(0), 'blur');

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
                "triggerInput('input[id = \"email-signup-password\"]', '" + params.account.password + "');"
            );



            provider.setNextStep('checkLoginErrors', function () {
                let account = $('button:contains("Log in"):visible');
                if (account.length) {
                    account.get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000)
                }
            });
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        browserAPI.log(document.location.href);

        if ($('h1:contains("Confirm account"):visible').length) {
            provider.complete();
            return;
        }

        // Terms of Service Update
        /*if (document.location.href.indexOf('/users/tos_confirm') !== -1) {
            provider.complete();
            return;
        }*/

        if (document.location.href.indexOf('/airlock') !== -1) {
            browserAPI.log("Airlock...");
            // Help us confirm it’s really you
            if ($('#site-content div:contains("Help us confirm it’s really you")').length
                || $('#site-content div:contains("We don’t recognize this device"):visible').length) {
                provider.complete();
                return;
            }

            // ReCaptcha
            setTimeout(function () {
                var captcha = $('iframe[src ^= "https://www.google.com/recaptcha/api2/anchor"]:visible');
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
                }
            }, 1000);

            return;
        }

        let error = $('section[role="status"]:visible > div > div, div[id *= "__error"]:visible');
        if (error.length > 0 && util.trim(error.text()) !== '') {
            provider.setError(util.trim(error.contents().filter(function() {
                return this.nodeType === 3;
            }).text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.airbnb.com/trips';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('a[href *= "/' + confNo + '"]:eq(0)');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
                    link.get(0).click();
                });
            }
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
