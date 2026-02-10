var plugin = {

    hosts: {
        'www.flybe.com': true,
        'accounts.flybe.com': true
    },

    getStartingUrl: function (params) {
        return 'https://accounts.flybe.com/o3r-app-server/flybe/profile';
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
        if ($('form[name = "login"]:visible').find('button.primary:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a.logout:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return false;

        var name = $('#notYouMessage').text();
        browserAPI.log("name: " + name);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (-1 < name.toLowerCase().indexOf(account.properties.Name.toLowerCase())));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('a.logout').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "login"]:visible');
        var btn = form.find('button.primary:visible');
        // browserAPI.log("waiting... " + counter);
        if (form.length > 0 && btn.length > 0) {
            // clearInterval(login);
            browserAPI.log("submitting saved credentials");
            var login = form.find('input[placeholder="Enter your email"]');
            var pass = form.find('input[placeholder="Enter your password"]');
            login.val(params.account.login);
            pass.val(params.account.password);
            util.sendEvent(login.get(0), 'input');
            util.sendEvent(pass.get(0), 'input');
            provider.setNextStep('checkLoginErrors', function () {
                btn.click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000);
            });
            return;
        }
        provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('span.message-title:visible:eq(0)');
        if (errors.length > 0 && util.filter(errors.text()) != '') {
            provider.setError(util.filter(errors.text()));
        }
        else {
            // bug sometimes appears in the site (Mobile)
            provider.setNextStep('loginComplete', function () {
                document.location.href = 'https://accounts.flybe.com/o3r-app-server/flybe/profile';
            });
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};