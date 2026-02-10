var plugin = {

    hosts : {
        'www.ebags.com'    : true,
        'secure.ebags.com' : true
    },

    cashbackLink      : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

    getStartingUrl : function(params) {
        return 'https://www.ebags.com/my-account';
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
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

/*    startRegion: function (params) {
        browserAPI.log('startRegion');
        provider.setNextStep('startLogin', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    startLogin : function(params) {
        browserAPI.log('startLogin');
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout(params);
        }
        else
            plugin.loadLogin(params);
    },
*/
    isLoggedIn : function() {
        browserAPI.log('isLoggedIn');
        if ($('a:contains("Sign Out")').length) {
            browserAPI.log('logged in');
            return true;
        }
        if ($('a[title="Login"]').length) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount : function(account) {
        browserAPI.log('isSameAccount');
        if ('undefined' != typeof account.properties
            && 'undefined' != typeof account.properties.Name
            && '' != account.properties.Name) {
            var name = account.properties.Name.split(' ');
            if ($('span.my-account-message:contains("' + name[0] + '")').length)
                return true;
        }
        return false;
    },

    logout : function() {
        browserAPI.log('logout');
        provider.setNextStep('startRegion', function() {
            document.location.href = 'http://www.ebags.com/members/SignOutCustomer';
        });
    },

/*    loadLogin : function(params) {
        browserAPI.log('loadLogin');
        if ($('form#frmSignInResponsive').length)
            return plugin.login(params);
        if (plugin.isLoggedIn(params))
            plugin.logout();
        provider.setError(util.errorMessages.unknownLoginState);
    },*/

    login : function(params) {
        browserAPI.log('login');
        var form = $('form#dwfrm_login');
        if (form.length > 0) {
            form.find('input[id^="dwfrm_login_username"]').val(params.account.login);
            form.find('input[id^="dwfrm_login_password"]').val(params.account.password);
            form.find('input[id^="dwfrm_login_rememberme"]').prop('checked', true);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#login-buttons > button[name = "dwfrm_login_login"]').click();
                setTimeout(function () {
                    plugin.checkLoginErrors();
                }, 5000);
            });
        }else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors : function() {
        browserAPI.log('checkLoginErrors');
        var errors = $('[id^="bouncer-error_dwfrm"]:visible');
        if (errors.length === 0){
            errors = $('.messaging.error:visible');
        }
        if (errors.length > 0) {
            provider.setError(errors.text());
        } else {
            plugin.loginComplete(params);
        }
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        provider.complete();
    }

};
