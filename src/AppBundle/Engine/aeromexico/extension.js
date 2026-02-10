var plugin = {

    hosts: {
        'member.clubpremier.com': true,
        'clubpremier.com': true,
        'www.aeromexico.com': true,
        'aeromexico.com': true
    },

    getStartingUrl: function (params) {
        return 'https://member.clubpremier.com/login/auth';
    },

    loadLoginForm: function(){
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl();
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
                        provider.complete();
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
        if ($('#loginForm').length > 0 || $('form[name = "login"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=salir]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = util.findRegExp($('div.account').text(), /\s+(\d+)/);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number !== '')
            && (number === account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://member.clubpremier.com/salir';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof (params.account.itineraryAutologin) == "boolean" &&
            params.account.itineraryAutologin &&
            params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://www.aeromexico.com/en-us/manage-your-booking";
            });
            return;
        }
        var form = $('#loginForm');
        if (form.length == 0)
            form = $('form[name = "login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "j_username"]').val(params.account.login);
            form.find('input[name = "j_password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.submit();
            });
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function () {
        var errors = $("#messageException");
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    getConfNoItinerary: function(params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form.PNRLookupForm');
        if (form.length > 0) {
            form.find('input#ticketNumber').val(properties.ConfNo);
            form.find('input#lastName').val(properties.LastName);
            util.sendEvent(form.find('input#ticketNumber').get(0), 'input');
            util.sendEvent(form.find('input#lastName').get(0), 'input');
            provider.setNextStep('itLoginComplete', function () {
                form.find('button[type="submit"]').get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};