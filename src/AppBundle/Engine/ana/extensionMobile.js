var plugin = {

    autologin: {
        url: "https://www.ana.co.jp/asw/sp/sp_login_e.jsp?sptype=iew",

        start: function (){
            if (plugin.autologin.isLoggedIn())
                if (plugin.autologin.isSameAccount())
                    plugin.autologin.finish();
                else
                    plugin.autologin.logout();
            else
                plugin.autologin.login();
        },

        isLoggedIn: function () {
            if($('form[action *= "login_action.jsp"]').length > 0){
                return false;
            }

            if($('.login-box').length > 0){
                return false;
            }

            if($('a[href *= "logout_action"]').length > 0){
                return true;
            }

            if($('.mileageClub').length > 0){
                return true;
            }

            throw "can't determine login state";
        },

        login: function () {
            var form = $('form[action *= "login_action.jsp"]');
            if(form.length == 1){
                form.find('input[name = "custno"]').val(params.login);
                form.find('input[name = "password"]').val(params.pass);
                api.setNextStep('checkLoginErrors', function(){
                    //form.submit();
                    //HTMLFormElement.prototype.submit().call(form.get(0));
                    form.find('input[name = "login"]').click();
                });
            }else{
                api.error("can't find login form");
            }
        },

        isSameAccount: function () {
            var name = $('.mileageClub .name');
            return ((name.length == 1)
                && (typeof(params.account.properties) != 'undefined')
                && (typeof(params.account.properties.Name) != 'undefined')
                && (name.text().trim().indexOf(params.account.properties.Name) !== -1));
        },

        checkLoginErrors: function () {
            var error = $('.error-box');
            if(error.length > 0){
                api.error(error.text().trim());
            }else{
                api.complete();
            }
        },

        toLoginPage: function () {
            api.setNextStep('login', function () {
                document.location.href = plugin.autologin.url;
            });
        },

        logout: function () {
            api.setNextStep('toLoginPage', function (){
                document.location.href = $('a[href *= "logout_action"]').attr('href');
            });
        },

        finish: function (){
            api.complete();
        }
    }
};