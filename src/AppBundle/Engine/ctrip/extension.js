var plugin = {

//    keepTabOpen: true,
    clearCache: true,
    hosts: {
        'trip.com': true,
        '/\\w+\\.trip\\.com/': true,
    },

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.trip.com/account/signin';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log('start');
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
                        plugin.logout();
                }
                else
                    setTimeout(function () {
                        plugin.login(params);
                    }, 1000)
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
        if ($('#ibu_login_submit:visible').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('a:contains("Sign Out")').length > 0) {
            browserAPI.log('isLoggedIn: true');
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        // if ('undefined' != typeof account.properties && 'undefined' != typeof account.properties.Name) {
        //     var name = $('label.keyName:contains("Name")').next();
        //     if (name && (name == account.properties.Name || name == account.properties.Name.split(' ').reverse().join(' ')))
        //         return true;
        // }

        return false;
    },

    logout: function () {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Sign Out")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log('login');
        var form = $('form input');
        if (form.length) {
            let email = $('input[placeholder="Please enter an email address"]');
            email.val(params.account.login);
            util.sendEvent(email.get(0), 'input');
            $('#ibu_login_submit').click();
            util.waitFor({
                timeout: 5,
                selector: 'input[placeholder="Please enter your password"]:visible',
                success: function(elem) {
                    let password = $('input[placeholder="Please enter your password"]');
                    password.val(params.account.password);
                    util.sendEvent(password.get(0), 'input');
                    provider.setNextStep('checkLoginErrors', function () {
                        $('#ibu_login_submit').click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 7000);
                    });
                },
                fail: function() {
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    // @Deprecated
    mobileRedirect: function () {
        browserAPI.log('mobileRedirect');
        if (provider.isMobile) {
            setTimeout(function () {
                document.location.href = 'https://accounts.ctrip.com/global/english/MemberCenter/ProfileInfo?curr=USD&language=EN&locale=en_us';
            }, 7000);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        alert(1);
        var error = $('.msg-error:visible');
        if (error.length && '' != util.trim(error.text()))
            provider.setError(error.text());
        else {
            //plugin.mobileRedirect();
            plugin.loginComplete(params);
        }
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.trip.com/order/all';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        plugin.loginComplete(params);
        return;
        var counter = 0;
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var link = $('a[href *= "/ViewOrder/' + params.account.properties.confirmationNumber + '"]:eq(0)');
            if (link.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('itLoginComplete', function(){
                    link.get(0).click();
                });
            }
            if (counter > 20) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    itLoginComplete: function() {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
