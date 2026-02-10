var plugin = {
    //keepTabOpen: true,
    hosts: {
        'www.att.com': true,
        '/\\w+\\.att\.com/': true
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        // it seems it auto logs out here
        return 'https://www.att.com/olam/loginAction.olamexecute?fromdlom=true&mobile=false';
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
        if ($('#mobileSavedUserIdList:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#login').find('button[id*=loginButton]:visible').length > 0 && $('#intialSpinner:visible').length == 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        //document.location.href = 'https://www.att.com/olam/passthroughAction.myworld?actionType=ProfileLanding';
        var number = $('div > span:contains("Account Number")').parent().next().text();
        if (!number || number.length > 0) {
            return typeof(account.login) !== 'undefined' &&
                account.login != '' && $('#mobileSavedUserIdList:visible a[value="' + account.login + '"]').length;
        }

        number = util.findRegExp(number, /(\d+)/i);
        browserAPI.log("number: " + number);
        return (
            (typeof(account.properties) != 'undefined') &&
            (typeof(account.properties.AccountNumber) != 'undefined') &&
            (account.properties.AccountNumber != '') &&
            (number == account.properties.AccountNumber)
        );
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            var different = $('a:contains("Enter a different ID")')
            if (different.length) {
                different.get(0).click();
                plugin.login(params);
            }
            else
                document.location.href = 'https://www.att.com/olam/logout.olamexecute';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function () {
            var form = $('form#login');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input#userName').val(params.account.login);
                // exactly, both ids happen
                form.find('input#password').val(params.account.password);
                form.find('input#password1').val(params.account.password);
                // if (!!navigator.userAgent.match(/Trident\/\d\./))
                //     provider.eval('jQuery.noConflict();');
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button[id*=loginButton]:visible').click();
                    setTimeout(function () {
                        plugin.checkLoginErrors(params);
                    }, 7000);
                });
            } else {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        }, 1000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.alert-error:visible + div');
        if (errors.length > 0)
            provider.setError(errors.text());
        else {
            setTimeout(function () {
                var promo = $('[dtmeventcode = "A_LGN_PROMO_SHOW_SUB"]');
                browserAPI.log('promo.length:');
                browserAPI.log(promo.length);
                if (promo.length > 0) {
                    provider.setNextStep('loginComplete', function () {
                        promo.get(0).click();
                    });
                } else
                    plugin.loginComplete(params);
            }, 2000);
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        var list = $('#mobileSavedUserIdList:visible');
        if (list.length) {
            browserAPI.log("Saved User Id List");
            var selectedAccount = list.find('a[value="' + params.account.login + '"]');
            if (selectedAccount.length) {
                selectedAccount.get(0).click();
                plugin.login(params);
                return;
            }
        }
        provider.complete();
    }

};
