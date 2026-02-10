var plugin = {
    //keepTabOpen: true,
    hosts: {'www.fuelrewards.com': true, 'fuelrewards.com': true},

    getStartingUrl: function (params) {
        return 'https://www.fuelrewards.com/fuelrewards/login-signup?utm_source=HP&utm_medium=um&utm_campaign=login';
    },
	gotostart: function (params) {
		browserAPI.log("gotostart");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[id="loginform"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href="/fuelrewards/logout.html"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('div[class="user-account"]').text().replace(/Account#\s+([\d-]+).*/i, "$1");
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('gotostart', function () {
            document.location.href = 'https://www.fuelrewards.com/fuelrewards/logout.html';
        });
    },

	login: function (params) {
		// inchect finish recaptcha event
		$('body').append("<div id=\"onSuccessDiv\" onclick=\"window.onSuccess2 = window.onSuccess; window.onSuccess = function(){ setTimeout(function(){ $('div.g-recaptcha').attr('success', 1); }, 1000); window.onSuccess2(); }\"></div>");
		$("#onSuccessDiv").click();
		
        browserAPI.log("login");
		var form = $('form[id="loginform"]');
		if (form.length > 0) {
			util.sendEvent(form.find('input[name = "userId"]').val(params.account.login).get()[0], 'input');
			util.sendEvent(form.find('input[name = "password"]').val(params.account.password).get()[0], 'input');
			var captcha = util.findRegExp( form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]').attr('src'), /k=([^&]+)/i);
			if(captcha && captcha.length > 0){
				provider.reCaptchaMessage();
				util.waitFor({
					selector: 'div.g-recaptcha[success=1]',
					success: function(){
						plugin.submitLogin(form);
					},
					fail: function(){
						provider.setError(util.errorMessages.captchaErrorMessage, true);
					},
					timeout: 120
				});
			}else
				plugin.submitLogin(form);
		}else
            provider.setError(util.errorMessages.loginFormNotFound);
    },
	
	submitLogin: function(form) {
		provider.setNextStep('checkLoginErrors', function () {
			form.find('#loginButton').focus().get(0).click();
			setTimeout(function(){
				plugin.checkLoginErrors();
			}, 2000);
		});
	},

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
		var errors = $('p.reg-box--error-text>span:visible, label.error:visible').not("[style*=hidden]");
        if (errors.length > 0)
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }

}