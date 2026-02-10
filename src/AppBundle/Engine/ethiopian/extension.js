var plugin = {

    hosts: {
        'shebamiles.ethiopianairlines.com': true,
        'ethiopianairlines.com'           : true
    },

    getStartingUrl: function (params) {
        return 'https://shebamiles.ethiopianairlines.com/account/my-account/index';
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
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[id = "login-user"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Logout")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = $('small:contains("ET ")').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && (number === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Logout")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[id = "login-user"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name="MemberID"]').val(params.account.login);
        form.find('input[name="Password"]').val(params.account.password);

        provider.setNextStep('checkLoginErrors', function () {
            form.find('input[value="LOG IN"]').click();
        });
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.alert-danger:visible').text().trim();

        if (errors.length > 0) {
            provider.setError(errors);
            return;
        }

        provider.complete();
    }

};
