var plugin = {
	autologin: {
		url: "https://www.m.ebay.com/signin",

        start: function () {
            api.complete();return;
			if(this.isLoggedIn())
                if(this.isSameAccount())
                    api.complete();
                else
                    this.logout();
            else
                this.login();
        },

        isLoggedIn: function () {
            if($('#loginform').length > 0){
                return false;
            }

            if($('#submitBtn[value = "Sign in"]').length > 0){
                return false;
            }

            if($('a[href *= "logout.cgi"]').length > 0){
                return true;
            }

            throw "can't determine login state";
        },

        login: function () {
            var form = $('#loginform');
            var button = $('#submitBtn');
            if(form.length == 1 && button.length == 1){
                form.find('input[name = "userName"]').val(params.login);
                form.find('input[name = "pass"]').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    api.complete();
                    return;
                    button.click();
                });
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && ($('div:contains("' + params.properties.Number + '")').length > 0)
        },

        checkLoginErrors: function () {
            var error = $('.msg_error');
            if(error.length > 0){
                api.error(error.text().trim());
            } else {
                api.finish();
            }
        },

        logout: function (){
            api.setNextStep('start', function () {
                document.location.href = $('a[href *= "logout.cgi"]').attr('href');
            });
        },

        finish: function () {
            api.complete();
        }
	}
};