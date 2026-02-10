var plugin = {

    hosts: {'eservice.evaair.com': true, 'www.evaair.com': true, 'booking.evaair.com': true, '.evaair.com': true},
    clearCache: true,

    getStartingUrl: function (params) {
        return "https://eservice.evaair.com/flyeva/EVA/FFP/Login.aspx";
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
        if ($('a:contains("Logout"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form#User_Page').length > 0) {
            browserAPI.log('not logged in');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.filter($('span:contains("Membership Number:") + span').text());
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.MemberNo) != 'undefined')
            && (account.properties.MemberNo != '')
            && number
            && (number == account.properties.MemberNo));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Logout"):visible').get(0).click();
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

        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = "https://booking.evaair.com/flyeva/eva/b2c/manage-your-trip/log_in.aspx";
            });
            return;
        }

        setTimeout(function () {
            let form = $('form#User_Page');
            if (form.length === 0) {
                provider.setError(util.errorMessages.loginFormNotFound);
                return;
            }

            browserAPI.log("submitting saved credentials");
            form.find('input[id = "content_wuc_login_Account"]').val(params.account.login);
            form.find('input[id = "content_wuc_login_Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                provider.eval('of_login();');
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }, 2000)
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('#wuc_Error:visible');
        if (errors.length > 0 && util.filter(errors.text()) != "")
            provider.setError(util.filter(errors.text()));
        else
            plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        setTimeout(function () {
            if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
                provider.setNextStep('toItineraries', function () {
                    document.location.href = 'https://booking.evaair.com/flyeva/EVA/B2C/manage-your-trip/reservation-reference.aspx';
                });
                return;
            }
            provider.complete();
        }, 2000)
    },

    toItineraries: function (params) {
        browserAPI.log("toItineraries");
        var counter = 0;
        var confNo = params.account.properties.confirmationNumber.toUpperCase();
        var toItineraries = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var view = $('h3:contains("' + confNo + '") + div').find('button[data-act="View"]');
            if (view.length > 0) {
                clearInterval(toItineraries);
                provider.setNextStep('skipCompanions', function () {
                    view.get(0).click();
                });
            }// if (link.length > 0)
            if (counter > 30) {
                clearInterval(toItineraries);
                provider.setError(util.errorMessages.itineraryNotFound);
            }
            counter++;
        }, 500);
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        setTimeout(function () {
            var form = $('form#User_Page');
            if (form.length > 0) {
                form.find('input[name = "ctl00$content$wuc_PNR$txt_Code"]').val(properties.ConfNo);
                form.find('input[name = "ctl00$content$wuc_PNR$txt_LastName"]').val(properties.LastName);
                form.find('input[name = "ctl00$content$wuc_PNR$txt_FirstName"]').val(properties.FirstName);
                provider.setNextStep('skipCompanions', function () {
                    form.find('button[name = "ctl00$content$wuc_PNR$btn_Go"]').get(0).click();
                });
            }
            else
                provider.setError(util.errorMessages.itineraryFormNotFound);
        }, 2000)
    },

    skipCompanions: function (params) {
        browserAPI.log('skipCompanions');
        util.waitFor({
            selector: 'button[data-event *= "btn_returntrip_Click"]',
            success: function(elem) {
                provider.setNextStep('itLoginComplete', function() {
                    elem.get(0).click();
                });
            },
            fail: function() {
                plugin.itLoginComplete(params);
            }
        });
    },

    itLoginComplete: function (params) {
        browserAPI.log('itLoginComplete');
        provider.complete();
    }
};
