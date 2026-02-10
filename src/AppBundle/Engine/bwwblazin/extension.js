var plugin = {
    hosts: {
        'www.buffalowildwings.com': true,
        'login.buffalowildwings.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.buffalowildwings.com/account/';
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
                    if (plugin.isSameAccount(params.account)) {
                        provider.complete();
                    } else {
                        plugin.logout(params);
                    }
                } else {
                    plugin.login(params);
                }
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
        if ($('form:has(button:contains("SIGN IN")):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button[data-gtm-id = "accountLogout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const email = $('#email')
        browserAPI.log("email: " + email.attr('value'));
        return ((typeof(account.properties) != 'undefined')
            && email.length
            && (email.attr('value') !== '')
            && (email.attr('value') === account.login));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('button[data-gtm-id = "accountLogout"]').click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form:has(input#email):visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
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
            "triggerInput('input#email', '" + params.account.login + "');\n" +
            "triggerInput('input#password', '" + params.account.password + "');"
        );
        provider.setNextStep('checkLoginErrors', function () {
            setTimeout(function() {
                form.find('button:contains("SIGN IN")').get(0).click();

                setTimeout(function () {
                    plugin.checkLoginErrors();
                }, 4000);
            }, 2000);
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        const errors = $('span[class *= "errorText"]');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }

};