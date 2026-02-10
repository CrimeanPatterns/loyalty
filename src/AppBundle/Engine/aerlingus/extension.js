var plugin = {

    hosts: {
        'www.aerlingus.com': true,
        'new.aerlingus.com': true,
        'accounts.aerlingus.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.aerlingus.com/api/loyalty/v1/login?redirect=%2Fhtml%2Fuser-profile.html';
    },

    start: function (params) {
        browserAPI.log("start");
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
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }
            if (counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
            }
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form:has(input[id = "test_membership_login_page-1"]):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('span:contains("Membership Number") + span:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = util.filter($('span:contains("Membership Number") + span').text());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && number
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[data-test-id="personalDropdownButton"]').get(0).click();
            setTimeout(function () {
                $('a[data-test-id="myAccountPersonalLogoutButton"]').get(0).click();
            }, 1000);
        });
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.aerlingus.com/html/trip-mgmt.html#?select=1";
            });
            return;
        }
        let form = $('form:has(input[id = "test_membership_login_page-1"])');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }
        browserAPI.log("submitting saved credentials");
        // angularjs
        provider.eval(
            "function triggerInput(enteredName, enteredValue) {\n" +
            "      const input = document.getElementById(enteredName);\n" +
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
            "triggerInput('test_membership_login_page-1', '" + params.account.login + "');\n" +
            "triggerInput('test_password_login_page-1', '" + params.account.password + "');"
        );
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button:contains("Log in")').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 10000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('p.uil-errorRed:visible:eq(0), .uil-message-error p:visible:eq(0)');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }
        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            var counter = 0;
            var loginComplete = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var link = $('p:contains("My Trips"):visible');
                if (link.length > 0) {
                    clearInterval(loginComplete);
                    provider.setNextStep('toItineraries', function () {
                        link.get(0).click();
                        setTimeout(function () {
                            plugin.toItineraries(params);
                        }, 5000);
                    });
                }
                if (counter > 20) {
                    clearInterval(loginComplete);
                    provider.setError(util.errorMessages.itineraryNotFound);
                }
                counter++;
            }, 500);
            //document.location.href = 'https://www.aerlingus.com/html/user-profile.html#?tabType=my-trips';
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        // this not working
        var confNo = params.account.properties.confirmationNumber;
        var link = $('span:contains("Booking Reference") + span:contains("' + confNo + '")').closest('div.user-trip').find('button:contains("Manage Booking")');
        if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function () {
                link.click();
            });
        }
        else
            provider.setError(util.errorMessages.itineraryNotFound);
    },

    getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
        var properties = params.account.properties.confFields;

        var counter = 0;
        var getConfNoItinerary = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var form = $('div[name = tripForm]');
            if (form.length > 0) {

                clearInterval(getConfNoItinerary);

                // angularjs
                provider.eval(
                    "var scope = angular.element($('div[name = tripForm]')).scope();" +
                    "scope.$apply(function(){" +
                    "scope.tripData.pnr = '" + properties.ConfNo + "';" +
                    "scope.tripData.surName = '" + properties.LastName + "';});"
                );
                provider.setNextStep('itLoginComplete', function () {
                    form.find('button:contains("Manage Trip")').get(0).click();
                });
            }
            if (counter > 20) {
                clearInterval(getConfNoItinerary);
                provider.setError(util.errorMessages.itineraryFormNotFound);
            }
            counter++;
        }, 500);
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }
};
