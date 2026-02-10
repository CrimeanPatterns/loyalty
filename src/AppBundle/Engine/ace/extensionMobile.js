var plugin = {
    autologin : {
        url : 'https://m.acehardware.com/checkout/index.jsp?process=login',

        start : function() {
            browserAPI.log('start');
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            } else
                this.login();
        },

        isLoggedIn : function() {
            browserAPI.log("isLoggedIn");
            if ($('a[href*="=logout"]').length) {
                browserAPI.log('logged in');
                return true;
            }
            if ($('a[href*="=login"]').length) {
                browserAPI.log('not logged in');
                return false;
            }
            provider.setError(util.errorMessages.unknownLoginState);
        },

        isSameAccount : function() {
            browserAPI.log('isSameAccount');
            return false;
        },

        login : function() {
            browserAPI.log('login');
            var $form = $('#login');
            if (!$form.length)
                return provider.setError(util.errorMessages.loginFormNotFound);
            $('input[name="email"]', $form).val(params.account.login);
            $('input[name="password"]', $form).val(params.account.password);
            provider.setNextStep('checkLoginErrors', function() {
                $('#returningSignIn').trigger('click');
            });
        },

        logout : function() {
            browserAPI.log('logout');
            provider.setNextStep('start', function() {
                $('a[href*="=logout"]').trigger('click');
            });
        },

        checkLoginErrors : function() {
            browserAPI.log('checkLoginErrors');
            var $error = $('p.error:visible');
            if ($error.length && '' != util.trim($error.text())) {
                var $err = $error.clone().find('a').remove().end();
                provider.setError(util.filter($err.text()));
            } else
                this.finish();
        },

        finish : function() {
            browserAPI.log('finish');
            provider.complete();
        }
    }
};
