var plugin = {
    autologin: {

        getStartingUrl: function (params) {
            return 'https://secure.bluebird.com/?linknav=us-Prepaid-Bluebird-Home-Login';
        },

        start: function (params) {
            api.setNextStep('login');
            if (this.isLoggedIn()) {
                if (this.isSameAccount(params.account))
                    api.complete();
                else
                    this.logout();
            }
            else
                this.login(params);
        },

        login: function (params) {
            console.log("login");
            var form = $('form[action *= Login]');
            if (form.length > 0) {
                $('input[name = "UserName"]').val(params.login);
                $('input[name = "Password"]').val(params.pass);
                api.setNextStep('checkLoginErrors', function () {
                    form.trigger("submit");
                });
            }
            else
                api.error("can't find login form");
        },

        isSameAccount: function (account) {
            console.log("isSameAccount");
            var name = $('span.welcome-name').text().replace('!', '');
            browserAPI.log("name: " + name);
            return ((typeof(account.properties) != 'undefined')
                && (typeof(account.properties.Name) != 'undefined')
                && (account.properties.Name != '')
                && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
        },

        isLoggedIn: function () {
            console.log("isLoggedIn");
            if ($('a[href *= LogOff]').attr('href')) {
                browserAPI.log("LoggedIn");
                return true;
            }
            if ($('form[action *= Login]').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            api.error("can't determine login state");
            return false;
        },

        checkLoginErrors: function () {
            console.log("checkLoginErrors");
            var error = $('div.error');
            if (error.length > 0)
                api.error(error.text());
            else
                api.complete();
        },

        logout: function () {
            console.log("logout");
            window.location.href = 'https://secure.bluebird.com/User/Login/LogOff';
        }

    }
};