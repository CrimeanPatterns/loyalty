var plugin = {
    flightStatus:{
	url: 'https://ci.sas.mobi/fs/?a=en',
    match: /^(?:SK|KF|WF)?\d+/i,

	start: function () {
        var input = $('#txtDefaultFlightNo');
       	var button = $('#btnContinue');
        var selectOperator = $('#ddlCarrier');
       	if (input.length == 1 && button.length == 1){
            if(opMatches = params.flightNumber.match(/(SK|KF|WF)/i))
                selectOperator.val(opMatches[1]);
       		input.val(params.flightNumber.replace(/SK|KF|WF/gi, ''));

            var dateInput = $('#ddlSelectedDay');
            var depDateElem = dateInput.find('option[value*="' + $.format.date(api.getDepDate(), 'dMMMyy').toUpperCase() + '"]');
            if(depDateElem.length == 1){
                dateInput.val(depDateElem.val());
                api.setNextStep('finish', function(){
                    window.location.href = button.attr('href');
                });
            }else{
                api.errorDate();
            }
       	}
	},

	finish: function () {
        if($('#lblFlightStatusHeader').length > 0)
		    api.complete();
        else
            api.error($('#myError_lblErrorMessage').text().trim());
	}
    },

    autologin: {
        url: "https://www.flysas.com/en/mobile/My-profile/Mit-EuroBonus/",

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + start);
                if ($('form[name = "aspnetForm"]').length > 0 || $('a#ctl00_MowFooter_aLogout').length > 0) {
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
            if ($('a#ctl00_MowFooter_aLogout').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[name = "aspnetForm"]').length > 0) {
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
                var form = $('form[name = "aspnetForm"]');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "ctl00$FullRegion$txtUserName"]').val(params.login);
                    form.find('input[name = "ctl00$FullRegion$txtPassWord"]').val(params.pass);

                    clearInterval(login);

                    api.setNextStep('checkLoginErrors', function () {
                        form.find('input[name = "ctl00$FullRegion$btnLogin"]').click();
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
                && ($('span:contains("' + params.properties.Number + '")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                browserAPI.log("waiting... " + checkLoginErrors);
                var error = $('p.errorText:not([style *= "display: none"])');
                if (error.length > 0) {
                    clearInterval(checkLoginErrors);
                    api.error(error.text().trim());
                }
                if (counter > 5) {
                    clearInterval(checkLoginErrors);
                    plugin.autologin.finish();
                }
                counter++;
            }, 500);
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('loadLoginForm', function () {
                $('a#ctl00_MowFooter_aLogout').get(0).click();
            });
        },

        loadLoginForm:function () {
            browserAPI.log("logout");
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