
var plugin = {

    hosts: {
        'www.amtrak.com': true,
        'tickets.amtrak.com': true,
        'login.amtrak.com':true
    },

    getStartingUrl: function (params) {
        return 'https://www.amtrak.com/guestrewards/account-overview';
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form#localAccountForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (
            $('p.my-account-summary__member__overview--id').length > 0
            && $('a:contains("Sign Out")').length > 0
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }
        // bag fix
        if (
            $("span.message__text:contains('System Error has occurred, please refresh the page and attempt again.')").length > 0
        ) {
            browserAPI.log("bag fix");
            location.reload();
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp($('p.my-account-summary__member__overview--id').text(), /Member\s*#\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && number
            && (number == account.properties.Number));
    },


    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function() {
            $('a:contains("Sign Out")').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (
            typeof (params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId == 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.amtrak.com/home.html';
            });
            return;
        }
        var form = $('form#localAccountForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#signInName').val(params.account.login);
            form.find('input#password').val(params.account.password);
            form.find('button#next').removeAttr("disabled");
            provider.setNextStep('checkLoginErrors', function() {
                form.find('button#next').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        } else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var link = $('a#ff_tabbar_mytrip.is-subnav-link');
        if (link.length > 0) {
            link.get(0).click();
            util.waitFor({
                selector: 'input#res-number',
                success: function (confInput) {
                    confInput.val(properties.ConfNo);
                    util.sendEvent(confInput.get(0), 'input');
                    util.sendEvent(confInput.get(0), 'blur');
                    $('#am-simple-dropdown__1').click();
                    util.waitFor({
                        selector: 'div[aria-label="Email Address"]',
                        success: function (elem) {
                            elem.click();
                            util.waitFor({
                                selector: 'input[aria-label="Email Address"]',
                                success: function (emailInput) {
                                    emailInput.val(properties.Email);
                                    util.sendEvent(emailInput.get(0), 'input');
                                    util.sendEvent(emailInput.get(0), 'blur');

                                    $('button[aria-label = "Find Trip"]').click();
                                    setTimeout(function () {
                                        plugin.itLoginComplete(params);
                                    }, 1000);
                                },
                                fail: function () {
                                    provider.setError(util.errorMessages.itineraryFormNotFound);
                                }
                            });
                        },
                        fail: function () {
                            provider.setError(util.errorMessages.itineraryFormNotFound);
                        }
                    });
                },
                fail: function () {
                    provider.setError(util.errorMessages.itineraryFormNotFound);
                }
            });
        } else {
            provider.setError(util.errorMessages.itineraryFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('#localAccountForm').find('div.error > p');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.setNextStep('loginComplete', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (
            typeof (params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId > 0
        ) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.amtrak.com/guestrewards/account-overview/my-trips.html';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        setTimeout(function () {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('div:contains("# ' + confNo + '")').closest('.trip-details').next('.view-share').find('button');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                    setTimeout(function () {
                        plugin.itLoginComplete(params);
                    }, 3000);
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
