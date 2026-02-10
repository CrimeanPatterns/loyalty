
var plugin = {
    // keepTabOpen: true,
    hosts: {'/reservations\\w*.airarabia.com/': true},

    getStartingUrl: function (params) {
        if (!params.account.login2 || params.account.login2 === 'ae-rk')
            params.account.login2 = '';
        return 'https://reservations' + params.account.login2 + '.airarabia.com/ibe/public/showCustomerLoadPage!loadCustomerHomePage.action';
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
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.loadLoginForm(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("function isLoggedIn");
        if ($('#btnSignIn, #formSignIn, #signIn').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('#tdLogout').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("function isSameAccount");
        // browserAPI.log("account: " + JSON.stringify(account));
        // browserAPI.log("account properties: " + JSON.stringify(account.properties));
        var name = util.findRegExp( util.beautifulName( $('#lblTxtName').text() ), /(?:Mr|Mrs|Ms)[.]?\s*(.+)/i );
        browserAPI.log("name: " + name);
        return (
            (typeof(account.properties) !== 'undefined') &&
            (typeof(account.properties.Name) !== 'undefined') &&
            (account.properties.Name !== '') &&
            (name === account.properties.Name)
        );
    },

    logout: function (params) {
        browserAPI.log("function logout");
        provider.setNextStep('beforeStart', function () {
            if (params.account.login2 === 'ae-rk')
                params.account.login2 = '';
            document.location.href = 'https://reservations' + params.account.login2 + '.airarabia.com/ibe/public/customerLogout.action';
        });
    },

    beforeStart: function(params) {
        browserAPI.log("function logout");
        provider.setNextStep('beforeStart2', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    beforeStart2: function(params) {
        browserAPI.log("function logout");
        provider.setNextStep('start', function () {
            if ($('#formSignIn').length)
                $('#signIn').get(0).click();
        });
    },

    loadLoginForm: function(params) {
        browserAPI.log("function logout");
        if ($('#formSignIn').length) {
            provider.setNextStep('login', function () {
                $('#signIn').get(0).click();
            });
        } else plugin.login(params);
    },

    login: function (params) {
        browserAPI.log("function login");
        var form = $('form#frmLogin');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "emailId"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            var pageBeforeClick = document.location.href;
            provider.setNextStep('checkLoginErrors', function () {
                $('#btnSignIn').click();
            });
            setTimeout(function(){
                var signIn = $('#btnSignIn');
                if (signIn.length > 0) {
                    plugin.checkLoginErrors(params);
                }
            }, 2000);
        } else {
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrors: function (params) {
        browserAPI.log("function checkLoginErrors");
        var errors = $('#lblLoginStatus');
        if (errors.length > 0) {
            var errorsText = errors.text().trim();
            if (errorsText) {
                provider.setError(errorsText);
            }
        } else {
            plugin.loginComplete(params);
        }
    },

    loginComplete: function(params) {
        browserAPI.log("function loginComplete");
        provider.complete();
    }

};
