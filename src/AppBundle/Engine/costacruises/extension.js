var plugin = {

    hosts: {
        'mycosta.costacruises.eu': true,
        'www.costacruises.com': true,
        'www.mycosta.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.costacruises.com/costa-club/profile.html';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);

            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                } else
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");

        if ($('#login-form').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('.nh__global_header_icon_logged').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return false;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('.nh__global_header_icon_logged').get(0).click();
            $('.nh__global_header_login_logout').get(0).click();
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
            document.location.href = 'https://mycosta.costacruises.eu/login-page.html';
            return;
        }

        let form = $('#login-form');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");

        let login = document.getElementById('-login-email');
        login.dispatchEvent(new Event('focus'));
        login.value = params.account.login;
        login.dispatchEvent(new Event('change'));
        login.dispatchEvent(new Event('blur'));

        let pwd = document.getElementById('-login-password');
        pwd.dispatchEvent(new Event('focus'));
        pwd.value = params.account.password;
        pwd.dispatchEvent(new Event('change'));
        pwd.dispatchEvent(new Event('blur'));

        provider.setNextStep('checkLoginErrors', function () {
            form.find('input[type=submit]').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 5000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");

        let errors = $('li.error');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        errors = $('.error-wrap p[role=alert]');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        let properties = params.account.properties.confFields;
        util.waitFor({
            selector: '.wrapper-login .form form:visible',
            success: function (form) {

                form.find('input[name="firstName"]').val(properties.FirstName ?? '');
                form.find('input[name="lastName"]').val(properties.LastName);
                util.sendEvent(form.find('input[name="lastName"]').get(0), 'input');
                /*let lastName = document.querySelector('input[name="lastName"]');
                lastName.dispatchEvent(new Event('focus'));
                lastName.value = properties.LastName ?? '';
                lastName.dispatchEvent(new Event('change'));
                lastName.dispatchEvent(new Event('blur'));*/

                form.find('input[name="bookingNumber"]').val(properties.ConfNo);
                util.sendEvent(form.find('input[name="bookingNumber"]').get(0), 'input');

                /*let bookingNumber = document.querySelector('input[name="bookingNumber"]');
                bookingNumber.dispatchEvent(new Event('focus'));
                bookingNumber.value = properties.ConfNo ?? '';
                bookingNumber.dispatchEvent(new Event('change'));
                bookingNumber.dispatchEvent(new Event('blur'));*/
                provider.setNextStep('itLoginComplete', function () {
                    form.find('button.btn:contains("Login")').get(0).click();
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
    }
};