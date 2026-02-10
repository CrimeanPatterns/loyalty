var plugin = {

    hosts: {
        'egyptairplus.com': true,
        'www.egyptairplus.com': true,
        'www.egyptair.com': true,
        'onlinebooking.egyptair.com': true
    },

    getStartingUrl: function (params) {
        return 'https://www.egyptairplus.com/StandardWebsite/Login.jsp?activeLanguage=EN';
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

    isLoggedIn: function () {
        browserAPI.log('isLoggedIn');
        if ($('div:contains("Card Number"), a:contains("Logout")').length) {
            browserAPI.log('isLoggedInd: true');
            return true;
        }
        if ($('#txtUser,txtPass').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        var number = util.findRegExp( $('div.LoginDetails').html(), /Card Number\s*(\d+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.CardNumber) != 'undefined')
            && (account.properties.CardNumber != '')
            && number
            && (number == account.properties.CardNumber));
    },

    logout: function (params) {
        browserAPI.log('logout');
        provider.setNextStep('loadLoginForm', function () {
            var $a = $('a:contains("Logout")');
            if ($a.length)
                document.location.href = $a.attr('href');
            else
                document.location.href = 'https://www.egyptairplus.com/StandardWebsite/rd.jsp?pageURL=http%3A%2F%2Fwww.egyptairplus.com%2FMS_Member_WebSite%2Ffrequent.jsp%3Fcode%3DLogout%26lang%3Den';
        });
    },

    loadLoginForm: function (params) {
        browserAPI.log('loadLoginForm');
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log('login');
        if (typeof (params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId === 0) {
            provider.setNextStep('getConfNoItinerary', function () {
                document.location.href = 'https://www.egyptair.com/en/Book/Pages/my-reservations.aspx';
            });
            return;
        }

        var $form = $('#form1');
        if ($form.length) {
            browserAPI.log("submitting saved credentials");
            $('#txtUser', $form).val(params.account.login);
            $('#txtPass', $form).val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                $('#btnSubmit').trigger('click');
            });
            return;
        }
        provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function (params) {
        browserAPI.log('checkLoginErrors');
        var $error = $('#errorPanelDiv:visible');
        if ($error.length && '' != util.trim($error.text()))
            provider.setError($error.text());
        else
            provider.setNextStep('loginComplete', function () {
                $('a:contains("My Account")').click();
            });
    },

    getConfNoItinerary: function (params) {
        browserAPI.log("getConfNoItinerary");
        var properties = params.account.properties.confFields;
        util.waitFor({
            selector: 'input[name *= "txtReservationNumber"]',
            success: function () {
                $('input[name *= "txtReservationNumber"]').val(properties.ConfNo);
                $('input[name *= "txtLastName"]').val(properties.LastName);
                provider.setNextStep('itLoginComplete', function () {
                    $('input[name *= "btnSubmit"]').get(0).click();
                });
            },
            fail: function () {
                provider.setError(util.errorMessages.itineraryFormNotFound);
            },
            timeout: 10
        });
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },

    itLoginComplete: function (params) {
        browserAPI.log("itLoginComplete");
        provider.complete();
    }

};
