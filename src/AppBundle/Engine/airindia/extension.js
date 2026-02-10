var plugin = {
    hosts: {
        'www.flyingreturns.co.in'     : true,
        'ffai.loyaltyplus.aero'       : true,
        'aiflyingreturns.b2clogin.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://loyalty.airindia.in/en-GB/dashboard/overview';
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
                    plugin.login(params)
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('span:contains("Membership Number"):visible + span').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#localAccountForm:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('span:contains("Membership Number") + span').text();
        browserAPI.log("number: " + number);
        return typeof (account.properties) != 'undefined'
            && typeof (account.properties.Number) != 'undefined'
            && account.properties.Number !== ''
            && number
            && number === account.properties.Number;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('button[aria-label="Log out"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form#localAccountForm');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[id = "signInName"]').val(params.account.login);
        form.find('input[id = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            $('button[id="next"]').get(0).click();

            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function () {
        const errors = $('.error:visible');

        if (errors.length > 0) {
            provider.setError(util.trim(errors.text()));
            return;
        }

        provider.complete();
    }

};
