var plugin = {

    hosts: {
        'www.brusselsairlines.com': true,
        'loop.brusselsairlines.com': true,
        'tdp.brusselsairlines.com': true
    },

    cashbackLink      : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'http://www.brusselsairlines.com/com/default.aspx';
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout(params);
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Login")').length > 0) {
            browserAPI.log("not LoggedIn");
            provider.setNextStep('login');
            document.location.href = 'https://loop.brusselsairlines.com/en/web/loop/login-loop?P=loop';
            return false;
        }
        if ($('a#sso-logout-link:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('span#welcome_msg').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name == account.properties.Name));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            $('a#sso-logout-link:visible').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://tdp.brusselsairlines.com/BEL/ReservationSearch.do";
            });
            return;
        }
        // open login form
        //$('a:contains("Login")').get(0).click();
        // wait login form
        var counter = 0;
        var login = setInterval(function () {
            //var form = $('iframe#ffpLoginLoop').contents().find('form[name = "signInForm"]:visible');
            var form = $('form[name = "signInForm"]:visible');
            browserAPI.log("waiting... " + counter);
            if (form.length > 0) {
                clearInterval(login);
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "login"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button.btn_bordered').get(0).click();
                    setTimeout(function() {
                        plugin.checkLoginErrors(params);
                    }, 3000);
                });
            }
            if (counter > 30) {
                clearInterval(login);
                provider.setError(util.errorMessages.loginFormNotFound);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('p.errorMessage:visible');
        if (errors.length > 0)
            provider.setError(errors.text());
        else {
            provider.setNextStep('loginComplete', function () {
                document.location.href = 'http://www.brusselsairlines.com/com/loop/';
            });
        }
    },

    getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
        var properties = params.account.properties.confFields;
        var form = $('form[name  = "ReservationRetrieveRemoteForm"]:visible');
        if (form.length > 0) {
            form.find('input[name = "bookingReference"]').val(properties.ConfNo);
            form.find('input[name = "remoteSearchCriteria.travelerLastName"]').val(properties.LastName);
            provider.setNextStep('loginComplete', function () {
                $('a.pgButtonRetrieve').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    loginComplete: function() {
        browserAPI.log("loginComplete");
        provider.complete();
    }

};