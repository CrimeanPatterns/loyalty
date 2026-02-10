var plugin = {

    hosts: {'www.amctheatres.com': true},

    getStartingUrl: function (params) {
        return 'https://www.amctheatres.com/amcstubs/wallet';
    },
	
	start: function (params) {
        browserAPI.log("start");
		if ($('button:contains("No Thanks"):visible').length>0) {
			browserAPI.log("found offer. close");
			provider.setNextStep('LoadLoginForm', function () {
				$('button:contains("No Thanks"):visible').get(0).click();
				plugin.LoadLoginForm(params);
			});
		}
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

    LoadLoginForm: function (params) {
        browserAPI.log("LoadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('span:contains("Sign In")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('span:contains("Hello")').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('h3:contains("AMC Stubs Premiere Number") + span').text(), /\(?([^<\)]+)\)?/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('LoadLoginForm', function () {
            $('a:contains("Sign Out")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        // open login form
        // $('button:contains("Sign In")').get(0).click();
        // wait login form
        var counter = 0;
        var login = setInterval(function () {
            var form = $('form.form-full-width-fields');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                
				
				var email = form.find('input[type="email"]').get(0);
				email.defaultValue = "";
				email.value = params.account.login;
				util.sendEvent(email, 'change');
				
				var password = form.find('input[type="password"]').get(0);
				password.defaultValue = "";
				password.value = params.account.password;
				util.sendEvent(password, 'change');

				if (form.find('button:contains("Sign In")').is("[disabled]").length > 0) {
					provider.setError(util.errorMessages.loginFormNotFound);
					return;
				}    
        
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button:contains("Sign In")').get(0).click();
                    setTimeout(function () {
                        plugin.checkLoginErrors();
                    }, 10000)
                });
            }
            if (counter > 80) {
                clearInterval(login);
				provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },
	
    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('span[class *= "error-message"]:visible');
        if (errors.length == 0)
            errors = $('div.ErrorMessageAlert:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}
