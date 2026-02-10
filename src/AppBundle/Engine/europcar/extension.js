var plugin = {
    
    hosts: {'www.europcar.com': true},

    getStartingUrl: function (params) {
        return "https://www.europcar.com/EBE/module/driver/DriverSummary.do";
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout();
        }// if (plugin.isLoggedIn())
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a.logout.tag_track').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('.NFE_newDriver').length > 0 || $('form[name="driverPasswordForm1000"]').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        const number = /\d+/.exec($('div label[for="europcarId"]').parent().text());
        browserAPI.log("number: " + JSON.stringify(number));
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.AccountNumber) != 'undefined')
            && (account.properties.AccountNumber !== '')
            && (number === account.properties.AccountNumber));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.europcar.com/EBE/module/driver/AuthenticateDrivers1000.do?action=6';
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

        if (
            typeof(params.account.itineraryAutologin) == "boolean"
            && params.account.itineraryAutologin
            && params.account.accountId === 0
        ) {
            plugin.getConfNoItinerary(params);
            return;
        }

        const form = $('form[name="driverPasswordForm1000"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name = "driverID"]').val(params.account.login);
        form.find('input[name = "password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.submit();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 10000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        const errors = $('div.error:visible');

        if (errors.length > 0 && util.filter(errors.text()) !== "") {
            provider.setError(util.filter(errors.text()));
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('itLoginComplete', function() {
                document.location.href = 'https://www.europcar.com/EBE/module/driver/DriverExistingBookings.do';
            });
            return;
        }
        provider.complete();
    },

    validateEmail: function (email) {
        var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(email);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form[name = "driverExistingBookingsForm"]');
        if (form.length) {
            if (!plugin.validateEmail(properties.LastName)) {
                form.find('input[name = "reservationNumber"]').val(properties.ConfNo);
                form.find('input[name = "lastName"]').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function() {
                    setTimeout(function () {
                        form.find('input#search1').get(0).click();
                    }, 1000);
                });
            } else {
                $('#searchByEmailBtn').click();
                form.find('input[name = "reservationNumber2"]').val(properties.ConfNo);
                form.find('input[name = "email"]').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function() {
                    setTimeout(function () {
                        form.find('input#search2').get(0).click();
                    }, 1000);
                });
            }
            return;
        }
        provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }
};
