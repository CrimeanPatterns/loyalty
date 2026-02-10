var plugin = {

    hosts : {
        'www.airitaly.com': true
    },

    getStartingUrl : function(params) {
        return 'https://www.airitaly.com/en/user-profile/update-profile';
    },

    start : function(params) {
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
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn : function() {
        browserAPI.log('isLoggedIn');
        if ($('a[data-target="#loginDialog"]:visible').length) {
            browserAPI.log('isLoggedIn: false');
            return false;
        }
        if ($('a[href*="/logout"]:visible').length) {
            browserAPI.log('isLoggedInd: true');
            return true;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount : function(account) {
        browserAPI.log('isSameAccount');
        if ('undefined' != typeof account.properties && 'undefined' != typeof account.properties.Name) {
            var name = util.trim($('span.brand-primary').text());
            return ('' != name && name.toLowerCase() == account.properties.Name.toLowerCase());
        }
        return false;
    },

    logout : function(params) {
        browserAPI.log('logout');
        provider.setNextStep('login', function() {
            document.location.href = 'https://www.meridiana.it/en/meridiana-club/logout';
        });
    },

    login : function(params) {
        browserAPI.log('login');
        $('a[data-target="#loginDialog"] span:last-child').trigger('click');
        setTimeout(function() {
            var $box = $('div#loginDialog:visible');
            if ($box.length) {
                $('#emailLogin', $box).val(params.account.login);
                $('#pwd', $box).val(params.account.password);
                return setTimeout(function() {
                    //provider.eval("jQuery('a[data-act=\"doLogin\"]').click();");
                    $box.find('a[data-act="doLogin"]').get(0).click();
                    setTimeout(plugin.checkLoginErrors, 1500);
                }, 500);
            }
            provider.setError(util.errorMessages.loginFormNotFound);
        }, 2500);
    },

    checkLoginErrors : function() {
        browserAPI.log('checkLoginErrors');
        var $error = $('div.alert.alert-danger:visible span:last-child');
        if ($error.length && '' != util.trim($error.text())) {
            provider.setError($error.text());
        } else
            plugin.finish();
    },

    finish : function() {
        browserAPI.log('finish');
        provider.complete();
    }

};
