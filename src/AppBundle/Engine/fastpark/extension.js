var plugin = {

    hosts: {'www.thefastpark.com': true},

    getStartingUrl: function (params) {
        return 'https://www.thefastpark.com/relaxforrewards/rfr-dashboard';
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
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
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
        if ($('a#a_btn_SignOut').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#Form:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        return false;
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        // var number = util.findRegExp(/Member\s*#\s*:\s*([\d]+)/i);
        // browserAPI.log("number: " + number);
        // return ((typeof(account.properties) != 'undefined')
        //     && (typeof(account.properties.Number) != 'undefined')
        //     && (account.properties.Number != '')
        //     && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a#a_btn_SignOut').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://www.thefastpark.com/reservation-find';
            });
            return;
        }
        var form = $('form#Form:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "dnn$ctl00$ctl01$signIn_username"]').val(params.account.login);
            form.find('input[name = "dnn$ctl00$ctl01$signIn_password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[name = "dnn$ctl00$ctl01$signIn_submit"]').get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.unknownLoginState);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $("#dnn_ctl00_ctl01_div_responseErrorMessage:visible");
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params)
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://www.hawaiianairlines.com/my-account/my-trips/upcoming-trip-itinerary';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('span:contains("'+ confNo +'"), h3:contains("'+ confNo +'")').parents('div.col').find('a:contains("Open")');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function(){
                    link.get(0).click();
                });
            }// if (link.length > 0)
            else
                provider.setError(util.errorMessages.itineraryNotFound);
        }, 2000);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        var form = $('form#Form');
        if (form.length > 0) {
            form.find('input[name = "dnn$ReservationFinder$yourEmail"]').val(properties.Email);
            form.find('input[name = "dnn$ReservationFinder$reservationNumber"]').val(properties.ConfNo);
            provider.setNextStep('itLoginComplete', function() {
                form.find('input[name = "dnn$ReservationFinder$btnFindReservation"]').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

}