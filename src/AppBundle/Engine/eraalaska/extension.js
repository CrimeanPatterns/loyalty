var plugin = {
    // всегда оставлять вкладку открытой, только для дебага!
    //keepTabOpen: true,
    hosts: {
        'www.flyravn.com': true,
        'booking.flyravn.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.flyravn.com/rewards/manage-your-account/';
    },

    getStartingUrlBooking: function (params) {
        return 'https://booking.flyravn.com/SSW2010/8M77/myb.html';
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

    startBooking: function (params) {
        browserAPI.log("startBooking");
        var counter = 0;
        var start = setInterval(function() {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedInBooking();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccountBooking(params.account))
                        plugin.loginCompleteBooking(params);
                    else
                        plugin.logoutBooking(params);
                }
                else
                    plugin.loginBooking(params);
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
        if ($('form[action *= "/manage-your-account/"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (/Member #: (\d+)/i.test($('body').text())) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isLoggedInBooking: function () {
        browserAPI.log("isLoggedInBooking");
        if ($('form#form_login_2').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "account-log-out"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('body').text(), /Member #: (\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.MemberAccount) != 'undefined')
            && (account.properties.MemberAccount != '')
            && (number == account.properties.MemberAccount));
    },

    isSameAccountBooking: function (account) {
        browserAPI.log("isSameAccountBooking");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('input#account-username').attr('value');
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.MemberAccount) != 'undefined')
            && (account.properties.MemberAccount != '')
            && (number == account.properties.MemberAccount));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('startRedirect', function () {
            document.location.href = 'https://www.flyravn.com/rewards/account-login/account-log-out/';
        });
    },

    logoutBooking: function () {
        browserAPI.log("logoutBooking");
        provider.setNextStep('startRedirectBooking', function () {
            var link = $('a[href *= "account-log-out"]');
            if (link.length > 0) {
                link.get(0).click();
            }
        });
    },

    startRedirect: function (params) {
        browserAPI.log('startRedirect');
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    startRedirectBooking: function (params) {
        browserAPI.log('startRedirectBooking');
        provider.setNextStep('startBooking', function() {
            document.location.href = plugin.getStartingUrlBooking(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId == 0   ) {
            provider.setNextStep('getConfNoItineraryBooking', function() {
                document.location.href = 'https://booking.flyravn.com/SSW2010/8M77/myb.html';
            });
            return;
        }

        var form = $('form[action *= "/manage-your-account/"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "UserName"]').val(params.account.login);
            form.find('input[name = "Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input#btnSubmit').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    getConfNoItineraryBooking: function (params) {
        browserAPI.log("getConfNoItineraryBooking");
        var properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'form#form_bookingretrieval_1',
            success: function(form) {
                form.find('input[name = "reservationCode"]').val(properties.ConfNo);
                form.find('input[name = "lastName"]').val(properties.LastName);
                provider.setNextStep('itLoginCompleteBooking', function() {
                    form.find('input[type = "submit"]').click();
                });
            },
            fail: function() {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            }
        });
    },

    loginBooking: function (params) {
        browserAPI.log("loginBooking");
        var form = $('form#form_login_2');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrorsBooking', function () {
                form.find('input[type = submit]').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('p.error');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    checkLoginErrorsBooking: function (params) {
        browserAPI.log("checkLoginErrorsBooking");
        var errors = $('p.wrongPassword');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginCompleteBooking(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId > 0    ) {
            provider.setNextStep('startBooking', function() {
                document.location.href = 'https://booking.flyravn.com/SSW2010/8M77/myb.html';
            });
            return;
        }
        provider.complete();
    },

    loginCompleteBooking: function(params) {
        browserAPI.log("loginCompleteBooking");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId > 0    ) {
            provider.setNextStep('toItinerariesBooking', function() {
                document.location.href = 'https://booking.flyravn.com/SSW2010/8M77/myb.html';
            });
            return;
        }
        provider.complete();
    },

    toItinerariesBooking: function(params) {
        browserAPI.log("toItinerariesBooking");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('a[href *= "' + confNo + '"');
            if (link.length > 0) {
                provider.setNextStep('itLoginCompleteBooking', function() {
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    itLoginCompleteBooking: function(params) {
        browserAPI.log("itLoginCompleteBooking");
        provider.complete();
    }

};
