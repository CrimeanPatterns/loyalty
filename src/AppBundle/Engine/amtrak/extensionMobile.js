var plugin = {
    autologin: {
        url: "https://amtrakguestrewards.com",

        start: function () {
            browserAPI.log("start");
            api.setNextStep('start2', function () {
                document.location.href = 'https://amtrakguestrewards.com/account';
            });
        },

        start2: function () {
            browserAPI.log("start2");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + start);
                if ($('form[action *= "validate-login"]').length > 0 || $('a:contains("Log Out")').length > 0) {
                    clearInterval(start);
                    plugin.autologin.start3();
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("Can't determine state");
                }
                counter++;
            }, 500);
        },

        start3: function () {
            browserAPI.log("start3");
            if (this.isLoggedIn()) {
                if (this.isSameAccount())
                    this.finish();
                else
                    this.logout();
            }
            else
                this.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('a:contains("Log Out")').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[action *= "validate-login"]').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        login: function () {
            browserAPI.log("login");
            var counter = 0;
            var login = setInterval(function () {
                var form = $('form[action *= "validate-login"]');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "member[uid]"]').val(params.login);
                    form.find('input[name = "member[memberpassword]"]').val(params.pass);

                    clearInterval(login);

                    api.setNextStep('checkLoginErrors', function () {
                        form.submit();
                    });
                }
                if (counter > 10) {
                    clearInterval(login);
                    browserAPI.log("can't find login form");
                    api.error("can't find login form");
                }
                counter++;
            }, 500);
        },

        isSameAccount: function () {
            browserAPI.log("isSameAccount");
            return false;
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && ($('div.profile-details > p:contains("' + params.properties.Number + '")').length > 0);
        },

        checkLoginErrors: function () {
            browserAPI.log("checkLoginErrors");
            var counter = 0;
            var checkLoginErrors = setInterval(function () {
                browserAPI.log("waiting... " + checkLoginErrors);
                var error = $('div.error');
                if (error.length > 0) {
                    clearInterval(checkLoginErrors);
                    api.error(error.text().trim());
                }
                if (counter > 3) {
                    clearInterval(checkLoginErrors);
                    plugin.autologin.finish();
                }
                counter++;
            }, 500);
        },

        logout: function () {
            browserAPI.log("logout");
            if ($('a[href *= "logout"]').length > 0) {
                provider.setNextStep('logout', function(){
                    document.location.href = 'https://www.amtrakguestrewards.com/members/logout';
                });
                return;
            }
            if ($('a[href *= "unrecognize"]').length > 0) {
                provider.setNextStep('logout', function(){
                    document.location.href = 'https://www.amtrakguestrewards.com/members/unrecognize';
                });
                return;
            }
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl();
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};
