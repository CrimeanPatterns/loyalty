var plugin = {

    hosts: {
        '/\\w+\\.flynas\\.com': true
    },

    getStartingUrl: function (params) {
        if (params.account.login2 === 'Corporate') {
            return 'https://booking.flynas.com/#/agent/login-corp';
        }

        if (params.account.login2 === 'Agencies') {
            return 'https://booking.flynas.com/#/agent/login';
        }

        return 'https://booking.flynas.com/#/member/login';
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('start', function () {
            $('.modal-dialog button:contains("OK")').get(0).click();
            document.location.href = plugin.getStartingUrl(params);
        });
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
                        provider.complete();
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

    isLoggedIn: function () {
        browserAPI.log('isLoggedIn');
        if ($('.header_menu a:contains("Log out"):visible').length) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }

        if ($('form[name="loginform"]').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return ((typeof (account.properties) != 'undefined')
                && (typeof (account.properties.AccountId) != 'undefined')
                && (account.properties.AccountId !== '')
                && ($('.align-right > b:contains("' + account.properties.AccountId + '")').length));
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            $('.header_menu a:contains("Log out")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log('login');
        const form = $('form[name="loginform"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        form.find('input[name="iptid"]').val(params.account.login);
        form.find('input[name="iptpasswprd"]').val(params.account.password);
        util.sendEvent(form.find('input[name="iptid"]').get(0), 'input');
        util.sendEvent(form.find('input[name="iptpasswprd"]').get(0), 'input');
        provider.setNextStep('checkLoginErrors', function () {
            form.find('button[type="submit"]').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors();
            }, 5000);
        });
    },

    checkLoginErrors: function () {
        browserAPI.log('checkLoginErrors');
        const errors = $('.modal-body > div[ng-bind-html="vm.message"]:visible');
        if (errors.length > 0 && util.trim(errors.text()) !== '') {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }

};
