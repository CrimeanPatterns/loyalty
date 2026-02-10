var plugin = {

    hosts: {
        'www.airmiles.ca': true,
        'auth.airmiles.ca': true,
        'oauth.airmiles.ca': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.airmiles.ca/en/profile.html';
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
        if ($('#login-page-user-id-field:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign out"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('strong:contains("Collector Number")').next('div').find('p').text(), /^(\d+)$/i);
        alert(number)
        browserAPI.log("Number: " + number);
        return (typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && account.properties.Number ===  number;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            var signOut = $('a:contains("Sign out"):visible');
            if (signOut.length > 0) {
                signOut.get(0).click();
            }
        });
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#login-page-user-id-field').closest('form');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('#login-page-user-id-field').val(params.account.login);
            util.sendEvent(form.find('#login-page-user-id-field').get(0), 'input');
            form.find('#login-submit-btn').get(0).click();
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    form.find('#login-page-password-field').val(params.account.password);
                    util.sendEvent(form.find('#login-page-password-field').get(0), 'input');
                    form.find('#login-submit-btn').get(0).click();

                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 6000)
                }, 500);
            });

        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div[role="alert"] div.V2Alert__content p span:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }

};
