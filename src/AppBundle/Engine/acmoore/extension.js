var plugin = {

    hosts: {'www.michaels.com': true, 'michaels.com': true},

    getStartingUrl: function (params) {
        return 'https://www.michaels.com/Account';
    },

    LoadLoginForm: function (params) {
        browserAPI.log("LoadLoginForm");
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

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.findRegExp( $('h2:contains("Hello,")').text(), /Hello,\s*([^<\!]+)/i);//todo
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && name
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($("#navigation").find("div.logout-signup").length === 0 && $("form#signInRegister").length === 1) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($("#navigation").find("div.logout-signup").length === 1 && $("form#signInRegister").length === 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('LoadLoginForm', function () {
            document.location.href = $("#navigation").find("div.logout-signup > a.user-login").attr('href');
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#signInRegister');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#dwfrm_login_username').val(params.account.login);
            form.find('input#dwfrm_login_password').val(params.account.password);
            form.find('input#dwfrm_login_rememberme').prop('checked', true);

            provider.setNextStep('checkLoginErrors', function () {
                $("button[name = 'dwfrm_login_login']").click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000)
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('form#signInRegister').find('span.error');
        if (errors.length === 0) {
            errors = $('form#signInRegister').find('div.error-form');
        }
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
};