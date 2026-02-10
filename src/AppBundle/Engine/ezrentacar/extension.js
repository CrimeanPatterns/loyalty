
var plugin = {

    hosts: {
        'www.e-zrentacar.com': true,
        'awards.e-zrentacar.com': true
    },

    getStartingUrl: function() {
        return 'https://www.e-zrentacar.com/profile?';
    },

    getLoginUrl: function() {
        return 'https://www.e-zrentacar.com/login';
    },

    getAwardUrl: function() {
        return 'https://awards.e-zrentacar.com/tracking';
    },

    start: function(params) {
        browserAPI.log('start');
        if (plugin.isLoggedIn(params)) {
            if (plugin.isSameAccount(params))
                provider.setNextStep('loginComplete', function(){
                    document.location.href = plugin.getAwardUrl();
                });
            else
                plugin.logout(params);
        } else {
            provider.setNextStep('login', function(){
                document.location.href = plugin.getLoginUrl();
            });
        }
    },

    isLoggedIn: function(params) {
        browserAPI.log('isLoggedIn');
        setTimeout(function () {
            if ($('a[href *= "login"]').length > 1) {
                browserAPI.log('isLoggedIn = false');
                return false;
            }

            if ($('select#logged_in_ddl').length) {
                browserAPI.log('isLoggedIn = true');
                return true;
            }

            provider.setError(util.errorMessages.unknownLoginState);
        },5000);
    },

    isSameAccount: function(params) {
        browserAPI.log('isSameAccount');
        browserAPI.log('isSameAccount = false');
        return false;
    },

    logout: function(params) {
        browserAPI.log('logout');
        provider.setNextStep('login', function(){
            document.location.href = plugin.getLoginUrl();
        });
    },

    login: function(params) {
        setTimeout(function () {
            browserAPI.log('login');
            var form = $('form#adv_login');
            if (form.length) {
                browserAPI.log('Submitting saved credentials');
                var username = form.find('input#user_name');
                var password = form.find('input#password');

                username.val(params.account.login);
                password.val(params.account.password);
                username.change();

                provider.setNextStep('checkLoginErrors', function () {
                    setTimeout(function () {
                        form.find('button[type = "submit"]').click();
                    }, 2000);
                });
            } else {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        },1000);
    },

    checkLoginErrors: function(params) {
        browserAPI.log('checkLoginErrors');
        var error = $('ul.error-messages').text().trim();
        if (error.length) {
            provider.setError(error);
        } else {
            provider.setNextStep('loginComplete', function(){
                document.location.href = plugin.getAwardUrl();
            });
        }
    },

    loginComplete: function(params) {
        browserAPI.log('loginComplete');
        provider.complete();
    }

};
