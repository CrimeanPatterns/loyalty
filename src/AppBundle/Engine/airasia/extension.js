var plugin = {

    hosts: {'/www\\.airasia\\.com/': true},
    counter: 0,
    getStartingUrl: function (params) {
        return 'https://www.airasia.com/account/personal-information/';
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
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 1000);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        plugin.counter++;
        if ($('div[class^="Account_basicInfo__"]').length > 0 && $('#login p:visible').text().length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#login:contains("Log in/Sign up"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let number = null;
        let modal = $('#login');
        if (modal.length) {
            modal.get(0).click();
            number = $('#bigMemberId p:contains("airasia member ID")').next('p').text();
        }

        browserAPI.log("number: " + number);
        return ((typeof(account.properties) !== 'undefined')
            && (typeof(account.properties.Number) !== 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function (params) {
            let modal = $('#login');
            if (modal.length) {
                if ($('#login-menu-wrapper').length === 0)
                    modal.get(0).click();
                 let logout = $('a#logout');
                if (logout.length) {
                    logout.get(0).click();
                }
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var login = $('#login:visible');
        if (login.length)
            login.click();

        setTimeout(function () {
            let form = $('#aaw-login-tab form:visible');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('#text-input--login').val(params.account.login);
                util.sendEvent(form.find('#text-input--login').get(0), 'input');
                form.find('#text-input--login').focus();
                form.find('#password-input--login').focus();
                setTimeout(function () {
                    form.find('label[for="password-input--login"').click()
                    form.find('#password-input--login').val(params.account.password);
                    util.sendEvent(form.find('#password-input--login').get(0), 'input');
                    util.sendEvent(form.find('#password-input--login').get(0), 'blur');
                    util.sendEvent(form.find('#password-input--login').get(0), 'change');

                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('#loginbutton:visible').click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 7000);
                    });
                }, 500)
            } else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 1000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('.sso-error-message:visible > div');
        if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.airasia.com/member/myorders/en/gb';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        util.waitFor({
            selector: 'td#bookingNumber:contains("' + confNo + '")',
            success: function() {
                provider.itLoginComplete(params)
               /* var link = $('td.booking-info-meta.ng-star-inserted:contains("' + confNo + '"), p.booking-info-meta:contains("' + confNo + '")');
                if (link.length > 0) {
                    provider.setNextStep('itLoginComplete', function(){
                        link.get(0).click();
                    });
                }// if (link.length > 0)
                else
                    provider.setError(util.errorMessages.itineraryNotFound);*/
            },
            fail: function() {
                provider.setError(util.errorMessages.itineraryNotFound);
            },
            timeout: 10
        });

    },
};
