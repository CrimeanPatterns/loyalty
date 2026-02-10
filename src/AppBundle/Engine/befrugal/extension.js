var plugin = {
    hosts: {'www.befrugal.com': true},

    getStartingUrl: function (params) {
        return 'http://www.befrugal.com/';
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
            }
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
        if (
            $('input[value="Login"]').length
            || $('a:contains("Log In"):visible').length
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a.logOutLink').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let email = $('input[id = "bf-olark-user"]').val();
        browserAPI.log("email: " + email);

        if (email === account.login) {
            browserAPI.log("isSameAccount:true");
            return true;
        }

        browserAPI.log("isSameAccount:false");
        return false;
    },

    logout: function () {
        browserAPI.log("logout");
        $('a.logOutLink').get(0).click();

        setTimeout(function () {
            $('a.logOutButtons').get(0).click();
        }, 1000)
    },

    isFirstStep: function () {
        browserAPI.log("isFirstStep:" + ($('a[id *= btnNotYou]').length == 0 ? 'true' : 'false'));
        return $('a[id *= btnNotYou]').length == 0;
    },

    goToFirstStep: function () {
        browserAPI.log("goToFirstStep");
        $('a[id *= btnNotYou]').get(0).click();
    },

    login: function (params) {
        browserAPI.log("login");
        if (!plugin.isFirstStep()) {
            plugin.goToFirstStep();
        }

        let login = $('input[value="Login"], a:contains("Log In"):visible');
        login.get(0).click();

        plugin.postForm(params);
    },

    getLoginBtn: function (form) {
        let logIn = form.find('button[name = "emailLogin"]');
        return logIn;
    },

    postForm: function (params) {
        browserAPI.log("postForm");
        const form = $('form.PreLogin, form.Login');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "Username"]').val(params.account.login);
        let logIn = plugin.getLoginBtn(form);
        logIn.get(0).click();

        setTimeout(function () {
            form.find('input[name = "Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                let logIn = plugin.getLoginBtn(form);
                logIn.get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }, 1000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('*[class *= "bf-AuthMessage"]:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    },
}