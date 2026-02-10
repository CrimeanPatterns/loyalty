var plugin = {

    //keepTabOpen: false,
    hosts: {'www.allegiantair.com': true},

    getStartingUrl: function (params) {
        return 'https://www.allegiantair.com/my-profile';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
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
                } else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.logBody("loginPage");
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('#login-email:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('div[class*="NavItem__NavContent-"]:contains("Hello ")').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp($('span[data-hook="dashboard-summary-my-allegiant-id"]').text(), /#\s*([^<]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.allegiantair.com/manage-travel';
            });
            return;
        }

        var form = $('div[class*="Login__LoginWrapper"] form');
        if (form.length > 0) {
            form.find('input#login-email').val(params.account.login + '');
            form.find('input#login-password').val(params.account.password);
            util.sendEvent(form.find('input#login-email').get(0), 'input');
            util.sendEvent(form.find('input#login-password').get(0), 'input');
            provider.setNextStep('loginCheckErrors', function () {
                form.find('button[data-hook="home-login_submit-button_continue"]').get(0).click();
                setTimeout(function () {
                    plugin.loginCheckErrors(params)
                }, 4000);
            });
        } else {
            provider.setError(util.errorMessages.unknownLoginState);
        }
    },

    loginCheckErrors: function (params) {
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var error = $('.wrapper-error-message');
            if (error.length > 0 && util.trim(error.text()) !== '') {
                clearInterval(start);
                provider.setError(util.trim(error.text()));
            } else if (counter > 7) {
                plugin.loginComplete(params);
                clearInterval(start);
            }
            counter++;
        }, 200);
    },

    loginComplete: function (params) {
        browserAPI.log('loginComplete');
        if (typeof (params.account.itineraryAutologin) === 'boolean' && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.allegiantair.com/my-profile/my-trips';
            });
            return;
        }
        provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'input#firstName:visible',
            success: function () {
                var inputFirstName = $('input#firstName');
                var inputLastName = $('input#lastName');
                var inputConfNo = $('input#orderNumber');

                inputFirstName.val(properties.FirstName);
                inputLastName.val(properties.LastName);
                inputConfNo.val(properties.ConfNo);
                util.sendEvent(inputFirstName.get(0), 'change');
                util.sendEvent(inputLastName.get(0), 'change');
                util.sendEvent(inputConfNo.get(0), 'change');

                var button = $('button[data-hook="lookup-page-lookup-button"]');
                button.removeAttr('disabled');
                button.get(0).click();
                setTimeout(function () {
                    plugin.loginComplete(params);
                }, 2000);
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            },
            timeout: 10
        });
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        var confNo = params.account.properties.confirmationNumber;
        util.waitFor({
            selector: 'div[data-hook="my-trips-section"]:visible',
            success: function (item) {
                provider.setNextStep('complete', function () {
                    let link = $('a[href *= "orderNumber=' + confNo + '"]');
                    if (link.length)
                        link.removeAttr('target').get(0).click();
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryNotFound);
            },
            timeout: 7
        });
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.allegiantair.com/user/logout';
        });
    },

    complete: function (params) {
        provider.complete();
    }
};
