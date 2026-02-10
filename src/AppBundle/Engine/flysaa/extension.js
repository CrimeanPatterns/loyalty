var plugin = {

    hosts: {
        'www.flysaa.com': true,
        'voyager.flysaa.com': true,
    },

    getStartingUrl: function(params) {
        return 'https://voyager.flysaa.com/my-voyager/my-voyager';
    },

    start: function(params) {
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
                else {
                    $('a[data-target="#modalVoyagerLogin"]:visible').get(0).click();
                    setTimeout(function () {
                        plugin.login(params);
                    }, 2000);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function() {
        browserAPI.log("isLoggedIn");
        if ($('a[data-target="#modalVoyagerLogin"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[title = "Logout"]:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function(account) {
        const number = $('dt:contains("Member Nº") + dd:eq(0)').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function() {
        browserAPI.log("logout");
        provider.setNextStep("start", function () {
            $('a[title = "Logout"]').get(0).click();
        });
    },

    login: function(params) {
        browserAPI.log("login");
        const form = $('#_voyagerloginportlet_WAR_saaairwaysportlet_loginForm');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "_voyagerloginportlet_WAR_saaairwaysportlet_voyagerId"]').val(params.account.login);
        form.find('input[name = "_voyagerloginportlet_WAR_saaairwaysportlet_pin"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('#_voyagerloginportlet_WAR_saaairwaysportlet_login').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000);
        });
    },

    checkLoginErrors: function(params) {
        const errors = $("#div.portlet-msg-error:visible");

        if (errors.length > 0) {
            provider.setError(errors.text());
        }

        provider.complete();
    }
};