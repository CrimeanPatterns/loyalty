var plugin = {
    autologin: {

        cashbackLink : '', // Dynamically filled by extension controller
        startFromCashback : function(params) {
            browserAPI.log('startFromCashback');
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        },

        getStartingUrl: function (params) {
            return "https://m.cvs.com/mt/www.cvs.com/extracare/landing.jsp?t=MyProfile&un_jtt_v_link=tab";
        },

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + counter);
                var isLoggedIn = plugin.autologin.isLoggedIn();
                var signIn =  $('a:contains("Sign In")');
                if (isLoggedIn !== null) {
                    clearInterval(start);
                    if (signIn.length > 0) {
                        provider.setNextStep('start2', function () {
                            signIn.get(0).click();
                        });
                    }
                    else
                        plugin.autologin.start2();
                }// if (isLoggedIn !== null)
                if (isLoggedIn === null && counter > 10) {
                    clearInterval(start);
                    provider.setError(util.errorMessages.unknownLoginState);
                    return;
                }// if (isLoggedIn === null && counter > 10)
                counter++;
            }, 500);
        },

        start2: function () {
            browserAPI.log("start2");
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            }
            else
                this.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('a:contains("Sign Out")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[action *= "login"]').length > 0 || $('a:contains("Sign In")').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            return null;
        },

        login: function () {
            browserAPI.log("login");
            var counter = 0;
            var login = setInterval(function () {
                var form = $('form[action *= "login"]');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[id = "login_new"]').val(params.login);
                    form.find('input[id = "password_new"]').val(params.pass);

                    clearInterval(login);

                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('input[name = "login"]').click();
                    });
                }
                if (counter > 10) {
                    clearInterval(login);
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
                counter++;
            }, 500);
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            var number = $('span[ng-bind *= "extracareCardNo"]:eq(0)').text();
            browserAPI.log("number: " + number);
            return ((typeof(account.properties) != 'undefined')
                && (typeof(account.properties.ExtraCareNumber) != 'undefined')
                && (account.properties.ExtraCareNumber != '')
                && number
                && (number == account.properties.ExtraCareNumber));
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                browserAPI.log("waiting... " + checkLoginErrors);
                var error = $('div#formerrorswrapper');
                if (error.length > 0) {
                    clearInterval(checkLoginErrors);
                    provider.setError(error.text().trim());
                }
                if (counter > 3) {
                    clearInterval(checkLoginErrors);
                    plugin.autologin.finish();
                }
                counter++;
            }, 500);
        },

        logout: function () {
            browserAPI.log("logout");
            provider.setNextStep('loadLoginForm', function () {
                $('input[value = "Sign Out"]').get(0).click();
            });
        },

        loadLoginForm: function () {
            browserAPI.log("loadLoginForm");
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl();
            });
        },

        finish: function () {
            browserAPI.log("finish");
            provider.complete();
        }
    }
};