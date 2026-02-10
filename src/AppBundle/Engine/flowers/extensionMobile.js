var plugin = {
    autologin : {
        url : 'https://m.www.1800flowers.com/1-800-flowers-home?flws_rd=1',

        start  : function() {
            browserAPI.log('start');
            if (this.isLoggedIn()) {// endless cycle
                return this.finish();
            }
            if ($('form[data-id="Logon"]').length) {// endless cycle
                this.login();
            } else {
                var $yourAcc = $('>a[href*="MyAccount"]', '#unRegYourAccount');
                api.setNextStep('start2');
                if ($yourAcc.length)
                    document.location.href = $yourAcc.attr('href');
                else
                    document.location.href = 'https://m.www.1800flowers.com/webapp/wcs/stores/servlet/LogonForm?catalogId=13302&langId=-1&storeId=20051&URL=AjaxLogonForm%3FMyAccount%3DY%26catalogId%3D13302%26langId%3D-1%26storeId%3D20051&krypto=CcfbjUZjVpx1VFKJEbKILpxseY%2FHAbwgp35Qmk0U3kooWNOmopNGb7FJQ9k5MXffP891hTzwWFyfdTM%2Fs8oeV6kdzLNi2nUEHOIe5tEAeXc%3D&ddkey=https%3AAjaxLogonForm';
            }
        },
        start2 : function() {
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            } else {
                this.login();
            }
        },

        isLoggedIn : function() {
            browserAPI.log('isLoggedIn');
            if ($('a[href*="/Logoff"]').length || $('h1:contains("My Account")').length || '' != $('#hdrSignInName').text()) {
                browserAPI.log('logged in');
                return true;
            }
            if ($('a[href*="/AjaxLogonForm"]').length || $('#regLogOut').length) {
                browserAPI.log('not logged in');
                return false;
            }
            provider.setError(util.errorMessages.unknownLoginState);
        },

        isSameAccount : function() {
            browserAPI.log('isSameAccount');
            return true;// endless cycle
        },

        login : function() {
            browserAPI.log('login');
            var $form = $('form[data-id="Logon"]');
            if (!$form.length)
                return provider.setError(util.errorMessages.loginFormNotFound);
            $('#logonId').val(params.account.login);
            $('#logonPassword').val(params.account.password);
            $('input[data-id="logonId"]', $form).val(params.account.login);
            $('input[data-id="logonPassword"]', $form).val(params.account.password);
            provider.setNextStep('checkLoginErrors', function() {
                provider.eval("document.getElementById('default').click();");
            });
        },

        logout : function() {
            browserAPI.log('logout');
            provider.setNextStep('start', function() {
                provider.eval('deleteAkamaiCookies();');
                document.location.href = $('a', '#regLogOut').attr('href');
            });
        },

        checkLoginErrors : function() {
            browserAPI.log('checkLoginErrors');
            var $error = $('#__errorMsg');
            if ($error.length && '' != util.trim($error.text())) {
                provider.setError(util.filter($error.text()));
            } else
                this.finish();
        },

        finish : function() {
            browserAPI.log('finish');
            provider.complete();
        }

    }
};
