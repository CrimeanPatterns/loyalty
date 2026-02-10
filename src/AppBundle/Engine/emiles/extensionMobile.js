var plugin = {
    autologin: {
        url: "https://m.e-miles.com/#sign-on",

        start: function () {
            browserAPI.log("start");
            var start = setInterval(function () {
                if ($('#signonform').length > 0 || $('#icon-sign-off').length > 0) {
                    clearInterval(start);
                    plugin.autologin.start2();
                }
            }, 500);
        },

        start2: function () {
            browserAPI.log("start2");
            if(this.isLoggedIn())
                this.logout();
            else
                this.login();
        },

        isLoggedIn: function () {
            browserAPI.log("isLoggedIn");
            if ($('#signonform').length > 0) {
                browserAPI.log('not logged in');
                return false;
            }
            if ($('#icon-sign-off').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
            browserAPI.log("Can't determine login state");
            api.error("Can't determine login state");
            throw "can't determine login state";
        },

        login: function () {
            browserAPI.log("login");
            var form = $('#signonform');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('#username').val(params.login);
                form.find('#password').val(params.pass);
                api.setNextStep('finish', function () {
                    jQuery('#sign-on-link').click();
                });
            } else {
                browserAPI.log("can't find login form");
                api.error("can't find login form");
            }
        },

        logout: function () {
            browserAPI.log("logout");
            api.setNextStep('login', function () {
                jQuery('#icon-sign-off').click();
            });
        },

        finish: function () {
            browserAPI.log("finish");
            api.complete();
        }
    }
};