var plugin = {
    flightStatus:{
	url: 'http://m.flyfrontier.com/m/FlightStatus',
    match: /^(?:F9)?\d+/i,

	start: function () {
        var input = $('#txtFlightNumber');
        var button = $('#FlightStatusSearch');
        if(input.length == 1 && button.length == 1){
            input.val(params.flightNumber.replace(/F9/gi, ''));

            var today     = new Date();
            var yesterday = new Date();
            var tomorrow  = new Date();

            yesterday.setDate(yesterday.getDate() - 1);
            tomorrow.setDate(tomorrow.getDate() + 1);
            var depDate = api.getDepDate();
            var depDateValue = '';
            if(depDate.getDate() == yesterday.getDate())
                depDateValue = 'Yesterday';
            else if(depDate.getDate() == today.getDate())
                depDateValue = 'Today';
            else if(depDate.getDate() == tomorrow.getDate())
                depDateValue = 'Tomorrow';

            if(depDateValue != ''){
                $('#maincontentarea_0_rdo' + depDateValue).click();
                api.setNextStep('finish', function(){
                    button.click()
                });
            }else{
                api.errorDate();
            }
        }
	},

	finish: function () {
        if($('.FlightStatusResultsContainer').length > 0){
		    api.complete();
        }else{
            api.error($('#maincontentarea_0_ResultsDetails > div').eq(0).text());
        }
	}
    },

    autologin: {

        getStartingUrl: function (params) {
            return 'https://m.flyfrontier.com/m/EarlyReturns';
        },

        start: function (params) {
            browserAPI.log("start");
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    api.finish();
                else
                    this.logout();
            }
            else
                this.login();
        },

        login: function () {
            browserAPI.log("login");
            var form = $('form#form1');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "maincontentarea_0$txtUserName"]').val(params.account.login);
                form.find('input[name = "maincontentarea_0$txtPassword"]').val(params.account.password);
                api.setNextStep('checkLoginErrors', function () {
                    $('div#EarlyRetTextWrapper').get(0).click();
                });
            }
            else
                api.error("can't find login form");
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return false;
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('#lnkSignOut').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form#form1').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            api.error("can't determine login state");
            return false;
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('div.error');
            if (error.length > 0)
                api.error(error.text());
            else {
                api.finish();
            }
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('LoadLoginForm', function () {

                var form = $('form#form1');
                if (form.length > 0) {
                    var inp1 = document.createElement( 'input' );
                    inp1.type = 'hidden';
                    inp1.name = '__EVENTTARGET';
                    inp1.id = '__EVENTTARGET';
                    inp1.value = 'maincontentarea_0$lnkSignOut';
                    document.getElementById( 'form1' ).appendChild( inp1 );

                    var inp2 = document.createElement( 'input' );
                    inp2.type = 'hidden';
                    inp2.name = '__EVENTARGUMENT';
                    inp2.id = '__EVENTARGUMENT';
                    inp2.value = '';
                    document.getElementById( 'form1' ).appendChild( inp2 );

                    form.submit();
                }
            });
        },

        LoadLoginForm: function (params) {
            browserAPI.log("LoadLoginForm");
            api.setNextStep('start', function () {
                window.location.href = plugin.autologin.getStartingUrl(params);
            });
        },

        finish: function () {
            api.complete();
        }
    }//
};