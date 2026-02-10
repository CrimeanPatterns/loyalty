var plugin = {

    hosts: {
        'my.spendgo.com': true
    },

    cashbackLink : '',

    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://my.spendgo.com/index.html#/storefront/coldstone';
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

        if ($('input#phoneOrEmail:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        if ($('a#signout-btn').length) {
            browserAPI.log("LoggedIn");
            return true;
        }

        return null;
    },

    isSameAccount: async function (account) {
        browserAPI.log("isSameAccount");
        if ((typeof (account.properties) != 'object')
            || (typeof (account.properties.login) != 'string')
            || account.properties.login.length === 0) return false;
        let response = await fetch('https://my.spendgo.com/consumer/gen/coldstone/v1/consumerdetails', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json;charset=utf-8',
                'Accept': 'application/json, text/plain, */*'
            },
            body: '{}'
        });
        if (response.ok) {
            let info = response.json();
            let email = info.email;
            let phone = info.phone;
            if ((typeof (email) != 'string')
                || (email.length === 0)
                || (typeof (phone) != 'string')
                || (phone.length === 0)) return false;
            email = email.toLowerCase();
            let login = account.properties.login.toLowerCase();
            if (login === email || login === phone) return true;
        }
        return false;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://my.spendgo.com/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");

        let form = $('div.Inputfield_box_model');
        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        let login = document.getElementById('phoneOrEmail');
        login.value = params.account.login;
        login.dispatchEvent(new Event('input'));
        login.dispatchEvent(new Event('change'));
        login.dispatchEvent(new Event('blur'));

        let pwd = document.querySelector('input[type=password]');
        pwd.value = params.account.password;
        pwd.dispatchEvent(new Event('input'));
        pwd.dispatchEvent(new Event('change'));
        pwd.dispatchEvent(new Event('blur'));

        provider.setNextStep('checkLoginErrors', function () {
            form.find('button').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params)
            }, 5000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('div.error:visible');

        if (errors.length > 0) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },
};