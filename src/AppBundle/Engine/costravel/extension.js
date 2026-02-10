var plugin = {
    //keepTabOpen: true,
    hosts: {'www.costcotravel.com': true},

    getStartingUrl: function (params) {
        return 'https://www.costcotravel.com/h=5001';
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
        if ($('form#costco_member_login:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#loginContentDiv').parent('.displayInline').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.trim($('p[data-test="c"]').text());
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && (number == account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            var linkMemberLoginOption = $('a[data-test="linkMemberLoginOption"]');
            if (linkMemberLoginOption.length > 0) {
                linkMemberLoginOption.get(0).click();
                var linkLogout = $('a#linkLogout');
                if (linkLogout.length > 0) {
                    linkLogout.get(0).click();
                }
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#costco_member_login');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "member_login_number"]').val(params.account.login);
            form.find('input[name = "member_login_password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('#loginButton_1').get(0).click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('#member_login_number-error:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.costcotravel.com/h=5002';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('a.member-booking-link:contains("'+ confNo +'")');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

}