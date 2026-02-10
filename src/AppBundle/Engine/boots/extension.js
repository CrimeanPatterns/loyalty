var plugin = {

    hosts: {
        'www.boots.ie': true,
        'www.boots.com': true
    },

    getStartingUrl: function (params) {
		switch (params.account.login2) {
            case 'Ireland':
                return 'https://www.boots.ie/BootsLogonForm?myAcctMain=1';
            case 'UK':
            default:
                return "https://www.boots.com/BootsLogonForm?myAcctMain=1";
        }
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
        if ($('form[id = "gigya-login-form"]:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.my_account_summary_bold:contains("Email address:")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var email = $('.my_account_summary_bold:contains("Email address:")').next().text();
        browserAPI.log("email: " + email);
        return account.login == email;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
			switch (params.account.login2) {
				case 'Ireland':
					document.location.href = 'http://www.boots.ie/Logoff?catalogId=28502&myAcctMain=1&langId=-1&storeId=11353&deleteCartCookie=true';
				case 'UK':
				default:
					document.location.href = 'http://www.boots.com/Logoff?catalogId=28501&myAcctMain=1&langId=-1&storeId=11352&deleteCartCookie=true';
			}
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[id = "gigya-login-form"]:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input.gigya-input-submit').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.gigya-form-error-msg:visible li');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

};
