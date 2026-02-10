var plugin = {

    hosts: {'www.drugstore.com': true},
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },

	getStartingUrl: function(params){
		return 'https://www.drugstore.com/user/login.asp';
	},

    /*deprecated*/
    startFromChase: function(params) {
        plugin.start(params);
    },

    /*deprecated*/
    fromCashback: function (params) {
        browserAPI.log("fromCashback");
        plugin.loadLoginForm(params);
    },

    // for Cashback auto-login
    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start');
        document.location.href = plugin.getStartingUrl(params);
    },
	
	start: function(params){
        provider.setNextStep('login');
        document.location.href = 'https://www.drugstore.com/user/login.asp';
        /*
		if(plugin.isLoggedIn())
			plugin.logout();
		else
			plugin.login(params);
			*/
	},

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if( $('form[name = "frmLogin"]').length > 0){
            browserAPI.log("not LoggedIn");
            return false;
        }
        if($('a[href*="/user/logoff.asp"]').length > 0){
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("Can't determine login state");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    logout: function(){
		provider.setNextStep('start');
        document.location.href = 'https://www.drugstore.com/user/login.asp';
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('form[name = "frmLogin"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            $('input#txtEmail').attr("value", params.account.login);
            $('input#txtPassword').attr("value", params.account.password);
			provider.setNextStep('checkLoginErrors');
            $('input#btnContinue').click();
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function(){
        var errors = $('table.standardError');
        if(errors.length > 0)
            provider.setError(errors.text());
		else
			plugin.loginComplete(params);
    },

	loginComplete: function(params){
		if(typeof(params.account.fromPartner) == 'string'){
			setTimeout(provider.close, 1000);
		}
		provider.complete();
	}

}
