var plugin = {
    flightStatus: {
        url: 'http://en.aegeanair.com/e-services/flight-status/',
        match: /A?\d+/i,

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                var form = $('form[name *= "flightStatusForm"]');
                browserAPI.log("waiting... " + start);
                if (form.length > 0) {
                    browserAPI.log("submit form");
                    // date
                    var date = $.format.date(api.getDepDate(), 'd-M-yyyy');
                    browserAPI.log("Date: " + date);
                    var dateInput = $('a[data-date = "' + date + '"]');
                    if (dateInput.length > 0) {
                        // find by day
                        dateInput.get(0).click();
                        clearInterval(start);
                        // find by flight
                        setTimeout(function () {
                            if (params.flightNumber.indexOf('A3') == -1)
                                params.flightNumber = 'A3' + params.flightNumber;
                            form.find('input[name = "mailfooter"]').val(params.flightNumber);

                            api.setNextStep('finish', function () {
                                form.find('input[type = "submit"]').get(0).click();

                                setTimeout(function () {
                                    plugin.flightStatus.finish();
                                }, 3000)
                            });
                        }, 2000);
                    }
                    else
                        api.errorDate();
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("can't find form");
                }
                counter++;
            }, 500);
        },

        finish: function () {
            browserAPI.log("finish");
            var error = $('div.noResults:visible');
            if (error.length > 0)
                api.error(error.text().trim());
            else
                api.complete();
        }
    },

    autologin: {

        url: "https://en.aegeanair.com/milesandbonus/my-account/",//"https://mobile.aegeanair.com/mbs/ident.jsp?l=en",

        /*start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + start);
                if ($('form[action *= "Login"]').length > 0 || $('input[name = "btnAccountLogout"]').length > 0) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("Can't determine state");
                }
                counter++;
            }, 500);
        },*/

        start: function () {
            browserAPI.log("start");
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
            if ($('.logout').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[action *= "Login"]').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        login: function () {
            browserAPI.log("login");
            var counter = 0;
            var login = setInterval(function () {
                var form = $('form[action *= "Login"]');
                browserAPI.log("waiting... " + counter);
                if (form.length > 0) {
                    //api.eval("toggleLoginForm()");
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "Username"]').val(params.login);
                    form.find('input[name = "Password"]').val(params.pass);

                    clearInterval(login);

                    api.setNextStep('checkLoginErrors', function () {
                        form.find('button[type = "submit"]').click();
                    });
                }
                if (counter > 10) {
                    clearInterval(login);
                    browserAPI.log("can't find login form");
                    api.error("can't find login form");
                }
                counter++;
            }, 500);
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return (typeof(params.properties) !== 'undefined')
               && (typeof(params.properties.Number) !== 'undefined')
               && ($('.cardNumber:contains("' + params.properties.Number + '")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('.validation-summary-errors li').eq(0);
            if (error.length > 0) {
                clearInterval(checkLoginErrors);
                api.error(error.text().trim());
            }else{
                this.finish();
            }
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('toLoginPage', function () {
                document.location.href = $('.logout a').attr('href');
            });
        },

        toLoginPage: function () {
            browserAPI.log("toLoginPage");
            api.setNextStep('start', function () {
                document.location.href = plugin.autologin.url;
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};