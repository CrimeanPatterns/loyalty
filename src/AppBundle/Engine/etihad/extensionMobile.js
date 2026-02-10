var plugin = {
    flightStatus: {
        url: 'https://mbooking.etihad.com/SSW2010/EYM0/#flightstatus?lang=en_GB',
        match: /^\d+$/i,

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                $('label:contains("Flight Number")').click();
                browserAPI.log("waiting... " + start);
                var flightNumber = $('input[name = "flightNumber"]');
                if (flightNumber.length > 0) {
                    clearInterval(start);
                    browserAPI.log("submit form");
                    flightNumber.val(params.flightNumber);
                    setTimeout(function () {
                        // date
                        var date = $.format.date(api.getDepDate(), 'dd/MM/yyyy');
                        var date2 = $.format.date(api.getDepDate(), 'yyyy-MM-dd');
                        browserAPI.log("Date: " + date);
                        //date = '17/02/2015';
                        //date2 = '2015-02-17';
                        var dateInput = $('input[name = "date"][value = "' + date + '"]');
                        if (dateInput.length > 0) {
                            setTimeout(function () {
                                dateInput.attr('checked', 'checked');

                                api.setNextStep('finish', function () {
                                    //browserAPI.log("href: " + $('a#search-btn').attr('href'));
                                    // sometimes it's not working
                                    //$('a#search-btn').get(0).click();
                                    document.location.href = 'https://mbooking.etihad.com/SSW2010/EYM0/#flightstatus?dlDepartureDate=' + date2 + '&dlFlightNumber='+ params.flightNumber +'&result=true';
                                });
                            }, 1000);
                        }
                        else
                            api.errorDate();
                    }, 1000);
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
            var error = $('p:contains("No flights found for your selected criteria")');
            if (error.length > 0)
                api.error(error.text().trim());
            else
                api.complete();
        }
    }//,

    //autologin: {
    //
    //    url: "https://mbooking.etihad.com/SSW2010/EYM0/#flightstatus?lang=en_GB",
    //
    //    start: function () {
    //        browserAPI.log("start");
    //        var counter = 0;
    //        var start = setInterval(function () {
    //            browserAPI.log("waiting... " + start);
    //            if ($('form.loginForm').length > 0 || $('input[name = "btnAccountLogout"]').length > 0) {
    //                clearInterval(start);
    //                plugin.autologin.start2();
    //            }
    //            if (counter > 10) {
    //                clearInterval(start);
    //                api.error("Can't determine state");
    //            }
    //            counter++;
    //        }, 500);
    //    },
    //
    //    start2: function () {
    //        browserAPI.log("start2");
    //        if (this.isLoggedIn()) {
    //            if (this.isSameAccount())
    //                this.finish();
    //            else
    //                this.logout();
    //        }
    //        else
    //            this.login();
    //    },
    //
    //    isLoggedIn: function () {
    //        browserAPI.log("isLoggedIn");
    //        if ($('input[name = "btnAccountLogout"]').length > 0) {
    //            browserAPI.log("LoggedIn");
    //            return true;
    //        }
    //        if ($('form.loginForm').length > 0) {
    //            browserAPI.log('not logged in');
    //            return false;
    //        }
    //        browserAPI.log("Can't determine login state");
    //        api.error("Can't determine login state");
    //        throw "can't determine login state";
    //    },
    //
    //    login: function () {
    //        browserAPI.log("login");
    //        var counter = 0;
    //        var login = setInterval(function () {
    //            var form = $('form.loginForm');
    //            browserAPI.log("waiting... " + login);
    //            if (form.length > 0) {
    //                api.eval("toggleLoginForm()");
    //                browserAPI.log("submitting saved credentials");
    //                form.find('input[name = "username"]').val(params.login);
    //                form.find('input[name = "password"]').val(params.pass);
    //
    //                clearInterval(login);
    //
    //                //api.setNextStep('checkLoginErrors', function () {
    //                //    form.find('#login').click();
    //                //});
    //            }
    //            if (counter > 10) {
    //                clearInterval(login);
    //                browserAPI.log("can't find login form");
    //                api.error("can't find login form");
    //            }
    //            counter++;
    //        }, 500);
    //    },
    //
    //    isSameAccount: function () {
    //        browserAPI.log("isSameAccount");
    //        return false;
    //        //return (typeof(params.properties) !== 'undefined')
    //        //    && (typeof(params.properties.Number) !== 'undefined')
    //        //    && ($('span:contains("' + params.properties.Number + '")').length > 0);
    //    },
    //
    //    checkLoginErrors: function () {
    //        browserAPI.log("checkLoginErrors");
    //        var counter = 0;
    //        var checkLoginErrors = setInterval(function () {
    //            browserAPI.log("waiting... " + checkLoginErrors);
    //            var error = $('div.msg');
    //            if (error.length > 0) {
    //                clearInterval(checkLoginErrors);
    //                api.error(error.text().trim());
    //            }
    //            if (counter > 3) {
    //                clearInterval(checkLoginErrors);
    //                plugin.autologin.finish();
    //            }
    //            counter++;
    //        }, 500);
    //    },
    //
    //    logout: function () {
    //        browserAPI.log("logout");
    //        api.setNextStep('start', function () {
    //            $('input[name = "btnAccountLogout"]').get(0).click();
    //        });
    //    },
    //
    //    finish: function () {
    //        browserAPI.log("finish");
    //        api.complete();
    //    }
    //}
};