var plugin = {
    hosts: {'www.dougfoxparking.com': true,'dougfoxparking.com': true},

    getStartingUrl: function (params) {
        return 'https://www.dougfoxparking.com/point-club/dashboard';
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

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[aria-label="PointClubAbout"]:contains("Point Club")').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('.point-club-dashboard').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp($('strong:contains("Your Membership Number:")').parent().text(), /:\s*(.+)/i);
        browserAPI.log("number: " + number);
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.Number) !== 'undefined'
            && account.properties.Number !== ''
            && number
            && number === account.properties.Number;
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('start', function () {
            var logout = $('.xs-flex-row-reverse > div:nth-child(2) > a');
            if (logout.length) {
                logout.get(0).click();
                setTimeout(function () {
                    logout = $('.user-dd-menu__section a:contains("Log Out")');
                    if (logout.length) {
                        logout.get(0).click();
                    }
                    setTimeout(function () {
                        plugin.start(params);
                    }, 2000);
                }, 1000);
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var logIn = $('a[aria-label="PointClubAbout"]:contains("Point Club")');
        if (logIn.length) {
            logIn.get(0).click();
        }
        setTimeout(function () {
            var form = $('div.login-form > form');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "email"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                provider.eval(
                    'function createNewEvent(eventName) {' +
                    'var event;' +
                    'if (typeof(Event) === "function") {' +
                    '    event = new Event(eventName);' +
                    '} else {' +
                    '    event = document.createEvent("Event");' +
                    '    event.initEvent(eventName, true, true);' +
                    '}' +
                    'return event;' +
                    '}'+
                    'var email = document.querySelector(\'input[name = "email"]\');' +
                    'email.dispatchEvent(createNewEvent(\'input\'));' +
                    'email.dispatchEvent(createNewEvent(\'change\'));' +
                    'var pass = document.querySelector(\'input[name = "password"]\');' +
                    'pass.dispatchEvent(createNewEvent(\'input\'));' +
                    'pass.dispatchEvent(createNewEvent(\'change\'));'
                );
                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        $('button:contains("Login")').get(0).click();
                        setTimeout(function () {
                            plugin.checkLoginErrors();
                        }, 5000);
                    }, 1000);
                });
            }// if (form.length > 0)
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }, 500);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('.rlx-form-message.rlx-form-message--error');
        if (errors.length > 0 && util.trim(errors.text()) !== '')
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete: function(params) {
        browserAPI.log("loginComplete");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
            provider.setNextStep('toItineraries', function() {
                document.location.href = 'https://dougfoxparking.com/point-club/dashboard';
            });
            return;
        }
        provider.complete();
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var confNo = params.account.properties.confirmationNumber;
        provider.setNextStep('itLoginComplete', function () {
            document.location.href = 'https://dougfoxparking.com/bookings/' + confNo;
        });
        var link = $('span:contains("' + confNo + '"), h3:contains("' + confNo + '")').parents('div.col').find('a:contains("Open")');
        /*if (link.length > 0) {
            provider.setNextStep('itLoginComplete', function(){
                link.get(0).click();
            });
        }// if (link.length > 0)
        else
            provider.setError(util.errorMessages.itineraryNotFound);*/
    },

    itLoginComplete: function(params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};