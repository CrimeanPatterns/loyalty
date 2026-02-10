var plugin = {
    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.84 Safari/537.36',
	hosts: {'www.dollar.com': true},

    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

	getStartingUrl: function(params) {
		return "https://www.dollar.com/Express/MainMember.aspx";
	},

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

	isLoggedIn: function(checkRedirect){
		browserAPI.log("isLoggedIn");
		if( $('a#LinkButton1').length > 0 ){
			browserAPI.log("LoggedIn");
			return true;
		}
		if ($('a[id*="EnrollHyperlink"]').length > 0) {
			browserAPI.log('not logged in');
			return false;
		}
		browserAPI.log("can't determine");
        if(!checkRedirect){
            provider.setError("Can't determine login state");
        }
		throw "Can't determine login state";
	},

	isSameAccount: function(account){
		// for debug only
		//browserAPI.log("account: " + JSON.stringify(account));
		var number = $('span[id*="DxMemberShipNumber"]').text();
		browserAPI.log("number: " + number);
		var matches = /\d+/.exec(number);
		return (matches
			&& (typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.AccountNumber) != 'undefined')
			&& (account.properties.AccountNumber != '')
			&& (matches[0] == account.properties.AccountNumber));
	},

	logout: function(){
		var func = "__doPostBack('ctl04','event=LoginLogoutLink_Click&control=LinkButton1');";
		var link = /__doPostBack.+/.exec($('a#LinkButton1').attr('href'));
		if (link)
			func = link[0] + ";";
		provider.setNextStep('loadLoginForm');
		provider.eval(func);
	},

	loadLoginForm: function(params) {
		provider.setNextStep('login');
		document.location.href = plugin.getStartingUrl(params);
	},

	login: function(params){
		browserAPI.log("login");
		var form = $('form#MainForm');
		if(form.length > 0){
			browserAPI.log("submitting saved credentials");
			form.find('input[name *= "$ExpressLoginColumnLayout$ExpressIDTextBox"]').val(params.account.login);
			form.find('input[name *= "$ExpressLoginColumnLayout$PasswordTextBox"]').val(params.account.password);
			provider.setNextStep('checkLoginErrors');
            form.find('input[name *= "$ExpressLoginColumnLayout$LoginButton"]').click();
		}
		else {
			provider.setError('Login form not found');
			throw 'Login form not found';
			}
	},

	checkLoginErrors: function(params){
		var errors = $('span.ValidatorMessage:visible');
		if (errors.length > 0) {
			provider.setError(errors.text());
		}
		else {
			plugin.loginComplete(params);
		}
	},

	loginComplete: function(params) {
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			if ($('table[id*="PendingReservationsGridView"]').length > 0) {
				plugin.toItineraries(params);
			}
			else {
				provider.setNextStep('toItineraries');
				document.location.href = 'https://www.dollar.com/Express/MainMember.aspx';
			}
			return;
		}
        if (typeof(params.account.fromPartner) == 'string') {
            setTimeout(provider.close, 1000);
        }
		provider.complete();
	},

	toItineraries: function(params) {
		var confNo = params.account.properties.confirmationNumber;
		var link = $('a[href*="' + confNo + '"][href*="Reservations/Confirmation.aspx"]');
		if (link.length > 0) {
			provider.setNextStep('itLoginComplete');
			document.location.href = "https://www.dollar.com" + link.attr('href');
		}
		else {
			provider.setError('Itinerary not found');
		}
	},

	itLoginComplete: function(params) {
		provider.complete();
	}
};
