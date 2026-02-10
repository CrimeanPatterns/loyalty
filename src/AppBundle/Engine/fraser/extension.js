var plugin = {

    hosts: {
        'www.houseoffraser.co.uk': true,
        'auth.houseoffraser.co.uk': true,
    },

    getStartingUrl: function () {
        return 'https://www.houseoffraser.co.uk/Login?returnurl=/recognition/recognitionsummary';
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
        if ($('form#loginForm:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "logoff"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = $('div.rewardCardBlock span.recognitionInfo').last().text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && number
            && number === account.properties.Number);
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            const logout = $('a[href *= "logoff"]');
            if (logout.length) {
                logout.get(0).click();
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form#loginForm');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[id = "email"]').val(params.account.login);
        form.find('input[id = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find("button.btn").click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        const errors = $('#errorMessage, p.field-error:visible:eq(0)');

        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }
};


