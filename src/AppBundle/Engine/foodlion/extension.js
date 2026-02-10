var plugin = {
    hosts: {
        'foodlion.com': true,
        'www.foodlion.com': true,
    },

    getStartingUrl: function (params) {
        return 'https://www.foodlion.com/';
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null && counter > 1) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account)) {
                        plugin.loginComplete(params);
                    } else {
                        plugin.logout(params);
                    }
                } else {
                    plugin.runAngular(params);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null || counter > 10) {
                clearInterval(start);

                const message = $('h4:contains("Site Temporarily Down"):visible');

                if (message.length > 0) {
                    provider.setError([message.text(), util.errorCodes.providerError], true);
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState, true);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    runAngular: function (params) {
        provider.setNextStep('login', function () {
            provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
            browserAPI.log('location: ' + document.location.href);
            document.location.href = plugin.getStartingUrl(params);
            browserAPI.log('location: ' + document.location.href);
        });
    },

    isLoggedIn: function (account) {
        browserAPI.log("isLoggedIn");
        let name = plugin.getElement("#header-account-button").text().trim();
        browserAPI.log("name: " + name);
        if (name && name === "Sign In") {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if (name && account.properties.Name.toLowerCase().indexOf(name.toLowerCase()) !== -1) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = plugin.getElement("#header-account-button").text().trim();
        browserAPI.log("name: " + name);
        return typeof account.properties !== 'undefined'
            && typeof account.properties.Name !== 'undefined'
            && account.properties.Name !== ''
            && name
            && account.properties.Name.toLowerCase().indexOf(name.toLowerCase()) !== -1;
    },

    logout: function () {
        browserAPI.log("logout");
        if ($('.account-menu_nav').length === 0) {
            $('#header-account-button').click();
        }
        provider.setNextStep('loadLoginForm', function () {
            setTimeout(function () {
                $('#nav-account-menu-log-out').click();
            }, 500);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        $("button#header-account-button").click();
        setTimeout(function () {
            browserAPI.log("open login form");
            $("button#nav-sign-in").click();
        }, 500);

        util.waitFor({
            selector: 'button:contains("Sign")',
            success: function () {
                browserAPI.log("click 'Sign In / Create Account'");
                $("button#nav-account-menu-sign-in").click();
                setTimeout(function () {
                    browserAPI.log("set up login form");
                    // vue.js
                    $('input[id = "login-username"]').val(params.account.login);
                    $('input[id = "LoginForm-password-password"], input[id = "current-password"]').val(params.account.password);

                    // vue.js
                    provider.eval(
                        'function createNewEvent(eventName) {' +
                        'var event;' +
                        'if (typeof(Event) === "function") {' +
                        '    event = new Event(eventName);' +
                        '} else {' +
                        '    event = document.createEvent("Event");' +
                        '    event.initEvent(eventName, true, true);' +
                        '}' +
                        'return event;' +
                        '}'+
                        'var email = document.querySelector(\'input[id = "login-username"]\');' +
                        'email.dispatchEvent(createNewEvent(\'input\')); email.dispatchEvent(createNewEvent(\'change\'));' +
                        'var pass = document.querySelector(\'input[id = "LoginForm-password-password"], input[id = "current-password"]\');' +
                        'pass.dispatchEvent(createNewEvent(\'input\')); pass.dispatchEvent(createNewEvent(\'change\'));'
                    );

                    browserAPI.log("click 'SignIn'");
                    provider.setNextStep('checkLoginErrors');
                    setTimeout(function () {
                        $('button#sign-in-button').click();
                        setTimeout(function () {
                            let btnOk = $('button[id = "alert-button_primary-button"]:visible');
                            if (btnOk.length > 0) {
                                btnOk.click();
                            } else {
                                plugin.checkLoginErrors(params);
                            }
                        }, 5000)
                    }, 1000);
                }, 500)
            },
            fail: function () {
                provider.setError(util.errorMessages.loginFormNotFound);
            },
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        let errors = $('p.message-box_message:visible');

        if (errors.length > 0) {
            let message = util.filter(errors.text());
            browserAPI.log("[Error]: " + message);

            if (errors.indexOf('The sign in information you entered does not match our records') !== -1) {
                provider.setError([message, util.errorCodes.invalidPassword], true);
                return;
            }

            provider.complete();
            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        provider.complete();
    },

    getElement: function (element) {
        return $(element).contents().filter(function () {
            return this.nodeType === Node.TEXT_NODE;
        });
    },
};
