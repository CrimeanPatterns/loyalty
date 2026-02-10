var plugin = {

    hosts: {'www.aireuropa.com': true},

    getStartingUrl: function (params) {
        return 'https://www.aireuropa.com/en/suma#/login';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();

            if (isLoggedIn !== null) {
                clearInterval(start);
                $('#user-details-button-desktop, #user-details-button-mobile').click();
                setTimeout(function () {
                    if (isLoggedIn) {
                        if (plugin.isSameAccount(params.account))
                            plugin.loginComplete(params);
                        else
                            plugin.logout(params);
                    } else {
                        $('button:contains("My account"):visible').click();
                        plugin.login(params);
                    }
                }, 2000);
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
        browserAPI.log("isLoggedIn");

        if ($('button:contains("My account"):visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('div.passenger-name:visible, div.name-initial:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.filter($('div.passenger-ticket').text());
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number === account.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('div.sign-off-padding').get(0).click();

            setTimeout(function () {
                plugin.loadLoginForm(params);
            }, 3000);
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            window.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function () {
            const form = $('form.login-form:visible');

            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            browserAPI.log("submitting saved credentials");
            const login_input = form.find('input[id = "email"]');
            const password_input = form.find('input[id = "password"]');

            login_input.focus().val(params.account.login).blur();
            util.sendEvent(login_input.get(0), 'input');
            password_input.focus().val(params.account.password).blur();
            util.sendEvent(password_input.get(0), 'input');

            const submit = form.find('button.ae-btn-primary');
            submit.focus();

            provider.setNextStep('checkLoginErrors', function () {
                submit.click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }, 2000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.alert-danger:visible:eq(0), div.invalid-credentials:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};
