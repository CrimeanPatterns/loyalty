var plugin = {

    hosts: {'www.boltbus.com': true, 'store.boltbus.com': true},

    getStartingUrl: function(params){
        return 'https://store.boltbus.com/fare-finder?redirect=https://www.boltbus.com/bus-ticket-search';
        // return 'https://www.boltbus.com/';
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

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if ($('#signout:visible').length) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('iframe[id = "iFrameResizer0"]:visible').length || $('#loyalty:visible').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        return false;
        var name = util.findRegExp( $('#ctl00_cphM_LinkButtonProfile').text(), /Hello\s*([^<]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('#signout').click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId == 0   ) {
            provider.setNextStep('getConfNoItinerary', function(){
                document.location.href = 'https://store.boltbus.com/retrieve-booking';
            });
            return;
        }

        var form = $('#loyalty:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "credentials"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button.btn-loyalty').click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 5000);
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $("div.login-error:visible");
        if (errors.length > 0)
            provider.setError(errors.text());
        else {
            if ($('#signout:visible').length === 0)
                return;
            plugin.loginComplete(params);
        }
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://store.boltbus.com/my-trips';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function(params) {
        browserAPI.log("toItineraries");
        setTimeout(function() {
            var confNo = params.account.properties.confirmationNumber;
            var link = $('button[data-href *= "' + confNo +'"]');
            if (link.length > 0) {
                provider.setNextStep('itLoginComplete', function() {
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
        var form = $('form#retrieveForm');
        if (form.length > 0) {
            form.find('input[name = "confirmationNumber"]').val(properties.ConfNo);
            form.find('input[name = "lastName"]').val(properties.LastName);
            provider.setNextStep('itLoginComplete', function() {
                form.find('button:contains("Retrieve")').click();
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
    },    

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }
};