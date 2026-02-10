var plugin = {
    hosts: {'skybonus.delta.com': true},

    getStartingUrl: function(params) {
        return 'http://skybonus.delta.com/';
    },

    start: function(params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (plugin.isLoggedIn(params)) {
                    plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)

            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");

        if ($('a:contains("LOG OUT"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }

        if ($('#userNameID:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        return null;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('login', function () {
            setTimeout(function () {
                $('a:contains("LOG OUT")').get(0).click();
            }, 2000);
        });
    },

    login: function(params) {
        browserAPI.log("login");

        if ($('#userNameID:visible').length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        // knockoutjs
        provider.eval("ko.dataFor($('#userNameID').get(0)).userName('" + params.account.login + "')");
        provider.eval("ko.dataFor($('#passwordID').get(0)).password('" + params.account.password + "')");

        $('#loginBtnID').click();
        setTimeout(function(){
            plugin.checkLoginErrors(params);
        }, 7000);
    },

    checkLoginErrors: function(params) {
        const errors = $('#errorMessage');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.setNextStep('loginComplete', function () {
            document.location.href = 'https://skybonus.delta.com/content/skybonus/corporate/us/en/posthome.html';
        });
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};
