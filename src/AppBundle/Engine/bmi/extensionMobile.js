var plugin = {

    autologin: {

        url: "https://www.diamondclub.org/iloyal-MemberPortal/mportal/loginFlow",

        start: function () {
            browserAPI.log("start");
            var counter = 0;
            var start = setInterval(function () {
                browserAPI.log("waiting... " + start);
                if ($('form[id = "loginModel"]').length > 0 || $('input[value = "Sign Out"]').length > 0) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }
                if (counter > 10) {
                    clearInterval(start);
                    api.error("Can't determine state");
                }
                counter++;
            }, 500);
        },

        start2: function () {
            browserAPI.log("start2");
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
            if ($('input[value = "Sign Out"]').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[id = "loginModel"]').length > 0) {
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
                var form = $('form[id = "loginModel"]');
                browserAPI.log("waiting... " + login);
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "username"]').val(params.login);
                    form.find('input[name = "userpassword"]').val(params.pass);

                    clearInterval(login);

                    api.setNextStep('checkLoginErrors', function () {
                        form.find('input[name = "_eventId_loginAction"]').click();
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
            return (typeof(params.properties) !== 'undefined')
                && (typeof(params.properties.Number) !== 'undefined')
                && ($('span:contains("' + params.properties.Number + '")').length > 0);
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
            api.setNextStep('loadLoginForm', function () {
                document.location.href = 'https://www.diamondclub.org/iloyal-MemberPortal/mportal/logout';
            });
        },

        loadLoginForm: function () {
            browserAPI.log("logout");
            api.setNextStep('start', function () {
                document.location.href = plugin.autologin.url;
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};