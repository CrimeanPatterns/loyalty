var plugin = {
    flightStatus: {
        url: 'https://m.flyasiana.com/I/en/DefaultFlightDepartureSearch.do',
        match: /^\d+$/i,

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                var view = $('a[id *= "arrivaldepSearch"]');
                var flightNumber = $('input[id = "flights_inquire"]');
                browserAPI.log("waiting... " + start);
                if (view.length > 0) {
                    // find by Flight No.
                    $('li#fltNo').get(0).click();
                    browserAPI.log("submit form");
                    clearInterval(start);
                    // find by flight
                    setTimeout(function () {
                        flightNumber.val(params.flightNumber);
                        api.setNextStep('finish', function () {
                            view.get(0).click();
                            setTimeout(function() {
                                plugin.flightStatus.finish();
                            }, 3000);
                        });
                    }, 2000);
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
            var flight = $('td:contains("'+ params.flightNumber +'")');
            if (flight.length > 0)
                api.complete();
            else
                api.error("Sorry, there is no arrival/departure information for the flight number and date you entered.");
        }
    },

    autologin: {
        url: "https://m.flyasiana.com/I/EN/GetMemberInformation.do?mobile=y",

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + start);
                if ($('form[id = "form"]').length > 0 || $('a:contains("Log Out")').length > 0) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("Can't determine state");
                }
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
            if ($('a:contains("Log Out")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[id = "form"]').length > 0) {
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
                var form = $('form[id = "form"]');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    clearInterval(login);
                    browserAPI.log("submitting saved credentials");
                    if (!isNaN(parseFloat(params.login)) && isFinite(params.login))
                        form.find('a[id = "u-number"]').get(0).click();
                    else
                        form.find('a[id = "u-id"]').get(0).click();

                    setTimeout(function() {
                        form.find('input[id = "forLoginID"]').val(params.login);
                        form.find('input[id = "forLoginIDPassword"]').val(params.pass);

                        api.setNextStep('checkLoginErrors', function () {
                            form.find('input.btn-login').get(0).click();
                            setTimeout(function() {
                                plugin.autologin.checkLoginErrors();
                            }, 4000);
                        });
                    }, 1000);
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
                && ($('span:contains("' + params.properties.Number + '")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                browserAPI.log("waiting... " + checkLoginErrors);
                var error = $('p.text-notice:visible');
                if (error.length > 0) {
                    clearInterval(checkLoginErrors);
                    api.error(error.text().trim());
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
            api.setNextStep('loadLoginForm', function () {
                $('a:contains("Log Out")').get(0).click();
            });
        },

        loadLoginForm: function () {
            browserAPI.log("loadLoginForm");
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