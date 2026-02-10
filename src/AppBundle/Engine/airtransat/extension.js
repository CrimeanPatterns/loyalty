var plugin = {

    hosts: {'www.airtransat.ca': true, "airtransat.ca": true, 'reservation.airtransat.com': true},

    getStartingUrl: function (params) {
        return 'https://www.airtransat.ca/MyTransat/MyProfile.aspx';
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
                provider.complete();
            else
                plugin.logout(params.account.login2);
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a#ctl00_ctl00_cphContent_cphMyTransatContent_UserAccountTabsControl_LoginUserControl_btnLogin').length > 0) {
            browserAPI.log("not LoggedIn sign in");
            return false;
        }
        if ($('a#ctl00_ctl00_cphContent_lnkNotThisPerson').length > 0) {
            browserAPI.log("LoggedIn logout found");
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('#ctl00_ctl00_cphContent_subMenu_hylMyTransat').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name == account.properties.Name));
    },

    logout: function (region) {
        browserAPI.log("logout");
        provider.setNextStep('login', function () {
            document.location.href = "javascript:__doPostBack('ctl00$ctl00$cphContent$lnkNotThisPerson','')";
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form#aspnetForm');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#ctl00_ctl00_cphContent_cphMyTransatContent_UserAccountTabsControl_LoginUserControl_tbxAccountNoEmail').val(params.account.login);
            form.find('input#ctl00_ctl00_cphContent_cphMyTransatContent_UserAccountTabsControl_LoginUserControl_tbxNoAccountPwd').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                form.find("span:contains('Login')").click();
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }
        else
            provider.setError(util.errorMessages.unknownLoginState);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        var error = $('span#ctl00_ctl00_cphContent_ucMsgError_lblMsgInfoTxt:visible');
        if (error.length > 0)
            provider.setError(error.text());
        else
            provider.complete();
    }
}
