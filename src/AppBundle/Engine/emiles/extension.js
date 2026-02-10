var plugin = {

    hosts: {'emiles.com': true},

    getStartingUrl: function (params) {
        return 'https://emiles.com';
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
        if ($('button:contains("Get Started")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.user-dd-items a:contains("Log Out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        return false;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('.user-dd-items a:contains("Log Out")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // open login form
        $('.login-button button:contains("Login")').get(0).click();
        setTimeout(function () {
            var form = $('h2:contains("Please sign in using your Email")').closest('div').find('form');
            if (form.length > 0) {
                // reactjs
                provider.eval(
                    "var FindReact = function(dom) {"
                    + "for (var key in dom)"
                    + "if (0 == key.indexOf(\"__reactInternalInstance$\")) {"
                    + "var compInternals = dom[key]._currentElement;"
                    + "var compWrapper = compInternals._owner;"
                    + "var comp = compWrapper._instance;"
                    + "return comp;"
                    + "}"
                    + "return null;"
                    + "};"
                    + "FindReact(document.querySelector('[placeholder=Email]')).props.onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});"
                    + "FindReact(document.querySelector('[placeholder=Password]')).props.onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
                );
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[type = "submit"]').get(0).click();
                    plugin.checkLoginErrors(params);
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 1000);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var counter = 0;
        var checkLoginErrors = setInterval(function () {
            browserAPI.log("checkLoginErrors: waiting... " + counter);
            var error = $('.ant-modal-body h2:contains("Login failed!")');
            if (error.length > 0) {
                clearInterval(checkLoginErrors);
                provider.setError(error.text());
            }
            if (plugin.isLoggedIn() === true || counter > 100) {
                clearInterval(checkLoginErrors);
                provider.complete();
            }
            counter++;
        }, 50);
    }

};