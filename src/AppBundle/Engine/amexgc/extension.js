var plugin = {

    hosts: {
        'www279.americanexpress.com': true,
        'wwww.americanexpress.com': true,
        'prepaidbalance.americanexpress.com': true,
    },

    getStartingUrl: function (params) {
        var clientKey;
        if (params.account.login2 == 'India') {
            browserAPI.log("Region India");
            clientKey = 'india%20gift%20card';
        }
        if (params.account.login2 == 'UK') {
            browserAPI.log("Region UK");
            clientKey = 'uk';
        }
        else {
            browserAPI.log("Region USA");
            clientKey = 'retail%20sales%20channel';
        }

        return 'https://prepaidbalance.americanexpress.com/GPTHBIWeb/validateIPAction.do?clientkey=' + clientKey;
    },

    start: function (params) {
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        var form = $('form[name = "viewTxnHistoryForm"]');
        if (form.length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').attr('href')) {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        return false;
    },

    logout: function () {
        browserAPI.log("logout");
        //provider.setNextStep('start');
        //document.location.href = 'https://www.thankyou.com/logout.jspx';
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "viewTxnHistoryForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            params.account.login = params.account.login.replace(/\D/g, "");

            var login;
            if (params.account.login2 == 'India' || params.account.login2 == 'UK') {
                browserAPI.log("Region " + params.account.login2);
                login = params.account.login;
            }
            else {
                browserAPI.log("Region USA");
                login = params.account.login.substring(0, 4) + '-' + params.account.login.substring(4, 10) + '-' + params.account.login.substring(10, 15);
            }
            //console.log(login);
            //console.log(params.account.login);
            form.find('input[name = "cardDetailsVO.cardNumber"]').val(login);
            provider.setNextStep('passwordEntering');

            if (params.account.login2 == 'India' || params.account.login2 == 'UK') {
                browserAPI.log("Region " + params.account.login2);
                provider.eval("validate();");
            }
            else {
                browserAPI.log("Region USA");
                form.find('#chksubmit').click();
            }
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    passwordEntering: function(params) {
        browserAPI.log("Password");
        var form = $('form[name = "viewTxnHistoryForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "cardDetailsVO.cscNumber"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');

            if (params.account.login2 == 'India' || params.account.login2 == 'UK') {
                browserAPI.log("Region " + params.account.login2);
                provider.eval("validate();");
            }
            else {
                browserAPI.log("Region USA");
                form.find('#chksubmit').click();
            }
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.passwordFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.successmessage');
        if (errors.length == 0)
            errors = $('font:contains("Invalid or missing information")');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
};