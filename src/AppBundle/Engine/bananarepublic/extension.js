var plugin = {

    hosts: {
        '/www\\d+\\.onlinecreditcenter\\d+\\.com/': true,
        'www.synchronycredit.com'                 : true,
        'www.barclaysus.com'                      : true,
        'gap.barclaysus.com'                      : true,
        'oldnavy.barclaysus.com'                  : true,
        'bananarepublic.barclaysus.com'           : true,
    },

    cashbackLink : '', // Dynamically filled by extension controller
    startFromCashback : function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function(params) {
        switch (params.account.login2) {
            case 'GapCard':
                return 'https://gap.barclaysus.com/servicing/authenticate';
                break;
            case 'OldNavy':
                return 'https://oldnavy.barclaysus.com/servicing/authenticate';
                break;
            case 'Athleta':
                return 'https://athleta.barclaysus.com/servicing/authenticate';
                break;
            default:// 'BananaRepublic'
                return 'https://bananarepublic.barclaysus.com/servicing/authenticate';
                break;
        }
    },
    
    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout(params);
        }// if (plugin.isLoggedIn())
        else
            plugin.login(params);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = util.trim( $('li:has(a:contains("Profile"))').prev('li:eq(0)').text() );
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && name !== ''
            && (name === account.properties.Name));
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('form[id = "loginSecureLoginForm"], form[id = "homePageLoginForm"]').length > 0) {
            browserAPI.log("not logged in");
            return false;
        }
        if ($('a[href *= "logout"]').length > 0) {
            browserAPI.log("logged in");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    logout: function(region) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function() {
            $('a[href *= "logout"]:visible').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[id = "loginSecureLoginForm"], form[id = "homePageLoginForm"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[id = "username"]').val(params.account.login);
            form.find('input[id = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('button#loginButton').get(0).click();
                setTimeout(function () {
                    plugin.checkLoginErrors();
                }, 10000)
            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function() {
        browserAPI.log("checkLoginErrors");
        const errors = $('p.error:visible');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }

};