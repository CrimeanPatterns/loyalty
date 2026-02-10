var plugin = {


	hosts: {'www.aplusrewards.com': true, 'tickets.airtran.com': true},

	getStartingUrl: function(params) {
		return "https://tickets.airtran.com/Login.aspx";
	},

	start: function(params) {
		if (plugin.isLoggedIn())
			if (plugin.isSameAccount(params.account))
				plugin.loginComplete(params);
			else
				plugin.logout();
		else
			plugin.login(params);
	},

	isLoggedIn: function(){
		browserAPI.log("isLoggedIn");
		if( $('div#agency_info').length > 0 ){
			browserAPI.log("LoggedIn");
			return true;
		}
		if ($('a#MemberLoginLoginView_LinkButtonLogIn').length > 0) {
			browserAPI.log('not logged in');
			return false;
		}
		if ($('#ucRewardSummary_tbSummary').length > 0) {
			browserAPI.log("LoggedIn");
			return true;
		}
		browserAPI.log("can't determine");
		provider.setError("Can't determine login state");
		throw "Can't determine login state";
	},

	isSameAccount: function(account){
		// for debug only
		//browserAPI.log("account: " + JSON.stringify(account));
		var number = $('#ucRewardSummary_lblFTNumber');
		if (number.length > 0)
			number = number.text();
		else {
			number = /\d+/.exec($('#agency_info tr:eq(1)').text());
			if (number)
				number = number[0];
		}
		browserAPI.log("number: " + number);
		return ((typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.Number) != 'undefined')
			&& (account.properties.Number != '')
			&& (number == account.properties.Number));
	},

	logout: function(){
		provider.setNextStep('loadLoginForm');
		document.location.href = 'http://tickets.airtran.com/Logout.aspx';
	},

	loadLoginForm: function(params) {
		provider.setNextStep('login');
		document.location.href = plugin.getStartingUrl(params);
	},

	login: function(params){
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
			provider.setNextStep('getConfNoItinerary');
			document.location.href = "https://tickets.airtran.com/RetrieveBooking.aspx";
			return;
		}
		browserAPI.log("login");
		var form = $('form[name = "SkySales"]');
		if(form.length == 0)
			form = $('#SkySales');
		if(form.length > 0){
			browserAPI.log("submitting saved credentials");
			form.find('input[name = "MemberLoginLoginView$TextBoxUserID"]').val(params.account.login);
			form.find('input[name = "MemberLoginLoginView$PasswordFieldPassword"]').val(params.account.password);
			provider.setNextStep('checkLoginErrors');
			plugin.eval("__doPostBack('MemberLoginLoginView$LinkButtonLogIn','');");
		}
		else {
			provider.setError('Login form not found');
			throw 'Login form not found';
			}
	},

	checkLoginErrors: function(params){
		if (!plugin.isLoggedIn()) {
			var errors = $('div#formbox p');
			provider.setError(errors.text());
		}
		else
			plugin.loginComplete(params);
	},

	loginComplete: function(params) {
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			provider.setNextStep('toItineraries');
			document.location.href = 'https://tickets.airtran.com/BookingList.aspx';
			return;
		}
		provider.complete();
		document.location.href = 'https://www.aplusrewards.com/aplus/Member_Home.aspx';
	},

	toItineraries: function(params) {
		provider.setNextStep('itLoginComplete');
		plugin.eval("javascript:__doPostBack('ATBookingListBookingListView','View:" + params.account.properties.confirmationNumber + "');");
	},

	getConfNoItinerary: function(params) {
		var properties = params.account.properties.confFields;
		var form = $('form#SkySales');
		if (form.length > 0) {
			form.find('input[name="ATBookingRetrieveInputRetrieveBookingView$CONFIRMATIONNUMBER1"]').val(properties.ConfNo);
			form.find('input[name="ATBookingRetrieveInputRetrieveBookingView$PAXFIRSTNAME1"]').val(properties.FirstName);
			form.find('input[name="ATBookingRetrieveInputRetrieveBookingView$PAXLASTNAME1"]').val(properties.LastName);
			form.find('input[name="ATBookingRetrieveInputRetrieveBookingView$ORIGINCITY1"]').val(properties.Origin);
			provider.setNextStep('itLoginComplete');
			plugin.eval("__doPostBack('ATBookingRetrieveInputRetrieveBookingView$LinkButtonRetrieve','');");
		}
		else {
			provider.setError('form not found');
		}
	},

	itLoginComplete: function(params) {
		provider.complete();
	},

    eval: function(code){
        // workaround to absesnce of provider.eval in mobile app
        var time;
        // api check
        if((typeof(api) !== 'undefined') && (typeof(api.getDepDate) === 'function') && (api.getDepDate() instanceof Date)){
            var script = document.createElement('script');
            script.type = 'text/javascript';
            script.text = code;
            document.body.appendChild(script);
        }else{
            provider.eval(code);
        }
    }
}
