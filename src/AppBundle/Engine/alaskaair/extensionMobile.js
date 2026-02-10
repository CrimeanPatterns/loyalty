var plugin = {
    flightStatus: {
        url: 'https://m.alaskaair.com/flightstatus',
        match: /^(?:AS)?\d+/i,

        start: function(){
            var input = $('#FlightNumber');
            var form = $('form[action*="flightstatus"]');
            if (input !== null && form !== null){
                input.val(params.flightNumber.replace(/AS/i, ''));

                var depDateElem = $('input[value*="' + $.format.date(api.getDepDate(), 'M/d/yyyy') + '"]');
                if(depDateElem.length == 1){
                    depDateElem.click();
                    api.setNextStep('finish', function () {
                        form.submit();
                    });
                }else{
                    api.errorDate();
                }
            }
        },

        finish: function () {
            if($('.status-msg').length > 0){
                api.complete();
            }else{
                api.error($('.server-msg-error').text());
            }
        }
    },

    autologin: {
        url: "https://m.alaskaair.com/account",

        start: function () {
            browserAPI.log('start');
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn();
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (isLoggedIn) {
                        if (plugin.autologin.isSameAccount())
                            plugin.autologin.finish();
                        else
                            plugin.autologin.logout();
                    }
                    else
                        plugin.autologin.login();
                }// if (isLoggedIn !== null)
                if (isLoggedIn === null && counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        },

        isSameAccount: function () {
            browserAPI.log('isSameAccount');
            return ($("#m-number:contains('" + params.account.login + "')").length > 0 || (typeof(params.properties) != 'undefined' &&
                typeof(params.properties.Number) != 'undefined' &&
                params.properties.Number != '' &&
                $('.m-number:contains("'+ params.properties.Number +'")').length > 0));
        },

        isLoggedIn: function () {
            browserAPI.log('isLoggedIn');
            if ($('h1.header-h1:contains("Account")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form#signin-form').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            return null;
        },

        login: function () {
            browserAPI.log('login');
            var form = $('form#signin-form');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "MyAccountSignIn.UserId"]').val(params.account.login).trigger('change keyup keydown');
                form.find('input[name = "MyAccountSignIn.Password"]').val(params.account.password).trigger('change keyup keydown');
                provider.setNextStep('checkLoginErrors', function () {
                    form.find('button#signin').click();
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        },

        checkLoginErrors: function () {
            browserAPI.log('checkLoginErrors');
            var error = $('.server-msg-error:visible');
            if (error.length > 0)
                provider.error(error.text());
            else
                plugin.autologin.finish();
        },

        logout: function () {
            browserAPI.log('logout');
            provider.setNextStep('start', function () {
                $('a.sign-inout-link:visible').get(0).click()
            });
        },

        finish: function () {
            browserAPI.log('finish');
            provider.complete();
        }
    }
};
