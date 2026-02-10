var plugin = {

    hosts: {
        'www.fedex.com': true,
        'getrewards.fedex.com': true
    },

    cashbackLinkMobile: false,
    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params) {
        return 'https://getrewards.fedex.com/#/login';
    },

    start: function(params) {
        browserAPI.log('start');
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
                }
                else
                    plugin.loadLoginForm(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    loadLoginForm: function(params) {
        browserAPI.log('loadLoginForm');
        const login = $('a[onclick *= "logLinkView"]:contains("LOG IN"):visible');

        if (login.length) {
            provider.setNextStep('login', function() {
                login.get(0).click();
            });
            return;
        }

        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    isLoggedIn: function() {
        browserAPI.log('isLoggedIn');

        if ($('a[onclick *= "logLinkView"]:contains("LOG IN"):visible').length > 0) {
            browserAPI.log('logged in = false');
            return false;
        }

        if ($('a.sign-out').length > 0) {
            browserAPI.log('logged in = true');
            return true;
        }

        return null;
    },

    isSameAccount: function(params) {
        browserAPI.log('isSameAccount');
        var name = util.findRegExp(document.cookie, /fcl_contactname=(.+?);/);
        browserAPI.log('name: ' + name);
        return name === plugin.objectVal(params, ['account', 'properties', 'Name']);
    },

    objectVal: function(obj, keys) {
        if (typeof(obj) === undefined)
            return null;
        var res = obj;
        var len = keys.length;
        for (var i = 0; i < len; i++) {
            var key = keys[i];
            if (typeof(res[key]) !== 'undefined' && res[key] !== '') {
                res = res[key];
            } else {
                console.log('Invalid keys:');
                console.log(keys);
                return null;
            }
        }
        if (typeof res === 'string')
            res = res.trim();
        return res;
    },

    logout: function() {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function() {
            $('a.sign-out').get(0).click();
        });
    },

    login: function(params) {
        browserAPI.log('login');
        util.waitFor({
            selector: 'form[name = "logonForm"]',
            success: function (elem) {
                $('input[name = "username"], input[name = "USER"]').val(params.account.login);
                $('input[name = "password"], #pswd-input').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function() {
                    $('input[value = "Login"]').get(0).click();
                });
            },
            fail: function() {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        });
    },

    checkLoginErrors: function() {
        browserAPI.log('checkLoginErrors');
        const errors = $('b.error:visible');

        if (errors.length) {
            provider.setError(util.filter(errors.text()));
            return;
        }

        provider.complete();
    },

    loginComplete: function() {
        browserAPI.log('loginComplete');
        provider.complete();
    }

};
