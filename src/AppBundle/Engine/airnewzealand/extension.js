var plugin = {

    clearCache: true,

    hosts: {
        'www.airnewzealand.com.au': true,
        'www.airnewzealand.ca': true,
        'www.airnewzealand.com.cn': true,
        'www.airnewzealand.fr': true,
        'www.airnewzealand.com.hk': true,
        'www.airnewzealand.co.jp': true,
        'www.pacificislands.airnewzealand.com': true,
        'www.airnewzealand.pf': true,
        'www.airnewzealand.co.uk': true,
        'www.airnewzealand.com': true,
        'www.airnewzealand.co.nz': true,
        'auth.airnewzealand.co.nz': true,
        'flightbookings.airnewzealand.co.nz': true
    },

    getStartingUrl: function(params) {
        var url = "https://" + plugin.getHost(params) + "/vloyalty/action/mybalances/airpoints";
        browserAPI.log("starting url => " + url);
        return url;
    },

    getRetrieveUrl: function(params) {
        var host = plugin.getHost(params).replace(/\bwww\./, '');
        var url = 'https://flightbookings.' + host + '/vmanage/actions/retrieve';
        browserAPI.log("retrieve url => " + url);
        return url;
    },

    getHost: function (params) {
        // Identification host
        var host = 'www.airnewzealand.co.nz';
        switch (params.account.login2) {
            case 'Australia':
                host = 'www.airnewzealand.com.au';
                break;
            case 'Canada':
                host = 'www.airnewzealand.ca';
                break;
            case 'China':
                host = 'www.airnewzealand.com.cn';
                break;
            // program is not supported for this region now
            //case 'Deutschland':
            //    host = 'www.airnewzealand.de';
            //    break;
            case 'France':
                host = 'www.airnewzealand.fr';
                break;
            case 'HongKong':
                host = 'www.airnewzealand.com.hk';
                break;
            case 'Japan':
                host = 'www.airnewzealand.co.jp';
                break;
            case 'PacificIslands':
                host = 'www.pacificislands.airnewzealand.com';
                break;
            case 'Tahiti':
                host = 'www.airnewzealand.pf';
                break;
            case 'UK':
                host = 'www.airnewzealand.co.uk';
                break;
            case 'USA':
                host = 'www.airnewzealand.com';
                break;
            default:
                host = 'www.airnewzealand.co.nz';
        }

        return host;
    },

    start: function (params) {
        browserAPI.log("start");

        // You are being redirected to the Air New Zealand site that matches your country of residence.
        var continueLink = $('a:contains("Continue")');
        if (continueLink.length > 0) {
            continueLink.get(0).click();
            return;
        }

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
        if ($('.vui-loginheader-signout').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name = "login"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
       return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp( $('div.airpoints-number, div[id *= "airpoints-number-container"]').eq(0).text(), /(?:no\.|number)\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return (
            (typeof(account.properties) != 'undefined') &&
            (typeof(account.properties.Number) != 'undefined') &&
            (account.properties.Number !== '') &&
            (number == account.properties.Number)
        );
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            //document.location.href = 'https://' + plugin.getHost(params) + '/vloyalty/action/logout';
            document.location.href = 'https://auth.airnewzealand.co.nz/vauth/oauth2/logout';
        });
    },

    loadLoginForm: function(params) {
        browserAPI.log("login");
        provider.setNextStep('login', function() {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getConfNoItinerary: function(params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'input[name *= "bookingReference"]:visible',
            success: function() {
                /*
                var inputConfNo = $('input[name *= "bookingReference"]');
                var inputLastName = $('input[name *= "familyName"]');
                inputConfNo.val(properties.ConfNo);
                inputLastName.val(properties.FamilyName);
                */
                // reactjs
                provider.eval(
                    "var setValue = function (id, value) {" +
                    "let input = document.querySelector('input[id = ' + id + ']');" +
                    "let lastValue = input.value;" +
                    "input.value = value;" +
                    "let event = new Event('input', { bubbles: true });" +
                    "event.simulated = true;" +
                    "let tracker = input._valueTracker;" +
                    "if (tracker) {" +
                    "   tracker.setValue(lastValue);" +
                    "}" +
                    "input.dispatchEvent(event);" +
                    "};" +
                    "setValue('pb-ManageBookingRetrieve__bookingReference', '" + properties.ConfNo + "');" +
                    "setValue('pb-ManageBookingRetrieve__familyName', '" + properties.FamilyName + "');"
                );

                var button = $('form button[type="submit"]');
                button.get(0).click();
                setTimeout(function() {
                    plugin.checkLoginErrors(params);
                }, 2000);
            },
            fail: function() {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            },
            timeout: 10
        });
    },

    login: function(params) {
        browserAPI.log("login");
        if (    typeof(params.account.itineraryAutologin) == "boolean" &&
                params.account.itineraryAutologin &&
                params.account.accountId === 0     ) {
            provider.setNextStep('getConfNoItinerary', function() {
                // document.location.href = 'https://flightbookings.airnewzealand.co.nz/vmanage/actions/managebookingstart';
                document.location.href = plugin.getRetrieveUrl(params);
            });
            return;
        }

        var form = $('form[name = "login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            provider.setNextStep('login2', function() {
                form.find('input[name = "xv_username"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);
                form.find('button[type="submit"]').get(0).click();
            });
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    login2: function(params) {
        browserAPI.log("login2");
        var form = $('form[name = "login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            provider.setNextStep('checkLoginErrors', function() {
                form.find('input[name = "xv_username"]').val(params.account.login);
                form.find('input[name = "password"]').val(params.account.password);

                var captcha = form.find('iframe[src *= "https://www.google.com/recaptcha/api2/anchor"]:visible');
                if (captcha.length > 0) {
                    provider.reCaptchaMessage();
                    provider.setNextStep('checkLoginErrors', function() {
                        var counter = 0;
                        var login = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            form.find('input[name = "password"]').val(params.account.password);
                            if (counter > 160) {
                                clearInterval(login);
                                provider.setError(util.errorMessages.captchaErrorMessage, true);
                            }
                            counter++;
                        }, 500);
                    });
                    form.find('button[type="submit"]').click(function () {
                        form.find('input[name = "password"]').val(params.account.password);
                    });
                } else
                    form.find('button[type="submit"]').get(0).click();
            });
        }
        else
            plugin.checkLoginErrors();
    },

    checkLoginErrors: function() {
        browserAPI.log('checkLoginErrors');
        var errors = $("div#message:visible, div.validation-advice:visible, div.errormsg:visible");
        if (errors.length > 0)
            provider.setError(errors.text().trim());
        else
            provider.complete();
    }

};
