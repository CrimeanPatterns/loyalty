var plugin = {

    hosts: {'www.bloomingdales.com': true, 'www1.bloomingdales.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params){
	    return 'https://www.bloomingdales.com/loyallist/accountsummary';
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

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if ($('#dashboard_AccountLogout').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#sign-in-section').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return typeof account.properties !== 'undefined'
        && typeof account.properties.LoyallistNumber !== 'undefined'
        && account.properties.LoyallistNumber !== ''
        && $('div[class *= "number"] span:contains("' + account.properties.LoyallistNumber + '")').eq(0).length;
    },

    logout: function(params){
        browserAPI.log("logout");
		document.cookie = "SignedIn=; path=/; domain=.bloomingdales.com";
        provider.setNextStep('login', function() {
		    document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('div#sign-in-section');
        if(form.length > 0){
            browserAPI.log("submitting saved credentials");
            form.find('input#email').val(params.account.login.toUpperCase());
            form.find('input#pw-input').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function() {
                form.find('#sign-in').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function(params){
        browserAPI.log("checkLoginErrors");

        // Popup - Complete your Profile
        if ($('div > h1:contains("Complete your Profile")').length) {
            provider.complete();
            //provider.setError('Complete your Profile');
            return;
        }

		var errors = $('#ul-login-error');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    }
};
