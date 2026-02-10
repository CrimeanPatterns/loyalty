var plugin = {

    hosts: {'www.qantasbusinessrewards.com': true, 'accounts.qantas.com': true},

    getStartingUrl: function (params) {
        return 'https://www.qantasbusinessrewards.com/myaccount';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
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
                        plugin.loginComplete(params);
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
        if ($('#logoutButton').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form:has(input[name = "email"])').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = null;
        /*
        $.ajax({
            url: "https://www.qantasbusinessrewards.com/api/qbr/dashboard",
            async: false,
            success: function (data) {
                data = $(data);
                // console.log("---------------- data ----------------");
                // console.log(data[0]);
                // console.log("---------------- data ----------------");
                if (typeof (data[0].abn) != 'undefined')
                    number = data[0].abn;
            }// success: function (data)
        });// $.ajax({
        */
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.ABN) != 'undefined')
            && (account.properties.ABN != '')
            && number
            && (number == account.properties.ABN));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('#logoutButton').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form:has(input[name = "email"])');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact(document.querySelector('input[name = \"email\"]')).onChange('" + params.account.login + "');" +
                "FindReact(document.querySelector('input[name = \"password\"]')).onChange('" + params.account.password + "');"
            );
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button:contains("LOGIN")').trigger('click');
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div[class *= "ErrorComponent__Message-sc-"]:visible');
        if (errors.length == 0)
            errors = $('p[class *= "InputError-sc-"]:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log('loginComplete');
        provider.complete();
    }
};