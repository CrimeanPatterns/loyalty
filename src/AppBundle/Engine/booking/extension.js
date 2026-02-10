var plugin = {

    hosts: {'secure.booking.com': true, 'www.booking.com': true, '/\\w+\\.booking\\.com/': true},
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params) {
        return "https://secure.booking.com/myreservations.en-us.html";
    },

    loadLoginStart: function(params) {
        browserAPI.log("loadLoginStart");
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
                        plugin.logout(params);
                }
                else
                    plugin.loadLoginForm(params);
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
        var notYou = $('.oauth-not-me-link:contains("Not you?"):visible');
        if (notYou.length > 0) {
            provider.setNextStep('start', function () {
                browserAPI.log("click 'not you'");
                notYou.get(0).click();
                return false;
            });
        }
        if ($('a.user_access_menu_auth_low_not_me').length > 0) {
            browserAPI.log("logged in = false");
            return false;
        }
        if ($('input[value *= "Sign out"]').length > 0) {//+
            browserAPI.log("logged in = true");
            return true;
        }
        if ($('span.user_firstname').length > 0) {//+
			browserAPI.log("logged in = true");
			return true;
		}
        if ($('.bui-dropdown-menu__text:contains("Sign out")').length > 0) {
            browserAPI.log("logged in = false");
            return true;
        }
		if ($('#formwrap').find('form.user_access_form_js').length > 0 || $('form.nw-signin').length) {
			browserAPI.log('logged in = false');
			return false;
		}
		// mobile
		if (provider.isMobile && $('div.user-access-menu-lightbox--signin:visible').length > 0) {
			browserAPI.log('logged in = false');
			return false;
		}
		return null;
	},

	isSameAccount: function(account) {
		//for debug only
		//browserAPI.log("account: " + JSON.stringify(account));
		var login = $('form.my_bookings_menu input[name="email"][value!=""]');
        if (login.length) {
            login = login.val().toLowerCase();
        } else {
            login = $('script:contains("avoidingXSSviaLocationHash")');
            login = util.findRegExp(login.text(), /email:\s*"(.+?)"/);
        }
        browserAPI.log("login: " + login);
        var res = ( (typeof(account.login) != 'undefined') && (account.login != '') && (login == account.login.toLowerCase()) );
        browserAPI.log('isSameAccount: ' + res);
        return res;
	},

	logout: function(){
        browserAPI.log("logout");
		provider.setNextStep('loadLoginStart', function () {
            if ($('span.user_firstname').length > 0) {
                $('span.user_firstname').get(0).click();
                if ($('form[class*="signout"]').length > 0)
                    $('form[class*="signout"]').submit();
            }
            else
                document.location.href = 'https://secure.booking.com/myreservations.en-us.html?tmpl=profile/myreservations;logout=1';
        });
	},

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        var signIn = $('a:contains("Sign in to your account")');
        if (provider.isMobile && signIn.length) {
            provider.setNextStep('login', function () {
                signIn.get(0).click();
            });
        } else
            plugin.login(params);

    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://secure.booking.com/confirmation.en-gb.html";
            });
            return;
        }
        var notMe = $('a.user_access_menu_auth_low_not_me');
        if (notMe.length) {
            provider.setNextStep('login', function () {
                notMe.get(0).click();
            });
        }
        var tab = $('div[class*="form-tabs"]:contains("Sign in"):visible');
        if (tab.length > 0)
            tab.get(0).click();

        var form = $('form.nw-signin:visible');
        if (form.length > 0) {
            var signMenu = $('div[data-target = "user_access_signin_menu"]');
            if (signMenu.length)
                signMenu.get(0).click();
            setTimeout(function () {
                browserAPI.log("submitting saved credentials");
                // reactjs
                provider.eval(
                    "var FindReact = function (dom) {" +
                    "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                    "        return dom[key];" +
                    "    }" +
                    "    return null;" +
                    "};" +
                    "FindReact(document.querySelector('input[name = \"username\"]')).onChange({target:{value:'" + params.account.login + "'}, preventDefault:function(){}});"
                );
                form.find('button[type = "submit"]:contains("Continue with")').get(0).click();
                setTimeout(function () {
                    form = $('form.nw-signin:visible');
                    // reactjs
                    provider.eval(
                        "var FindReact = function (dom) {" +
                        "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                        "        return dom[key];" +
                        "    }" +
                        "    return null;" +
                        "};" +
                        "FindReact(document.querySelector('input[name = \"password\"]')).onChange({target:{value:'" + params.account.password + "'}, preventDefault:function(){}});"
                    );
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('button[type = "submit"]:contains("Sign in")').get(0).click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 5000);
                    });
                }, 1000);
            }, 2000);
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
	},

	checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div[class*="alert-error"]:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
	},

	loginComplete: function(params) {
        browserAPI.log('loginComplete');
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			if ($('#MyTripsContainer').length > 0) {
				plugin.toItineraries(params);
			} else {
                var menu = $('a[data-command = "show-profile-menu"]');
                if (menu.length > 0) {
                    menu.get(0).click();
                    util.waitFor({
                        selector: 'input[value *= "secure.booking.com/myreservations.html"]',
                        success: function(elem) {
                            provider.setNextStep('itLoginComplete', function() {
                                elem.next('input').click();
                            });
                        },
                        fail: function() {
                            provider.setError(util.errorMessages.itineraryFormNotFound);
                        }
                    });
                }
            }
		} else {
			provider.complete();
        }
	},

    getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
        var properties = params.account.properties.confFields;
        var form = $('input[name = "pincode"]').closest('form.user_access_form');
        if (form.length > 0) {
            form.find('input[name = "bn"]').val(properties.ConfNo);
            form.find('input[name = "pincode"]').val(properties.Pin);
            provider.setNextStep('itLoginComplete', function () {
                form.submit();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

	toItineraries: function(params) {
        browserAPI.log("toItineraries");
		var confNo = params.account.properties.confirmationNumber;
		var link = $('div.mb-block__hotel-name a[href*="' + confNo + '"]');
        if (link.length === 0)
            link = $('a.bui-dropdown-menu__button[href*="reservation_id=' + confNo + '"]').closest('.mtr-timeline__reservation').find('.mtr-two-column-card__link');
        if (link.length === 0)
            link = $('div#' + confNo + ' a.b-button_primary');
        if (link.length !== 1)
            link = $('div#' + confNo + ' a.custom_track[data-trackname = "cancel_or_change"]');
		if (link.length === 1) {
			provider.setNextStep('itLoginComplete', function() {
			    if (link.attr('href').indexOf('https:') !== -1)
                    document.location.href = link.attr('href');
                else
                    document.location.href = "https://secure.booking.com/" + link.attr('href');
            });
		} else
            provider.setError(util.errorMessages.itineraryNotFound);
	},

	itLoginComplete: function(params) {
		provider.complete();
	}
};
