var plugin = {
    autologin: {
        url: "https://m.choicehotels.com/cpacct?chain=chi",

        start: function() {
            if (this.isLoggedIn())
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            else
                this.login();
        },

        isLoggedIn: function () {
            if($('#logout').length > 0){
                return true;
            }

            if($('#editProfile').length > 0){
                return true;
            }

            if($('img[src *= "icon_signin"]').length > 0){
                return false;
            }

            if($('input[name = "login"]').length > 0){
                return false;
            }

            api.error("Can't determine login state");
        },

        login: function () {
            var button = $('input[name = "login"]');
            if(button.length > 0){
                $('#username').val(params.login);
                $('#password').val(params.pass);
                api.setNextStep('checkErrors', function () {
                    button.click();
                });
            }else{
                api.error("can't determine login state");
            }
        },

        isSameAccount: function () {
            return ((typeof(params.account.properties) != 'undefined')
                && (typeof(params.account.properties.Number) != 'undefined' && params.account.properties.Number != '')
                && ($('.detail:has("' + params.account.properties.Number + '")').length > 0));
        },

        logout: function () {
            api.setNextStep('start', function() {
                $('#logout').get(0).click()
            });
        },

        checkErrors: function () {
            var error = $('.chh-error');
            if(error.length > 0){
                api.error(error.text());
            }else{
                api.complete();
            }
        },

        finish: function (){
            api.complete();
        }

    },
};