var plugin = {

    hosts: {
        'finishline.com'        : true,
        'www.finishline.com'    : true,
        'account.finishline.com': true
    },

    cashbackLink     : '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        if (provider.isMobile)
            return 'https://m.finishline.com/store/myaccount/login.jsp';
        return 'https://www.finishline.com/store/myaccount/login.jsp';
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

        // if (plugin.isLoggedIn()) {
        //     if (plugin.isSameAccount(params.account))
        //         plugin.loginComplete(params);
        //     else
        //         plugin.logout();
        // } else
        //     setTimeout(function () {
        //         plugin.login(params);
        //     }, 2000);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a.signOut').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[id = "loginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        let number = util.findRegExp($('div.myaccount-promo').text(), /\#([\d]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.CircleNumber != 'undefined')
            && (account.properties.CircleNumber != '')
            && (number == account.properties.CircleNumber)));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a.signOut').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        let form = $('form[id = "loginForm"]');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        form.find('input[name = "username"]').val(params.account.login);
        form.find('input[name = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.submit();
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.text-danger:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
};
