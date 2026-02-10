var plugin = {

	hosts: {'secure.carnival.com': true, 'www.carnival.com': true},

	getStartingUrl: function(params) {
		return "https://www.carnival.com/BookedGuest/guestmanagement/myprofile";
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
        if ($('form.lrc-form').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#ccl_header_expand-login-link').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp($('p[class *= "vifp"]:contains("VIFP Club #")').text(), /#\s*:\s*([^<]+)/i);
        browserAPI.log("number: " + number);
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.VIFPClubNumber) !== 'undefined'
            && account.properties.VIFPClubNumber !== ''
            && number
            && number === account.properties.VIFPClubNumber;
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://www.carnival.com/bookedguest/guestmanagement/mycarnival/logout';
        });
    },

	login: function(params) {
		browserAPI.log("login");
		var form = $('form.lrc-form');
        if (form.length > 0) {
			browserAPI.log("submitting saved credentials");
            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact($('#username').get(0)).onChange({currentTarget:{value:'" + params.account.login + "'}, isDefaultPrevented:function(){}});" +
                "FindReact($('#password').get(0)).onChange({currentTarget:{value:'" + params.account.password + "'}, isDefaultPrevented:function(){}});"
            );
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type="submit"]:contains("LOG IN!")').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
		}
		else
            provider.setError(util.errorMessages.loginFormNotFound);
	},

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
		var errors = $('ul li.errf-item:visible');
		if (errors.length > 0)
			provider.setError(errors.text());
		else
			plugin.loginComplete(params);
	},

	loginComplete: function(params) {
        browserAPI.log('loginComplete');
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.carnival.com/BookedGuest/';
            });
			return;
		}
        if (provider.isMobile) {
            provider.setNextStep('itLoginComplete', function () {
                document.location.href = 'https://www.carnival.com/BookedGuest/guestmanagement/mycruises';
            });
            return;
        }
		provider.complete();
	},

	toItineraries: function(params) {
        browserAPI.log('toItineraries');
		var confNo = params.account.properties.confirmationNumber;
		var link = $('a[href*="' + confNo + '"], a:contains("Booking #' + confNo + '")');
		if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function () {
                link.get(0).click();
            });
		}// if (link.length > 0)
		else
            provider.setError(util.errorMessages.itineraryNotFound);
	},

	itLoginComplete: function(params) {
        browserAPI.log('itLoginComplete');
        if (provider.isMobile)
            setTimeout(function () {
                window.scrollTo(0, 0);
            }, 500);
		provider.complete();
	}
};
