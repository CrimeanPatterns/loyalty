var plugin = {

    hosts: {'member.flyerbonus.com': true, 'flyerbonus.com': true, 'flyerbonus.bangkokair.com': true},

    getStartingUrl: function (params) {
        return 'https://flyerbonus.bangkokair.com/member/';
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
                        plugin.logout();
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
        if ($('a[href *= logout]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#form-auth-login').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('div.card-id-m > strong').text();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://flyerbonus.bangkokair.com/?logout=yes';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('#form-auth-login');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "USER_LOGIN"]').val(params.account.login);
            form.find('input[name = "USER_PASSWORD"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[name = "Login"]').click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('font.errortext');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}