var plugin = {

    hosts: {
        'www.discoverasr.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.discoverasr.com/content/discoverasr/en/member/user-profile.html#dashboard';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(async function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (await plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
                    plugin.login(params);
            }

            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }

            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");

        if ($('.login-card:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        let link = $(".asr-booking-login a:contains('Sign in')"); // link to form exists on mobile
        if (link.length) link.get(0).click();

        if ($('.profile-dropdown').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: async function (account) {
        browserAPI.log("isSameAccount");
        let number;
        let counter = 0;
        do {
            if (counter === 10) break;
            browserAPI.log("account number is not loaded yet, waiting 1 sec");
            await new Promise(res => setTimeout(res, 1000));
            number = util.findRegExp($('.member-id-text').text(), /(\d+)/);
            counter++;
        }
        while (number === null)
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.Number) != 'undefined')
                && (account.properties.Number !== '')
                && number
                && (number === account.properties.Number));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.querySelector('.profile-menu .dropdown-text').click();
            setTimeout(function () {
                document.getElementsByClassName('signout-link')[0].click();
            }, 5);
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");

        if (
            typeof (params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            provider.setNextStep('getConfNoItinerary');
            document.location.href = 'https://www.discoverasr.com/en/booking/property-listing/search-for-reservation';
            return;
        }

        let form = $('.login-card');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        let login = form.find('#the-login-email-field').val(params.account.login).get(0);
        login.dispatchEvent(new Event('input'));
        login.dispatchEvent(new Event('change'));
        let pwd = form.find('#the-login-password-field').val(params.account.password).get(0);
        pwd.dispatchEvent(new Event('input'));
        pwd.dispatchEvent(new Event('change'));
        provider.setNextStep('checkLoginErrors', function () {
            setTimeout(function () {
                form.find('button.primary').get(0).click();
            }, 2000);
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('.form-alert-error:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        if ($('.login-card:visible').length && errors.length < 1) {
            plugin.login(params);
            return;
        }

        plugin.loginComplete(params);
    },

    toItineraries: function (params) {
        browserAPI.log('toItineraries');
        let counter = 0;
        let toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let link = $('.confirmation-no:contains("' + params.account.properties.confirmationNumber + '")').parents('.reservation-item').find('.modify-reservation button');
            browserAPI.log('link ' + link);

            if (link.length) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function () {
                    link.get(0).click();
                });
                return;
            }

            if (counter > 20) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
                return;
            }

            counter++;
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        let properties = params.account.properties.confFields;
        util.waitFor({
            selector: '.asr-search-reservations .wrapper-content',
            success: function () {
                let wrapper = $('.asr-search-reservations .wrapper-content');
                wrapper.find('input[name=search-reservation-number]').val(properties.ConfNo);
                wrapper.find('input[name=search-reservation-email]').val(properties.Email);
                provider.setNextStep('itLoginComplete', function () {
                    wrapper.find('button.primary').get(0).click();
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            },
            timeout: 10
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");

        if (typeof (params.account.itineraryAutologin) === "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.discoverasr.com/content/discoverasr/en/member/user-profile.html#reservation';
            });
            return;
        }

        provider.complete();
    },
};