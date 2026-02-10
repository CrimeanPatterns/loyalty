var plugin = {

	startingUrl: 'https://www.businessextraa.com/AccountSummaryAction.do',

	hosts: {'www.businessextraa.com': true},

	isLoggedIn: function(){
		browserAPI.log("extraa check");
		if($('#aa-Login').html() == 'Login')
			return false;
		if($('#aa-Logout').html() == 'Logout')
			return true;
		provider.setError("Can't determine login state");
		throw "Can't determine login state";
	},

	isSameAccount: function(account){
		// for debug only
		//browserAPI.log("account: " + JSON.stringify(account));
		var number = plugin.getNumber();
		return ((typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.Number) != 'undefined')
			&& (account.properties.Number != '')
			&& (number == account.properties.Number));
	},

	logout: function(){
		document.location.href = '/PublicLogoutAction.do';
	},

	login: function(params){
		var form = $('form[name = "PublicLoginForm"]');
		if(form.length > 0){
			browserAPI.log("submitting saved credentials");
			form.find('input[name = "loginId"]').val(params.login);
			form.find('input[name = "password"]').val(params.password);
			form.find('input[alt = "Go"]').click();
		}
		else {
			provider.setError('Login form not found');
			throw 'Login form not found';
			}
	},

	checkLoginErrors: function(){
		var errors = $('td.errorText').filter(function(){
			var $this = $(this);
		   	return util.trim($this.text()) != '';
		}).last();
		if(errors.length > 0)
			provider.errorMessage = errors.html();
	},

	parse: function(account, data){
		if(document.location.href == 'https://www.businessextraa.com/AccountSummaryAction.do')
			return plugin.parseSummary(account, data);
		else{
			return {nextStep: 'Summary'};
		}
	},

	loadSummary: function(){
		document.location.href = 'https://www.businessextraa.com/AccountSummaryAction.do';
	},

	parseSummary: function(account, data){
		return {
			Balance: plugin.getProperty("Current Point Balance:"),
			Number: plugin.getNumber(),
			BusinessName: plugin.getProperty("Business Name:")
		};
	},

	getProperty: function(name){
		return $('td.moduleText:contains("'+name+'")').next().html();
	},

	getNumber: function(){
		var number = plugin.getProperty('Business ExtrAA Account #:');
		browserAPI.log('number: ' + number);
		return number;
	}

}