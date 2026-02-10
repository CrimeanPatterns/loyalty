var plugin = {

    hosts: {'www.caymanairways.com': true, 'caymanairways.com': true, 'flights.caymanairways.com': true},

    getStartingUrl: function (params) {
        return 'https://www.caymanairways.com/login';
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

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = $('span:contains("Account Number:") + span.detail').text();
        browserAPI.log("number: " + number);
        return ((typeof (account.properties) != 'undefined')
            && (typeof (account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber != '')
            && number
            && (number == account.properties.AccountNumber));

    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[name = "loginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.caymanairways.com/logout';
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
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://flights.caymanairways.com/dx/KXDX/#/home?tabIndex=1';
            });
            return;
        }
        var form = $('form[name = "loginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[id = "memberNo"]').val(params.account.login);
            form.find('input[id = "memberPassword"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button[id = "loginUser"]').click();
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $("div.alert-danger:visible");
        if (errors.length > 0) {
            provider.setError(errors.text());
        }
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        setTimeout(function() {
            var form = $('form.retrieve-pnr:visible');
            if (form.length > 0) {
                form.find('input[id = "confirmationPnrInput-confirmation-pnr"]').val(properties.ConfNo);
                form.find('input[id = "lastnameInput-lastname"]').val(properties.LastName);
                form.find('input[id = "firstnameInput-firstname"]').val(properties.FirstName);

                provider.setNextStep('loginComplete', function() {
                    form.find('button#search').click();
                    setTimeout(function () {
                        plugin.loginComplete(params);
                    }, 5000);
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }, 3000);
    },
};