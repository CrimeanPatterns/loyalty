var plugin = {
    autologin : {
        url : 'https://www.basspro.com/shop/AjaxLogonForm?myAcctMain=1&catalogId=3074457345616676768&langId=-1&storeId=715838534',

		start: function (params) {
			browserAPI.log("start");
			var counter = 0;
			var start = setInterval(function () {
				browserAPI.log("waiting... " + counter);
				var isLoggedIn = plugin.autologin.isLoggedIn();
				if (isLoggedIn !== null) {
					clearInterval(start);
					if (isLoggedIn) {
						if (plugin.autologin.isSameAccount(params.account))
							plugin.autologin.finish();
						else
							plugin.autologin.logout(params);
					}
					else
						plugin.autologin.login(params);
				}
				if (isLoggedIn === null && counter > 10) {
					clearInterval(start);
					provider.setError(util.errorMessages.unknownLoginState);
					return;
				}
				counter++;
			}, 500);
		},

        loadLoginForm : function() {
            provider.setNextStep('start', function() {
                document.location.href = plugin.autologin.url;
            });
        },

        login : function() {
            browserAPI.log('login');
            var $form = $('#Logon');
            if ($form.length) {
                $('input[name="logonId"]', $form).focus().val(params.account.login);
                $('input[name="logonPassword"]', $form).focus().val(params.account.password);
                provider.setNextStep('checkLoginErrors', function() {
                    $('#WC_AccountDisplay_links_2', $form).get()[0].click();
					setTimeout(function(){
						plugin.autologin.checkLoginErrors();
					}, 5000);
                });
            }else provider.setError(util.errorMessages.loginFormNotFound);
        },

        isLoggedIn : function() {
            browserAPI.log('isLoggedIn');
            if ($('.header_welcome').length) {
                browserAPI.log('LoggedIn');
                return true;
            }
            if ($('#Logon').length) {
                browserAPI.log('not LoggedIn');
                return false;
            }
            return null;
        },

        isSameAccount : function(account) {
            browserAPI.log('isSameAccount');
            if ('undefined' != typeof account.properties
                && 'undefined' != typeof account.properties.Name
                && '' != account.properties.Name
                && $('p:contains("' + account.properties.Name + '")').length) {
                return true;
            }
            return false;
        },

        logout : function() {
            browserAPI.log('logout');
            provider.setNextStep('loadLoginForm', function() {
                $('#signInOutQuickLink').get()[0].click();
            });
        },

        checkLoginErrors : function() {
            browserAPI.log('checkLoginErrors');
            var $error = $('#ErrorMessageText:visible');
            if ($error.length && '' != util.trim($error.text())) {
                provider.setError($error.text());
            } else
				return provider.setNextStep('finish', function() {
                    document.location.href = plugin.autologin.url;
                });
        },

        finish : function() {
            browserAPI.log('finish');
            provider.complete();
        }

    }
};
