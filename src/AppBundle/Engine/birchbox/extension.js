var plugin = {

    hosts: {
        'birchbox.com': true,
        'www.birchbox.com': true
    },

    getStartingUrl: function(){
        return 'https://www.birchbox.com/me/account';
    },

    start: function (params) {
        browserAPI.log('start');
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

    isLoggedIn: function (params) {
        browserAPI.log('isLoggedIn');
        if ($('div[class *= "accountDetails"]').length) {
            browserAPI.log('isLoggedIn = true');
            return true;
        }

        if ($('input[id = "email"]').length) {
            browserAPI.log('isLoggedIn = false');
            return false;
        }

        return null;
    },

    isSameAccount: function (params) {
        browserAPI.log('isSameAccount');
        browserAPI.log('isSameAccount = false');
        return false;
    },

    logout: function (params) {
        browserAPI.log('logout');
    },

    login: function (params) {
        browserAPI.log('login');
        util.waitFor({
            selector: 'input[id = "email"]',
            success: function (elem) {
                browserAPI.log('Submitting saved credentials');
                const username = $('input[id = "email"]');
                const password = $('input[name = "password"]');

                username.val(params.account.login);
                util.sendEvent(username.get(0), 'input');
                password.val(params.account.password);
                util.sendEvent(password.get(0), 'input');

                $('button[class *= "button-primary"]:contains("Sign In")').get(0).click();

                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000);
            },
            fail: function(){
                provider.setError(util.errorMessages.loginFormNotFound);
            },
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        const error = $('p.error-message:visible');

        if (util.filter(error.text()) !== '') {
            provider.setError(util.filter(error.text()));
            return;
        }

        provider.complete();
    }

};