var plugin = {

    hosts: {'www.dunkindonuts.com': true},

    getStartingUrl: function(params){
        return 'https://www.dunkindonuts.com/en/account/add-value';
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

    isLoggedIn: function(params) {
        browserAPI.log("isLoggedIn");
        if ($('form[action *= "signin"]:visible').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('a:contains("Sign Out"):visible').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return false;
        //var name = plugin.findRegExp($('#welcome'), /Welcome\s([^<]+)/i);
        //browserAPI.log("name: " + name);
        //name = name.toLowerCase();
        //return ((typeof(account.properties) != 'undefined')
        //    && (typeof(account.properties.Name) != 'undefined')
        //    && (account.properties.Name != '')
        //    && (0 === account.properties.Name.toLowerCase().indexOf(name)));
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('login', function () {
            $('a:contains("Sign Out")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[action *= "signin"]:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            // captcha recognize
            setTimeout(function() {
                var captcha = form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:visible');
                if (captcha.length > 0) {
                    provider.reCaptchaMessage();
                    browserAPI.log("waiting...");
                    provider.setNextStep('checkLoginErrors', function() {
                        var counter = 0;
                        var login = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            var errors = $('ul.parsley-errors-list:visible');
                            if (errors.length == 0)
                                errors = $('div.u-page-error:visible');
                            if (errors.length > 0) {
                                clearInterval(login);
                                provider.setError(errors.text(), true);
                            }// if (errors.length > 0)
                            if (counter > 80) {
                                clearInterval(login);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                            counter++;
                        }, 500);
                    });
                }// if (captcha.length > 0)
                else {
                    browserAPI.log("captcha is not found");
                    provider.setNextStep('checkLoginErrors', function() {
                        form.find('input[type = "submit"]').get(0).click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 3000)
                    });
                }
            }, 2000);
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('ul.parsley-errors-list:visible');
        if (errors.length == 0)
            errors = $('div.u-page-error:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }

}