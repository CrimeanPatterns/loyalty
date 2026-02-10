var plugin = {


    hosts: {'www.deltahotels.com': true},

    getStartingUrl: function (params) {
        return 'https://www.deltahotels.com/member/dashboard';
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
        if ($('form[action = "/user/login"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *= logout]').text()) {
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
        var number = plugin.findRegExp( $('h4:contains("privilege member status:") span').text(), /#\s*([\d\s]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        var UA = window.navigator.userAgent,
            ChromeB = /Chrome\/\w+\.\w+/i,
            Chrome = UA.match(ChromeB);
        if (!Chrome == ""){
            provider.setNextStep('login');
            browserAPI.log(">>> Сhrome");// + Chrome[0]
        }
        else
            provider.setNextStep('loadLoginForm');
        document.location.href = 'https://www.deltahotels.com/member/logout';
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        // bug in safari, ff. maybe cache is guilty
        if ($.browser.safari || $.browser.mozilla)
            provider.setNextStep('reloadPage');
        else
            provider.setNextStep('login');
        document.location.href = plugin.getStartingUrl();
    },

    reloadPage: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login');
        window.location.reload();
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[action = "/user/login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "Login"]').val(params.account.login);
            form.find('input[name = "Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors');
            form.find('input[name = "LoginButton"]').click();
        }
        else
            provider.setError('code 1');
    },

    checkLoginErrors: function () {
        var errors = $("div.login-warning-dialog");
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    },

    findRegExp: function (elem, regExp, required) {
        var matches = regExp.exec( elem );
        if (matches) {
            console.log('matched regexp: ' + regExp);
            result = matches[1];
        }
        else {
            console.log('failed regexp: ' + regExp);
            if (required)
                console.log('regexp not found');
            else
                result = null;
        }
        return util.trim(result);
    }
}