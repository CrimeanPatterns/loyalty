var plugin = {

    hosts: {'www.autozonerewards.com': true, 'www.autozone.com': true},

    cashbackLink: '', // Dynamically filled by extension controller
    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        return 'https://www.autozone.com/myzone/profile/login.jsp?redirectURL=%2Fmyzone%2Fprofile%2Flogin.jsp%3FredirectURL%3D%252Flanding%252Fpage.jsp%253Fname%253Dautozone-rewards';
    },

    fromCashback: function (params) {
        browserAPI.log("fromCashback");
        plugin.loadLoginForm(params);
    },

    // for Cashback auto-login
    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        if (plugin.isLoggedIn()) {
            if (plugin.isSameAccount(params.account))
                plugin.loginComplete(params);
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Log Out")').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[name="loginForm"]').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
        throw "Can't determine login state";
    },

    isSameAccount: function (account) {
        return false;
        // var element = $('div.headerdata1').html().replace(/&nbsp;/ig, ' ');
        // var number = util.findRegExp( element, /Member ID #\s*[^>]+>[^>]+>\s*([\d\s]+)/i);
        // browserAPI.log("number: " + number);
        // return ((typeof(account.properties) != 'undefined')
        //     && (typeof(account.properties.MemberID) != 'undefined')
        //     && (account.properties.MemberID != '')
        //     && (number == account.properties.MemberID));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $('a:contains("Log Out")').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const form = $('form[name="loginForm"]');

        if (form.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[name="username"]').val(params.account.login.toUpperCase());
        form.find('input[name="password"]').val(params.account.password);
        provider.setNextStep('checkLoginErrors', function () {
            form.find('[type="submit"]').get(0).click();
            setTimeout(function () {
                plugin.checkLoginErrors(params);
            }, 7000)
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("login");
        const errors = $('#loginError, #sign-in-page-error');
        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    }
}
