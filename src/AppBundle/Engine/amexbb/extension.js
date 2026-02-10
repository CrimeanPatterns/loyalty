var plugin = {


    hosts: {'secure.bluebird.com': true, 'bluebird.com': true},

    getStartingUrl: function (params) {
        return 'https://secure.bluebird.com/?linknav=us-Prepaid-Bluebird-Home-Login';
    },

    start: function (params) {
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                provider.complete();
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[action *= Login]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= LogOff]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        browserAPI.log("can't determine");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('span.welcome-name').text().replace('!', '');
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function () {
        browserAPI.log("Logout");
        document.location.href = 'https://secure.bluebird.com/User/Login/LogOff';
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[action *= Login]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "UserName"]').val(params.account.login);
            form.find('input[name = "Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('button.btn-primary').click();
        }
        else {
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function () {
        var errors = $('div.message-inner ul');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}