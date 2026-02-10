var plugin = {

    hosts: {'secure.famousfootwear.com' : true, 'www.famousfootwear.com': true},

    getStartingUrl: function(params) {
        return 'https://www.famousfootwear.com/account/dashboard';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function(params) {
        browserAPI.log("start");
        // cash back
        if (document.location.href.indexOf('partnerid') > 0) {
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }// if (document.location.href.indexOf('partnerid') > 0)
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.loginComplete(params);
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

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function() {
            document.location.href = 'https://www.famousfootwear.com/api/calxa/account/logoff';
        });
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form.sign-in-form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign Out"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // browserAPI.log("account: " + JSON.stringify(account));
        browserAPI.log("account properties: " + JSON.stringify(account.properties));
        var number = util.findRegExp( $( "script:contains('user.attributes.rewardsMemberID')").get(1).text, /data\.set\('user\.attributes\.rewardsMemberID', '(.+?)'\);/i);
        browserAPI.log("number: " + number);
        return (
            (typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.RewardsNumber) !== 'undefined')
            && (account.properties.RewardsNumber !== '')
            &&  number
            && (number == account.properties.RewardsNumber)
        );
    },

    login: function(params) {
        browserAPI.log("login");
        var form = $('form.sign-in-form:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved login");
            provider.eval("ko.dataFor($('input.email-input').get(0)).userName('" + params.account.login + "')");
            provider.eval("ko.dataFor($('input.password-field__input').get(0)).password('" + params.account.password + "')");
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button:contains("SIGN IN")').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function(params) {
        browserAPI.log('checkLoginErrors');
        var errors = $('p.error-message:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
		} else
            plugin.loginComplete(params);
    },

	loginComplete: function(params) {
        browserAPI.log('loginComplete');
		provider.complete();
	}
};
