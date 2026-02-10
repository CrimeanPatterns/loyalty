var plugin = {


    hosts: {'mainstreetshares.com': true},

    getStartingUrl: function (params) {
        return 'https://mainstreetshares.com/myAccount.do';
    },

    isMobile: function () {
        return (typeof(api) !== 'undefined') && (typeof(api.getDepDate) === 'function') && (api.getDepDate() instanceof Date);
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout(params);
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a[href = "logoff.do"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name = "LoginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        browserAPI.log("Can't determine login state");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        browserAPI.log('isSameAccount');
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = plugin.findRegExp($('h4:contains("Hello,")').text(), /Hello,\s([^\!]+)/i);
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() === account.properties.Name.toLowerCase()) );
    },

    findRegExp:function (elem, regExp, required) {
        var matches = regExp.exec( elem );
        if (matches) {
            console.log('matched regexp: ' + regExp);
            result = util.trim(matches[1]);
        }
        else {
            console.log('failed regexp: ' + regExp);
            if (required)
                console.log('regexp not found');
            else
                result = null;
        }
        return result;
    },

    logout: function () {
        browserAPI.log("logout");
        var logout = 'https://mainstreetshares.com/logoff.do';
        if (plugin.isMobile()) {
            api.setNextStep('loadLoginForm', function () {
                document.location.href = logout;
            });
        } else {
            provider.setNextStep('loadLoginForm');
            document.location.href = logout;
        }
    },

    loadLoginForm: function (params) {
        if (plugin.isMobile()) {
            api.setNextStep('login', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
        } else {
            provider.setNextStep('login');
            document.location.href = plugin.getStartingUrl(params);
        }
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[name = "LoginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "memberName"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            if (plugin.isMobile()) {
                api.setNextStep('checkLoginErrors', function () {
                    form.get(0).submit();
                });
            } else {
                provider.setNextStep('checkLoginErrors');
                form.get(0).submit();
            }
        }
        else {
            browserAPI.log("Login form not found");
            provider.setError('Login form not found');
            throw 'Login form not found';
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.alert-danger');
        if (errors.length > 0 && $('a[href = "logoff.do"]').length == 0)
            provider.setError(errors.text());
        else
            plugin.loginComplete(params);
    },

    loginComplete:function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }

}
