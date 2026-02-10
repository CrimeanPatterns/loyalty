var plugin = {

    hosts: {'/\\w+\\.advantage\\.com/': true},
    loginURL: "https://www.advantage.com/login",

    getStartingUrl: function (params) {
        return "https://www.advantage.com/awards/";
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        provider.complete();
                    else
                        plugin.logout(params);
                }
                else
                    provider.setNextStep('login', function() {
                        document.location.href = plugin.loginURL;
                    });
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function() {
            document.location.href = plugin.loginURL;
        });
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a:contains("Log Out")').length > 0 || util.trim($('div:contains("Welcome,") > b:eq(0)').text())) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('form[id = "adv_login"]').length > 0 || $('a:contains("Log In"):visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.trim($('div:contains("Welcome,") > b:eq(0)').text());
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && name
            && (-1 < account.properties.Name.toLowerCase().indexOf(name.toLowerCase())) );
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            $.ajax({
                url: 'https://www.advantage.com/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {'action': 'advLogout'},
                dataType: 'html',
                success: function (data) {
                    document.location.reload();
                }
            });

            // $('#logged_in_ddl').val('logout');
            // $('#logged_in_ddl option:selected').get(0).click();

        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form = $('form[id = "adv_login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "user_name"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function() {
                form.find('button[type = "submit"]').trigger('click');
                setTimeout(function () {
                    plugin.checkLoginErrors(params);
                }, 7000)
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },
    
    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var errors = $('div.aez-error__message span.aez-error__main-text');
        if (errors.length > 0)
            provider.setError(errors.text());
        else
            provider.complete();
    }
};