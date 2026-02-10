var plugin = {
    flightStatus: {
    	url: 'http://mobile.aa.com/mt/www.aa.com/travelInformation/gatesTimesAccess.do',
        match: /^(?:AA)?\d+/i,

    	start: function () {
            var input = document.getElementById('flightNumber');
           	var form = document.getElementById('gatesTimesForm');
           	if (input !== null && form !== null){
           		input.value = params.flightNumber.replace(/AA/gi, '');

                $('#gatesTimesForm\\.flightParams\\.flightDateParams\\.searchTime').val('000000'); // set All Day period
                var depDateElem = $('option[value*="' + $.format.date(api.getDepDate().getTime(), 'MMMM dd') + '"]');
                if(depDateElem.length == 1){
                    $('#gatesTimesForm\\.travelDate').val(depDateElem.val());
                    api.setNextStep('finish', function () {
                        form.submit();
                    });
                }else{
                    api.errorDate();
                }
           	}
    	},

    	finish: function () {
            if($('#aa-content-container-gatesTimes').length > 0){
    		    api.complete();
            }else{
                api.error($('.lmb_warning').eq(1).text());
            }
    	}
    },// flightStatus:

    autologin: {

        getStartingUrl: function (params) {
            return 'https://www.aa.com/homePage.do?locale=en_US';
        },

        start: function (params) {
            browserAPI.log('start');
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    api.complete();
                else
                    this.logout();
            }
            else
                this.login();
        },

        login: function () {
            browserAPI.log('login');
            var form = $('#loginForm');
            if (form.length > 0) {
                $('a[href = "#aa-hp-login"] .span10').click();
                $('input[name = "loginId"]').val(params.login);
                $('input[name = "lastName"]').val(params.login2);
                $('input[name = "password"]').val(params.pass);
                // refs #11326
                api.setNextStep('checkLoginErrors', function(){
                    form.submit();
                });
            } else {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        },

        isSameAccount: function () {
            browserAPI.log('isSameAccount');
            let number = util.findRegExp($('p.account-number').text(), /#(.+)/i);
            browserAPI.log("number: " + number);
            return ((typeof(params.properties) != 'undefined')
                && (typeof(params.properties.Number) != 'undefined')
                && (params.properties.Number != '')
                && (number.toLowerCase() == params.properties.Number.toLowerCase()));
        },

        isLoggedIn: function () {
            browserAPI.log('isLoggedIn');
            if ($('a:contains("Log out")').length) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('#loginForm').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            provider.setError(util.errorMessages.unknownLoginState);
            return false;
        },

        checkLoginErrors: function () {
            browserAPI.log('checkLoginErrors');
            var error = $('#main .message-error p');
            if (error.length > 0)
                api.error(util.filter(error.text()));
            else
                api.complete();
        },

        logout: function () {
            browserAPI.log('logout');
            api.setNextStep('LoadLoginForm', function () {
                $('a:contains("Log out")').get(0).click();
            });
        },

        LoadLoginForm: function (params) {
            browserAPI.log('LoadLoginForm');
            api.setNextStep('login', function () {
                window.location.href = plugin.autologin.getStartingUrl(params);
            });
        }
    }
};
