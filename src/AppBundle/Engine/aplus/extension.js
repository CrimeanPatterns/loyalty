var plugin = {

    hosts: {
        'secure.accorhotels.com'        : true,
        'www.accorhotels.com'           : true,
        'authentication.accorhotels.com': true,
        ".accorhotels.com"              : true,
        "all.accor.com"                 : true,
        "secure.accor.com"              : true,
        ".accor.com"                    : true,
        "login.accor.com"               : true,
    },

    clearCache: true,

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params){
        return 'https://all.accor.com/account/index.en.shtml#/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log('start');
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout();
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 20)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log('isLoggedIn');
        if ($('div.ah-login-widget form:visible').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        // if ($('a[href *= "logout"]').length > 0) {
        if ($('h1.heading-account__title:visible').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        return null;
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            $('div[aria-controls="login-nav-menu-wrapper"] > button').get(0).click();
            setTimeout(function () {
                $('button.login-nav__item.login-nav__item--link').get(0).click();
            }, 500)
        });
    },

	isSameAccount: function(account) {
        browserAPI.log('isSameAccount');
        var name = util.findRegExp($('h1.heading-account__title').text(), /Hello\s*([^<]+)/ );
		browserAPI.log('name: ' + name);
		return ((typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.AccountNumber) != 'undefined')
			&& (account.properties.Name !== '')
            && name
			&& (name.toLowerCase() === account.properties.Name.toLowerCase()));
	},

    login: function (params) {
        browserAPI.log('login');

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItineraryWait', function(){
                document.location.href = "https://secure.accor.com/gb/cancellation/search-booking.shtml";
            });
            return;
        }

        let form = $('div.ah-login-widget form:visible');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }// if (form.length > 0)

        browserAPI.log("submitting saved credentials");

        // angularjs 10
        provider.eval(
            "function triggerInput(enteredName, enteredValue) {\n" +
            "      const input = document.querySelector(enteredName);\n" +
            "      var createEvent = function(name) {\n" +
            "            var event = document.createEvent('Event');\n" +
            "            event.initEvent(name, true, true);\n" +
            "            return event;\n" +
            "      }\n" +
            "      input.dispatchEvent(createEvent('focus'));\n" +
            "      input.value = enteredValue;\n" +
            "      input.dispatchEvent(createEvent('change'));\n" +
            "      input.dispatchEvent(createEvent('input'));\n" +
            "      input.dispatchEvent(createEvent('blur'));\n" +
            "}\n" +
            "triggerInput('input[name = \"pf.username\"]', '" + params.account.login + "');\n" +
            "triggerInput('input[name = \"pf.pass\"]', '" + params.account.password + "');"
        );

        provider.setNextStep('checkLoginErrors', function () {
            form.find('button.api-btn__primary').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function(params) {
        browserAPI.log('checkLoginErrors');
		let errors = $('span.api__error-field:visible:eq(0), span#api-service-error:visible');
		if (errors.length > 0 && $.inArray(util.filter(errors.text()), ['', 'UNKNOWN KEY']) === -1) {// strange provider default message on login form
			provider.setError(util.trim(errors.text()));
		    return;
        }

        plugin.loginComplete(params);
    },

	loginComplete: function(params) {
        browserAPI.log('loginComplete');

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://all.accor.com/account/index.en.shtml#/my-bookings';
                setTimeout(function () {
                    plugin.toItineraries(params);
                }, 5000);
            });
            return;
        }

        provider.complete();
	},

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        var counter = 0;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('a[href*="historic.folderNumber=' + params.account.properties.confirmationNumber +'"]').attr('href');
            browserAPI.log('>>>> link ' + link);
            if (link) {
                clearInterval(toItineraries);
                if (link.indexOf('http') !== 0)
                    link = 'https://all.accor.com' + link;
                provider.setNextStep('itLoginComplete', function () {
                    document.location.href = link;
                });
            }// if (link)
            if (counter > 20) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }// if (counter > 20)
            counter++;
        }, 500);
    },

    getConfNoItineraryWait: function(params) {
        browserAPI.log('getConfNoItineraryWait');
        var counter = 0;
        var getConfNoItinerary = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var form = $('form#seacrchForm');
            if (form.length > 0) {
                clearInterval(getConfNoItinerary);
                provider.setNextStep('getConfNoItinerary', function () {
                    provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
                    document.location.href = "https://secure.accor.com/gb/cancellation/search-booking.shtml?t";
                });
                return;
            }// if (form.length > 0)
            if (counter > 20) {
                clearInterval(getConfNoItinerary);
                provider.setError(util.errorMessages.itineraryFormNotFound);
                return;
            }// if (counter > 20)
            counter++;
        }, 500);
    },

	getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
		var properties = params.account.properties.confFields;
        var counter = 0;
        var getConfNoItinerary = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var form = $('form#seacrchForm');
            if (form.length > 0) {
                clearInterval(getConfNoItinerary);
                var date = properties.DateIn, day, month, year;
                var matches = /(\d+)\/(\d+)\/(\d+)/.exec(date);
                if (matches) {
                    month = matches[1];
                    day = matches[2];
                    year = matches[3];
                    date = year + '-' + month + '-' + day;
                }
                provider.setNextStep('itLoginComplete', function () {
                    // angularjs
                    provider.eval("var scope = angular.element(\"#seacrchForm\").scope();"
                        + "scope.$apply(function(){"
                        + "scope.homeCtrl.searchForm.dateIn.$setViewValue('" + date + "');"
                        + "scope.homeCtrl.searchData.dateIn = '" + date + "';"
                        + "scope.homeCtrl.searchForm.dateIn.$render();"
                        + "scope.homeCtrl.searchForm.number.$setViewValue('" + properties.ConfNo + "');"
                        + "scope.homeCtrl.searchForm.number.$commitViewValue('" + properties.ConfNo + "');"
                        + "scope.homeCtrl.searchForm.number.$render();"
                        + "scope.homeCtrl.searchForm.lastName.$setViewValue('" + properties.LastName + "');"
                        + "scope.homeCtrl.searchForm.lastName.$commitViewValue('" + properties.LastName + "');"
                        + "scope.homeCtrl.searchForm.lastName.$render();"
                        + "scope.homeCtrl.searchForm.$valid = true;"
                        + "scope.homeCtrl.search();"
                        + "});");
                    setTimeout(function () {
                        plugin.itLoginComplete(params);
                    }, 5000);
                });
            }// if (form.length > 0)
            if (counter > 20) {
                clearInterval(getConfNoItinerary);
                provider.setError(util.errorMessages.itineraryFormNotFound);
                return;
            }// if (counter > 20)
            counter++;
        }, 500);
	},

	itLoginComplete: function(params) {
        browserAPI.log('itLoginComplete');
		provider.complete();
	}

};