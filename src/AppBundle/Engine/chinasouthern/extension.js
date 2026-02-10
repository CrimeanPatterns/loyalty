var plugin = {

    hosts: {'www.csair.com': true, 'b2c.csair.com': true, 'skypearl.csair.com': true},

    getStartingUrl: function (params) {
        return 'https://b2c.csair.com/B2C40/modules/bookingnew/manage/login.html?lang=en';
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
                    plugin.login(params);
            }
            if (isLoggedIn === null && counter > 30) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }
            counter++;
        }, 500);
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if ($('form#memberLogin').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#exitsystem').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = util.beautifulName($('#userinfo').text());
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name == account.properties.Name ));
    },

    logout: function (params) {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function() {
            // IE not working properly
            if (!!navigator.userAgent.match(/Trident\/\d\./)) {
                provider.eval('jQuery.noConflict()');
            }
            $('#exitsystem').get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var memberBox = $('li[data-boxname="memberBox"]');
        if (memberBox.length)
            memberBox.click();
        var form = $('form#memberLogin:visible');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find("input#userId").val(params.account.login);
            form.find("input.passWord").val(params.account.password);
            form.find("#loginProtocol1").prop( "checked", true);

            //provider.setNextStep('checkLoginErrors', function () {
            // IE not working properly
            /*if (!!navigator.userAgent.match(/Trident\/\d\./)) {
                // password will be overwritten somewhere in deferred scripts, set it again
                provider.eval("document.getElementById('password').value = '" + params.account.password + "'; CheckAndLogin();");
            }
            else*/

            provider.setNextStep('checkLoginErrors', function() {
                form.find('#mem_btn_login').get(0).click();
            });
            plugin.checkLoginErrors();
            //});
        }
        else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        setTimeout(function () {
            var errors = $('.lg-msg.error:visible,.help-txt:visible');
            if (errors.length > 0 && util.trim(errors.last().text()) !== '') {
                provider.setError(errors.last().text());
            } else
                provider.complete();
        }, 3000);
    }

};
