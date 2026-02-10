var plugin = {


    hosts: {'www.austinreed.com': true},

    getStartingUrl: function(params) {
        return 'http://www.austinreed.com/';
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn(params)) {
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Sign In"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a:contains("Sign Out"):visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("Can't determine login state");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

	isSameAccount: function(account) {
        browserAPI.log("isSameAccount");
		var login = $('div#accountPanelContent div.accDetailsRow:eq(0) span.value');
		return ((typeof(account) != 'undefined')
			&& (typeof(account.login) != 'undefined')
			&& (account.login != '')
			&& (login.length > 0)
			&& (login.text().toLowerCase() == account.login.toLowerCase()));
	},

    logout: function() {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm');
        $('a:contains("Sign Out"):visible').get(0).click();
    },

	loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
		provider.setNextStep('login');
		document.location.href = plugin.getStartingUrl(params);
	},

    login: function(params){
        browserAPI.log("login");
        $('a:contains("Sign In"):visible').get(0).click();
        var counter = 0;
        var login = setInterval(function () {
            var form = $('form[name = "LogonPopUp"]');
            browserAPI.log("waiting... " + login);
            if (form.length > 0) {

                clearInterval(login);

                browserAPI.log("submitting saved credentials");
                form.find('input[name = "logonId"]').val(params.account.login);
                form.find('input[name = "logonPassword"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors');
                form.submit();
            }
            if (counter > 10) {
                clearInterval(login);
                browserAPI.log("Login form not found");
                provider.setError('Login form not found');
                throw 'Login form not found';
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div[class *= "message-error"]');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}