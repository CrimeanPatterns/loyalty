var plugin = {

    hosts: {'flyasiana.com': true, '/\\w+\\.flyasiana\\.com/': true},

    getStartingUrl: function (params) {
        return 'https://flyasiana.com/I/US/EN/MyasianaDashboard.do?menuId=CM201803060000729176';
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

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('a[onclick *= logout]:visible').length > 0
            || (provider.isMobile &&  $('div.user_membership:visible').length > 0)) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form:has(input[id = txtID]):visible, div#logindiv:has(input[id = txtID])').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('div.my_card > p > a > span').text().trim();
        if (provider.isMobile)
            number = $('div.user_membership').text().trim();
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && number
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a[onclick *= logout], button[onclick *= logout]').get(0).click();
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        setTimeout(function () {
            var form = $('form:has(input[id = txtID]), div#logindiv:has(input[id = txtID])');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");

                if (params.account.login2 == 'Number' || (params.account.login2 == '' && Number.isInteger(params.account.login)))
                    form.find('#loginType_ACNO').click();
                else
                    form.find('#loginType_ID').click();

                form.find('input[id = txtID]').val(params.account.login);
                form.find('input[id = txtPW]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('#btnLogin').get(0).click();
                    setTimeout(function () {
                        provider.complete();
                    }, 5000)
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 1000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        // var errors = $("div.errorInner");
        // if (errors.length > 0)
        //     provider.setError(errors.text());
        // else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = "https://flyasiana.com/I/US/EN/RetrieveReservationList.do";
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        var counter = 0;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('button[pnralpha="' + confNo + '"]');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function () {
                    link.click();
                    plugin.itLoginComplete(params);
                });
            }
            if (counter > 15) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }// if (counter > 15)
            counter++;
        }, 500);
    },

    itLoginComplete: function () {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
}