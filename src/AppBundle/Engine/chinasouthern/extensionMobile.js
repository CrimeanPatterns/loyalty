var plugin = {

    flightStatus: {
        url: 'http://m.csair.com/touch/com.csair.mbp.index/index.html#com.csair.mbp.flightstatus_new/',
        match: /^\d+/i,

        start: function() {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                var form = $('#queryStatus-form');
                browserAPI.log("waiting... " + start);
                if (form.length > 0) {
                    // Find by flight number
                    $('#queryByNo_li').get(0).click();
                    browserAPI.log("submit form");
                    form.find('input[name = "flightno"]').val(params.flightNumber);

                    var date = $.format.date(api.getDepDate(), 'yyyy-MM-dd');
                    browserAPI.log("Date: " + date);
                    form.find('input[name = "queryDate"]').val(date);

                    clearInterval(start);

                    api.setNextStep('finish', function(){
                        form.find('a#queryStatusbtn').get(0).click();

                        setTimeout(function() {
                            plugin.flightStatus.finish();
                        }, 3000)
                    });
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
            var errors = $('p.cube-dialog-subtitle');
            if (errors.length > 0)
                api.error(errors.text().trim());
            else
                api.complete();
        }
    },

    autologin: {
        url: "http://m.csair.com/touch/com.csair.mbp.index/index.html#com.csair.mbp.SouthernAirlinesPearl_new/Mileage",

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + start);
                if ($('#username').length > 0 || $('#depAndArrCityName').length > 0) {
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
            if (plugin.autologin.isLoggedIn()) {
                if (plugin.autologin.isSameAccount())
                    api.complete();
                else
                    plugin.autologin.toHomePage('logout');
            }
            else
                plugin.autologin.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('#username').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            if ($('#depAndArrCityName').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        login: function () {
            browserAPI.log("login");
            var login = $('#username');
            var button = $('#loginBtn');
            if (login.length > 0 && button.length > 0) {
                login.val(params.login);
                $('#password').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    button.addClass('on');
                    button.get(0).click();
                    plugin.autologin.checkLoginErrors();
                });
            } else {
                browserAPI.log("can't find login form");
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            var numEl = $('#depAndArrCityName');
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && (numEl.length > 0)
                && (numEl.val().indexOf(params.properties.Number) !== -1)
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            setTimeout(function () {
                var error = $('.cube-dialog-subtitle');
                if (error.length > 0)
                    api.error(error.text().trim());
                else
                    plugin.autologin.toHomePage('finish');
            }, 10000);
        },

        logout: function () {
            browserAPI.log("logout");
            setTimeout(function () {
                api.setNextStep('toLoginPage', function () {
                    $('.logoutBtn').click();
                    $('.cube-dialog-btn[eventname *= "ok"]').click();
                    plugin.autologin.toLoginPage();
                });
            }, 10000);
        },

        toLoginPage: function () {
            browserAPI.log("toLoginPage");
            api.setNextStep('start', function () {
                document.location.href = plugin.autologin.url;
            })
        },

        toHomePage: function (nextStep) {
            browserAPI.log("toHomePage");
            if (!nextStep) {
                nextStep = 'finish';
            }
            api.setNextStep(nextStep, function () {
                $('.bar-title .button').get(1).click();
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};