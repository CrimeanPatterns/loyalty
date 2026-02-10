var plugin = {


	hosts: {'www.loyaltygateway.com': true},

	getStartingUrl: function(params){
		return 'https://www.loyaltygateway.com/bankofbermuda/rewards.com/rewards/ControllerServlet?bank_id=186067&i18n=en_US';
	},

	start: function(params){
		if(plugin.isLoggedIn()){
			if(plugin.isSameAccount(params.account))
				provider.complete();
			else
				plugin.logout();
		}
		else
			plugin.login(params);
	},

	isLoggedIn: function(){
		browserAPI.log("isLoggedIn");
		var form = $('form[name = "LoginForm"]');
		if(form.length > 0){
			browserAPI.log("not LoggedIn");
			return false;
		}
		form = $('form[name = "VerifyForm"]');
		if(form.length > 0){
			browserAPI.log("entering phone");
			return true;
		}
		if($('a[href = "javascript:doSubmit(\'Header2Form\', \'logoutPost\');"]').length > 0){
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
		var name = $('tr.summaryText td:first');
		browserAPI.log("name: " + name);
		return ((typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.Name) != 'undefined')
			&& (account.properties.Name != '')
			&& (name == account.properties.Name));
	},

	logout: function(){
		provider.setNextStep('login');
		$('a[href = "javascript:doSubmit(\'Header2Form\', \'logoutPost\');"]').click();
	},

	login: function(params){
		browserAPI.log("login");
		var form = $('form[name = "LoginForm"]');
		if(form.length > 0){
			browserAPI.log("submitting saved credentials");
			form.find('input[name = "bank_account_num"]').val(params.account.login);
			provider.setNextStep('sendPass');
			form.find('input[type = "button"]').click();
		}
		else {
			provider.setError('Login form not found');
			throw 'Login form not found';
			}
	},

	checkLoginErrors: function(params){
		var errors = $('div#status_msg');
		if(errors.length > 0)
			provider.setError(errors.text());
		else
			provider.sendPass(params);
	},

	sendPass: function(params){
		browserAPI.log("send pass: " + params.account.password);
		var form = $('form[name = "VerifyForm"]');
		if(form.length > 0){
			browserAPI.log("submitting pass");
			form.find('input[name = "phone"]').val(params.account.password);
			provider.setNextStep('checkPassErrors');
			form.find('input#btn_submit').click();
		}
		else
			provider.setError('code 1');
	},

	checkPassErrors: function(params){
		provider.complete();
	}

}
