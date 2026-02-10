var plugin = {

    hosts: {
        'getgo.com.ph': true,
        '/\\w+\\.getgo\\.com.*/': true,
        'book.cebupacificair.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.getgo.com.ph/member/quick-enroll-login';
    },

    start: function (params) {
        browserAPI.log('start');
        provider.setNextStep('startAfter', function(){
            // angularjs
            // provider.eval("angular.reloadWithDebugInfo();");
            provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
            browserAPI.log('location: ' + document.location.href);
            document.location.href = plugin.getStartingUrl(params);
            browserAPI.log('location: ' + document.location.href);
        });
    },

    startAfter: function (params) {
        browserAPI.log('startAfter');
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
        browserAPI.log('isLoggedIn');
        if ($('input[id = "txtUserName"]:visible').length > 0) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('a.logout-link-button').length > 0) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        var number = util.findRegExp($('label.nav-private-id').text(), /\:\s*([^<]+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && (number != '')
            && (number == account.properties.CardNumber));
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('logoutRedirect', function () {
            $('#getgo-profile-logout a').get(0).click();
        });
    },

    logoutRedirect: function (params) {
        browserAPI.log('logoutRedirect');
        provider.setNextStep('logoutBook', function () {
            document.location.href = 'https://book.cebupacificair.com/Member/MyBookings';
        });
    },

    logoutBook: function (params) {
        browserAPI.log('logoutBook');
        var logout = $('a[href="/Member/Logout"]');
        if (logout.length)
            provider.setNextStep('start', function () {
                logout.get(0).click();
            });
        else
            plugin.start();
    },

    login: function (params) {
        browserAPI.log('login');
        if (
            typeof (params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId == 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://book.cebupacificair.com/Manage/Retrieve';
            });
            return;
        }
        var form = $('form#aspnetForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // $('input[id = "txtUserName"]', form).val(params.account.login);
            // $('input[id = "txtPassword"]', form).val(params.account.password);
            // angularjs
            provider.eval(
                "var scope = angular.element(document.querySelector('div.login-panel')).scope();"
                + "scope.$apply(function(){"
                + "scope.credential.Username = '" + params.account.login + "'; "
                + "scope.credential.Secword = '" + params.account.password + "';"
                + "});"
            );
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[type = "button"][data-ng-click="login($event)"]').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form#retrieveBookingByEmailNames');
        if (form.length > 0) {
            form.find('input[name = "cebRetrieveBooking.RecordLocator"]').val(properties.ConfNo);
            form.find('input[name = "cebRetrieveBooking.LastName"]').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function () {
                form.find('button#retrieve-by-email-submit').click();
            });
        } else {
            provider.setError(util.errorMessages.itineraryFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        var errors = $('div.error-container:visible p');
        if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(util.filter(errors.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('loginToItineraries', function() {
                document.location.href = 'https://book.cebupacificair.com/Member/MyBookings';
            });
            return;
        }
        provider.complete();
    },

    itr: 0,
    loginToItineraries: function (params) {
        browserAPI.log("loginToItineraries");
        plugin.itr++;
        if (document.location.href.indexOf('/Member/MyBookings') === -1 && document.location.href.indexOf('/Login') === -1 && plugin.itr < 5) {
            plugin.loginComplete(params);
            return null;
        }

        var form = $('form#getGoMemberLoginForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "cebMemberLogin.Username"]').val(params.account.login);
            form.find('input[name = "cebMemberLogin.Password"]').val(params.account.password);

            provider.setNextStep('toItineraries', function () {
                $('button#get_go_login_submit').click();
                setTimeout(function () {
                    plugin.toItineraries(params);
                }, 5000);
            });
        } else if ($('.bookinglist-table-container:visible').length) {
            plugin.toItineraries(params);
        } else
            provider.setError(util.errorMessages.itineraryNotFound);
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('tr .bookinglist-bookinglocator:contains("'+ confNo +'")').closest('tr').find('button[type="submit"][value="Manage"]');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 1000);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }


};
