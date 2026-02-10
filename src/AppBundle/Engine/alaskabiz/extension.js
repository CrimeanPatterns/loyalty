var plugin = {

    hosts: {'easybiz.alaskaair.com': true, 'www.alaskaair.com': true},

    getStartingUrl: function (params) {
        return 'https://easybiz.alaskaair.com/ssl/coprofile/MyEasyBizActivity.aspx?view=miles';
    },

    start: function (params) {
        browserAPI.log("start");
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
        if ($('#signInForm').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=signout]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        provider.setError(["Can't determine login state", util.errorCodes.providerError]);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = util.findRegExp($('div#FormUserControl__mileagePlanAccountDetail__mileagePlanInfo').text(), /Mileage\s*Plan\s*Number\s*:\s*([\d]+)/i);
        browserAPI.log("number: " + number);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Number) != 'undefined')
            && (account.properties.Number != '')
            && (number == account.properties.Number));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            document.location.href = 'https://easybiz.alaskaair.com/signin?action=signout&lid=ezBizSignOut';
        });
    },

    loadLoginForm: function () {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('login', function () {
            document.location.href = plugin.getStartingUrl();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#signInForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "UserId"]').val(params.account.login);
            form.find('input[name = "Password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find('input[id = "ezbSignIn"]').click();
            });
        }
        else
            provider.setError(['Login form not found', util.errorCodes.providerError]);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $("#errorTextSummaryId");
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
}