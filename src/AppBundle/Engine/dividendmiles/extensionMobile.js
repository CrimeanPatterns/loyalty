var plugin = {
    flightStatus: {
        url: 'http://mobile.usairways.com/mt/flights.usairways.com',
        match: /^(?:US)?\d+/i,

        start: function () {
            var input = document.getElementById('un_jtt_flight_num');
            var button = document.getElementsByName('un_jtt_flifo_submit')[0];
            if (input !== null && typeof(button) !== 'undefined') {
                input.value = params.flightNumber.replace(/US/gi, '');

                var depDateElem = $('[name="un_jtt_btn__' + $.format.date(api.getDepDate(), 'M/d/yyyy') + '"]');
                if (depDateElem.length == 1) {
                    depDateElem.click();
                    api.setNextStep('finish', function () {
                        button.click()
                    });
                } else {
                    api.errorDate();
                }
            }
        },

        finish: function () {
            var error = $('.red + div:has(#ctl00_ErrorDisplay_ReferenceCodeLabel) > div').eq(1);
            if (error.length == 1) {
                api.error(error.text());
            } else {
                api.complete();
            }
        }
    },

    autologin: {

        url: "https://mobile.usairways.com/mt/membership-mobile.usairways.com/Manage/AccountSummary.aspx",

        start: function () {
            browserAPI.log("start");
            api.setNextStep('login');
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            }
            else
                this.login();
        },

        login: function () {
            browserAPI.log("login");
            var submitButton = $('[name="un_jtt_new_login_submit"]');
            if (submitButton.length == 1) {
                browserAPI.log("submitting saved credentials");
                $('input#ctl00_phMain_loginModule_ctl00_loginForm_UserName').val(params.login);
                $('input#ctl00_phMain_loginModule_ctl00_loginForm_txtLastName').val(params.login2);
                $('input#ctl00_phMain_loginModule_ctl00_loginForm_Password').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    submitButton.click();
                });
            } else {
                browserAPI.log("can't find login form");
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            var number = $('td:contains("AAdvantage number") + td').text();
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && (number == params.properties.Number);
        },


        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('[name="un_jtt_new_login_submit"]').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            if ($('#ctl00_loginView_lnkLogOut').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('#un_server_side_error');
            if (error.length > 0)
                api.error(error.text());
            else
                this.finish();
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('login', function () {
                $('#ctl00_loginView_lnkLogOut').get(0).click();
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};