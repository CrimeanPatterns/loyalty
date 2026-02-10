var plugin = {
    autologin : {

        url : 'https://www.directv.com/m/acq/#Login?logout=yes&clear=true&refreshGn=true',

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
            browserAPI.log('isLoggedIn');
            if ($('a[href*="/logOut"]').length) {
                browserAPI.log('logged in');
                return true;
            }
            if ($('form.x-paint-monitored').length) {
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
            var $form = $('form.x-form.x-paint-monitored:eq(2)');
            if (!$form.length)
                return provider.setError(util.errorMessages.loginFormNotFound);
            $('input[name="userid"]', $form).val(params.account.login);
            $('input[name="password"]', $form).val(params.account.password);
            provider.setNextStep('checkLoginErrors', function() {
                // it does not always work
                //$('div.sign-in-btn span[id]', $form).click();
                $('div.sign-in-btn', $form).click();
                $('span:contains("Sign In")', $form).click();
                //provider.eval("document.querySelector('div.sign-in-btn').click();");
            });
        },

        logout : function() {
            browserAPI.log('logout');
            provider.setNextStep('start', function() {
                document.location.href = $('a[href*="/logOut"]').attr('href');
            });
        },

        checkLoginErrors : function() {
            browserAPI.log('checkLoginErrors');
            var $error = $('div.alert.warn p:visible');
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
