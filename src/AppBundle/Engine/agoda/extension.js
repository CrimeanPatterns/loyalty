var plugin = {
    //keepTabOpen: true,
	hosts: {'www.agoda.com': true, 'my.agoda.com': true},

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	getStartingUrl: function(params) {
		return "https://www.agoda.com/";
	},

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	start: function(params) {
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
                        plugin.logout();
                } else {
                    provider.setNextStep('login', function () {
                        var link = $('#sign-in-btn:visible');
                        if (link.length > 0)
                            link.get(0).click();
                        setTimeout(function () {
                            plugin.login(params);
                        }, 2000);
                    });
                }

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
		if ($('#sign-in-btn:visible').length > 0) {
			browserAPI.log('not logged in');
			return false;
		}
        if ($('#sign-out-btn').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
	},

	isSameAccount: function(account) {
        return false;
		// for debug only
		//browserAPI.log("account: " + JSON.stringify(account));
        //var name = util.findRegExp( $('li.club_welcome').text().replace(/[^A-Z]+/ig, ' ') , /Welcome\s*([^<]+)/i);
        //browserAPI.log("name: " + name);
        //return ((typeof(account.properties) != 'undefined')
        //    && (typeof(account.properties.Name) != 'undefined')
        //    && (account.properties.Name != '')
        //    && (name == account.properties.Name));
	},

    logout: function () {
        browserAPI.log("logout");
		provider.setNextStep('loadLoginForm', function () {
            $('#sign-out-btn').get(0).click();
        });
	},

	login: function(params) {
		browserAPI.log("login");
        var link = $('#sign-in-btn:visible');
        if (link.length > 0)
            link.get(0).click();
        util.waitFor({
            selector: '#signin-content:visible, form.signin-form:visible, form.EmailSignInPanel:visible, form.mmb-signin-form:visible',
            success: function(form) {
                browserAPI.log("submitting saved credentials");
                // form.find('input[name = "email"]').val(params.account.login);
                // form.find('input[name = "password"]').val(params.account.password);

                // reactjs
                provider.eval(
                    "var FindReact = function (dom) {" +
                    "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                    "        return dom[key];" +
                    "    }" +
                    "    return null;" +
                    "};" +
                    "FindReact($('#signin-email-input').get(0)).onChange({currentTarget:{value:'" + params.account.login + "', name:'email'}});" +
                    "FindReact($('#signin-password-input').get(0)).onChange({currentTarget:{value:'" + params.account.password + "', name:'password'}});"
                );

                provider.setNextStep('checkLoginErrors', function () {
                    $('#sign-in-submit-button').trigger('click');
                    setTimeout(function () {
                        if ($('#signin-respond-confirm-login:visible').length > 0)//sometimes captcha no display after on
                            provider.reCaptchaMessage();
                        waiting();
                    }, 3000);
                });

                function waiting() {
                    browserAPI.log("waiting...");
                    var counter = 0;
                    var login = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        var success = $('a#sign-in:visible').length;
                        if (success.length > 0
                            || $('#signin-respond-warning-email-password-incorrect:visible, #signin-email-wrong-format:visible, div.error-message').length > 0) {
                            clearInterval(login);
                            plugin.checkLoginErrors(params);
                        }
                        if (counter > 120) {
                            clearInterval(login);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                        }
                        counter++;
                    }, 500);
                }
            },
            fail: function() {
                provider.setError(util.errorMessages.loginFormNotFound);
            },
        });
	},

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var counter = 0;
		var checkLoginErrors = setInterval(function() {
			var errors = $('#signin-respond-warning-email-password-incorrect:visible, #signin-email-wrong-format:visible, div.error-message').clone();
			if (errors.length > 0) {
                errors.find('a').remove();
				provider.setError(util.trim(errors.text()));
				clearInterval(checkLoginErrors);
			}
            if (counter > 5) {
                clearInterval(checkLoginErrors);
                plugin.loginComplete(params);
            }
            counter++;
		}, 500);
	},

	loginComplete: function(params) {
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			if ($('a#ctl00_ctl00_MainContent_ContentMain_lbtTotalBooking').length > 0) {
				plugin.toItineraries(params);
			}// if ($('a#ctl00_ctl00_MainContent_ContentMain_lbtTotalBooking').length > 0)
			else {
				provider.setNextStep('toItineraries', function () {
				    document.location.href = 'https://www.agoda.com/account/bookings.html';
                });
			}
			return;
		}
		provider.complete();
	},

	toItineraries: function(params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var counter = 0;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + toItineraries);

            var span = $('span[data-selenium = "booking-id-value"]:contains("' + confNo + '")');
            if (span.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function() {
                    span.click();
                });
            }// if (numbers.length > 0)
            if (counter > 10) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
	},

	itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
		provider.complete();
	}
};
