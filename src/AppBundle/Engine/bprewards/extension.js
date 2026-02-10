var plugin = {

    hosts: {'bpdriverrewards.com': true, 'mybpstation.com' : true, 'www.mybpstation.com': true},

    getStartingUrl: function (params) {
        return 'https://www.mybpstation.com/account';
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
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('a#DR_login').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('div.logged_info > button').text();
        browserAPI.log("name: " + name);
            return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (name.trim().toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a[href *= "logout"]').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // open login form
        $('a#DR_login').get(0).click();

        var form = $('form[id = "user-login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "name"]').val(params.account.login);
            form.find('input[name = "pass"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                provider.eval("document.getElementById('edit-submit').click()");
                // form.submit();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        var errors = $('.error:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

}
