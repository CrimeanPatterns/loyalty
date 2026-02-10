var plugin = {
    autologin: {

        getStartingUrl: function (params) {
            return 'https://portal.discover.com/customersvcs/universalLogin/ac_main?ICMPGN=HDR_LOGN_CC_LOGN';
        },

        start: function (params) {
            browserAPI.log("start");
            var loadingCnt = 0;
            var start = setInterval(function () {
                console.log("waiting... " + loadingCnt);
                if ($('a:contains("Log Out")').length > 0 || $('form#login-form-content').length > 0 || loadingCnt > 15) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }
                loadingCnt++;
            }, 500);
        },

        start2: function () {
            browserAPI.log("start2");
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    provider.complete();
                else
                    this.logout();
            }
            else
                this.login();
        },

        login: function () {
            browserAPI.log("login");
            setTimeout(function () {
                var form = $('form#login-form-content');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "userID"]').focus();
                    form.find('input[name = "userID"]').val(params.login);
                    form.find('input[name = "password"]').val(params.pass);
                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('#log-in-button').click();
                    });
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
            }, 1000)
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            var number = util.findRegExp( $('p:contains("8897")').text(), /Ending\s*([^<]+)/i);
            browserAPI.log("number: " + number);
            return ((typeof(params.properties) != 'undefined')
                && (typeof(params.properties.Number) != 'undefined')
                && (params.properties.Number != '')
                && (number == params.properties.Number));
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('a:contains("Log Out")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form#login-form-content').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            provider.setError(util.errorMessages.unknownLoginState);
            return false;
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var error = $('p.alert-body:visible');
            if (error.length > 0)
                provider.error(util.trim(error.text()));
            else
                provider.complete();
        },

        logout: function () {
            browserAPI.log("logout");
            provider.setNextStep('LoadLoginForm', function () {
                $('a:contains("Log Out")').click();
            });
        },

        LoadLoginForm: function (params) {
            browserAPI.log("LoadLoginForm");
            provider.setNextStep('login', function () {
                window.location.href = plugin.autologin.getStartingUrl(params);
            });
        }

    }
};