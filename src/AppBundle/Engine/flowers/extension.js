var plugin = {

    hosts: {
        'ww12.1800flowers.com': true,
        'ww11.1800flowers.com': true,
        'ww10.1800flowers.com': true,
        'ww30.1800flowers.com': true,
        'ww32.1800flowers.com': true,
        'ww31.1800flowers.com': true,
        'flowers.instorecard.com': true,
        'www.1800flowers.com': true
    },

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    getStartingUrl: function (params) {
        return 'https://www.1800flowers.com/';
    },

    startFromChase: function (params) {
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    fromCashback: function (params) {
        browserAPI.log("fromCashback");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    start: function (params) {
        // cash back
        if (document.location.href.indexOf('AFFILIATES') > 0) {
            provider.setNextStep('start');
            document.location.href = plugin.getStartingUrl(params);
            return;
        }

        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + start);
            var signIn = $('a#SignIn');
            if (signIn.length > 0 || $('div#hdrSignInName:visible').text() != "") {

                clearInterval(start);

                if (plugin.isLoggedIn()) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else {
                    provider.setNextStep('login');
                    signIn.get(0).click();
                }
            }
            if (counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
            }
            counter++;
        }, 500);
    },

    isSameAccount: function (account) {
        return false;
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('div#hdrSignInName:visible').text() != "") {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('a#SignIn').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    logout: function () {
        provider.setNextStep('loadLoginForm');
        $('span.signout > a').get(0).click();
    },

    loadLoginForm: function (params) {
        if ($('form[name = "Logon"]').length > 0)
            plugin.login(params);
        else {
            provider.setNextStep('start');
            document.location.href = plugin.getStartingUrl(params);
        }
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "Logon"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "logonId"]').val(params.account.login);
            form.find('input[name = "logonPassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.submit();
        } else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        var errors = $("td.signupError span");
        if (errors.length > 0) {
            provider.setError(errors.text());
        }
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        if (typeof(params.account.fromPartner) == 'string') {
            //setTimeout(provider.close, 1000);
            // don't reopen page
            var info = {message: 'warning', reopen: false, style: 'none'};
            browserAPI.send("awardwallet", "info", info);
        }
        provider.complete();
    }
};
