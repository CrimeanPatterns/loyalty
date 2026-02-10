var plugin = {

    hosts: {
        'www.etihadguest.com': true,
        'www.virtuallythere.com': true,
        'www.etihad.com': true
    },

    cashbackLinkMobile : false,//todo
    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.etihadguest.com/en/your-account/transaction-details/';
    },

    getFocusTab: function(account, params){
        return true;
    },

    loadLoginForm: function (params) {
        browserAPI.log("logout");
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
                if ($('span:contains("Session expired")').length > 0) {
                    plugin.logout(params);
                    return;
                }
                var error = $('h2:contains("Scheduled maintenance")');
                if (error.length > 0) {
                    provider.setError([error.text(), util.errorCodes.providerError]);
                    return;
                }
                if (document.location.href === 'https://www.etihadguest.com/en/my-account/activity-history.html') {
                    var retry = $.cookie("etihadguest.com_aw_retry_" + params.account.login);
                    var notRetry = retry === null || retry === undefined;
                    if (notRetry || retry < 2) {
                        if (notRetry)
                            retry = 0;
                        retry++;
                        browserAPI.log("login retry: " + retry);
                        $.cookie("etihadguest.com_aw_retry_" + params.account.login, retry, { expires: 0.01, path:'/', domain: '.etihadguest.com', secure: true });
                        plugin.loadLoginForm(params);
                        return;
                    }
                }
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href *= "logout=true"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if (
            $('form[id = "eyg-login-form"]:visible').find('input[name = "emailOrGuestNumber"]').length > 0
            || $('a.join-link:visible').length > 0
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp($('h3:contains("Etihad Guest number:")').parent('div').text(), /:\s*([^<]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.etihadguest.com/en/your-account/transaction-details/?logout=true';
        });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('#frmManageMyBooking:visible');
        if (form.length) {
            form.find('input[name = "mybBookingReference"]').val(properties.ConfNo);
            form.find('input[name = "mybLastName"]').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function() {
                form.find('button#mybFormSubmit').click();
            });
            return;
        }
        provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    },

    login: function (params) {
        browserAPI.log("login");
        if (
            typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId == 0
        ) {
            provider.setNextStep('getConfNoItinerary', function () {
                browserAPI.log('location: ' + document.location.href);
                document.location.href = 'https://www.etihad.com/en-us/manage';
            });
            return;
        }

        var loginLink = $('a.join-link:visible');
        if (loginLink.length > 0) {
            loginLink.click();
        }

        var form = $('form[id = "eyg-login-form"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            util.setInputValue(form.find('input[name = "emailOrGuestNumber"]'), params.account.login);
            util.setInputValue(form.find('input[name = "loginPass"]'), params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function() {
                    // angularjs
                    provider.eval("var scope = angular.element(document.querySelector('form[id = \"eyg-login-form\"]')).scope();"
                        + "scope.$apply(function(){"
                        + "scope.loginPartial.dataModel.emailOrGuestNumber = '" + params.account.login + "';"
                        + "scope.loginPartial.dataModel.password = '" + params.account.password + "';"
                        + "});");
                    $('button[id = "submitLogin"]').trigger('click');
                    waiting();
                }, 3000);

                function waiting() {
                    browserAPI.log("waiting...");
                    var counter = 0;
                    var login = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        var error = $('div#errorMessageContainer p:visible');
                        if (error.length == 0)
                            error = $('span.error:visible');
                        if (error.length > 0 && util.filter(error.text()) != '') {
                            clearInterval(login);
                            provider.setError(util.filter(error.text()), true);
                        }// if (error.length > 0 && error.text().trim() != '')
                        // refs #14909
                        var success = $('a[onclick *= "logOut"]:visible');
                        if (success.length === 1 && util.filter(success.text()) !== '') {
                            clearInterval(login);
                            plugin.checkLoginErrors(params);
                        }// if ($('p:contains("Account#"):visible').length > 0)
                        if (counter > 100 || $('p:contains("One time password is sent to your email id. Please verify."):visible').length > 0) {
                            clearInterval(login);
                            provider.complete();
                        }
                        counter++;
                    }, 500);
                }
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div#errorMessageContainer p:visible');
        if (errors.length == 0)
            errors = $('span.error:visible');
        if (errors.length > 0 && util.trim(errors.text()) != '') {
            provider.setError(errors.text(), true);
        }// if (errors.length > 0)
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};