var plugin = {
    //keepTabOpen: true,
    hosts: {'www.unitybyhardrock.com': true, 'login.unitybyhardrock.com': true},

    getStartingUrl: function (params) {
        return 'https://www.unitybyhardrock.com/dashboard';
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
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#usernameUserInput').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.dashboard-hero-qr-account_number:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        let number = util.findRegExp( $('.dashboard-hero-qr-account_number').text(), /^\s*(\d+)\s*$/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && (number == account.properties.CardNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            let logout = $('a[href="/log-out"]:contains("Log Out")');
            if (logout.length)
                logout.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form#loginForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "usernameUserInput"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#js-shr-login__submit').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        let errors = $('.shr-card__error-message:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }

}