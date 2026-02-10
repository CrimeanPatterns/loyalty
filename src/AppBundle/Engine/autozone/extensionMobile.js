var plugin = {
    autologin : {

        getStartingUrl : function() {
            return 'https://m.autozone.com/userLogin/login.page';
        },

        start : function() {
            browserAPI.log('start');
            // refs 13987#note-35
            if ('m.autozone.com' == location.host && 'undefined' == typeof params.__redirect) {
                params.__redirect = true;
                api.setNextStep('start2', function() {
                    provider.eval('switchToFullWebSite();');
                });
            } else if ('m.autozone.com' != location.host && -1 == location.href.indexOf('profile/login')) {
                api.setNextStep('start2', function() {
                    document.location.href = 'https://www.autozone.com/myzone/profile/login.jsp';
                });
            } else
                this.start2();
        },

        start2 : function() {
            if (plugin.autologin.isLoggedIn()) {
                if (plugin.autologin.isSameAccount())
                    plugin.autologin.finish();
                else
                    plugin.autologin.logout();
            } else
                plugin.autologin.login();
        },

        login : function() {
            browserAPI.log('login');
            // mobile site, authentication does not work on the site
            var $form = $('#login-form');
            if ($form.length) {
                $('#username', $form).focus().val(params.account.login);
                $('#password', $form).focus().val(params.account.password);

                return setTimeout(function() {
                    api.setNextStep('checkLoginErrors', function() {
                        $('input[name="submit"]', $form).click();
                    });
                }, 2000);
            }

            // full site
            $form = $('#myZoneLoginForm');
            if ($form.length) {
                $('input[name="username"]', $form).val(params.account.login);
                $('input[name="password"]', $form).val(params.account.password);

                return api.setNextStep('checkLoginErrors', function() {
                    $('input[type="submit"]').click();
                    setTimeout(function() {
                        plugin.checkLoginErrors(params);
                    }, 3000)
                });
            }

            api.setError(util.errorMessages.loginFormNotFound);
        },

        isLoggedIn : function() {
            browserAPI.log('isLoggedIn');
            if ($('a:contains("Log Out"):visible').length) {
                browserAPI.log('LoggedIn');
                return true;
            }
            if ($('a:contains("Log In"):visible, #login-form, #myZoneLoginForm').length) {
                browserAPI.log('not LoggedIn');
                return false;
            }

            provider.setError(util.errorMessages.unknownLoginState);
        },

        isSameAccount : function() {
            browserAPI.log('isSameAccount');
            return false;
        },

        logout : function() {
            browserAPI.log('logout');
            api.setNextStep('start', function() {
                provider.eval("document.profileLogoutForm.submit();");
            });
        },

        checkLoginErrors : function() {
            browserAPI.log('checkLoginErrors');
            var $error = $('#login-form div.errorGroup:visible, #noAccountError');
            if ($error.length && '' != util.trim($error.text())) {
                api.error($error.text());
            } else
                this.finish();
        },

        finish : function() {
            browserAPI.log('finish');
            api.complete();
        }

    }
};
