var plugin = {
	autologin: {
		url: "https://mobile.alitalia.com/",

        start: function () {
            window.alert = function(){};
			if(this.isLoggedIn())
                if(this.isSameAccount())
                    api.complete();
                else
                    this.logout();
            else
                this.login();
        },

        isLoggedIn: function () {
            var form = $('form[action *= "/FrequentFlyer/Login"]');
            var form2 = $('form[action *= "/FrequentFlyer/Logout"]');
            if(form.length > 0){
                return false;
            }
            if(form.length > 0 && form.find('input[type=button][data-role="submit"]').length > 0){
                return false;
            }

            if(form2.length > 0 && form2.find('input[type=button][data-role="submit"]').length > 0){
                return true;
            }

            throw "can't determine login state";
        },

        login: function () {
            var form = $('form[action *= "/FrequentFlyer/Login"]');
            var button = form.find('input[type=button][data-role="submit"]');
            if(form.length == 1 && button.length == 1){
                form.find('input[name=Code]').val(params.login);
                form.find('input[name=Pin]').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    button.click();
                });
            } else {
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            return (typeof(params.login) !== 'undefined')
                && ($('div.green.nome-millemiglia:contains("' + params.login + '")').length > 0)
        },

        checkLoginErrors: function () {
            var error = $('form .alert');
            if(error.length > 0){
                api.error(error.text().trim());
            } else {
                api.complete();
            }
        },

        logout: function (){
            var form = $('form[action *= "/FrequentFlyer/Logout"]');
            api.setNextStep('start', function () {
                form.find('input[type=button][data-role="submit"]').click();
            });
        },

        finish: function () {
            api.complete();
        }
	}
};