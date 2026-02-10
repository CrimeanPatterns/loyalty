var plugin = {

    hosts: {
        'binnys.com': true,
        'www.binnys.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.binnys.com/myaccount/account';
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
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
                } else
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
        browserAPI.log('isLoggedIn');
        if ($('a:contains("Sign out")').length) {
            browserAPI.log('isLoggedInd: true');
            return true;
        }
        if ($('#login-form').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        let number = $('div:contains("Binny\'s Card:"):has(strong:contains("Binny\'s Card")) + div.col').text();
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.CardNumber) != 'undefined')
                && (account.properties.CardNumber != '')
                && number
                && (number == account.properties.CardNumber));
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Sign out")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log('login');
        let form = $('#login-form');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        let emailInput = form.find('input[name = "signupEmail"]');
        emailInput.val(params.account.login);
        util.sendEvent(emailInput.get(0), 'input');

        let passwordInput = form.find('input[name = "signupPassword"]');
        passwordInput.val(params.account.password);
        util.sendEvent(passwordInput.get(0), 'input');

        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[type="submit"]', form).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        let errors = $('.error-msg:visible:eq(0)');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    }
};
