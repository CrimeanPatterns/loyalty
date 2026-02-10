var isAndroid = typeof (applicationPlatform) != "undefined" && applicationPlatform === "android";
var mobileUserAgent = {
    android: 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Mobile Safari/537.36',
    iphone: 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1\n',
    ipad: 'Mozilla/5.0 (iPad; CPU OS 17_6_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1\n'
}
var plugin = {
    // keepTabOpen: true,//todo
    saveScreenshot: true,

    blockImages: true,

    // hideOnStart: true,//todo
    // mobileUserAgent: undefined; // needed to force ios app use standard user agent.
    mobileUserAgent: isAndroid ? mobileUserAgent.android : (navigator.platform === 'iPhone' ? mobileUserAgent.iphone : mobileUserAgent.ipad),
    alwaysSendLogs: true, //todo
    // J.P. Morgan -> 'a[href *= "logoff"]'
    logoutSelector: 'a[href *= "LogOff"], #convo-deck-sign-out:visible, a[href *=logoffbutton], #brand_bar_sign_in_out > button:visible, button:contains("Sign out"):visible',
    alternativeLogoutSelector: '#allAccountsOverview:visible, [sign-in-out-button-text="Sign out"]:visible, span.header-label:contains("Bank accounts"):visible, span.header-label:contains("Accounts"):visible',

    maxHistoryRows: 150,
    maxActivityInfoPage: 5,
    profileId: null,
    enableHistoryLogs: true,

    getFocusTab: function (account, params) {
        return true;
    },

    hosts: {
        'www.chase.com': true,
        'chaseonline.chase.com': true,
        'ultimaterewards.chase.com': true,
        'ultimaterewardsearn.chase.com': true,
        'ultimaterewardspoints.chase.com': true,
        'cards.chase.com': true,
        'jpmorgan.chase.com': true,
        'mfasa.chase.com': true,
        'smws.chase.com': true,
        'm.chase.com': true,
        '/secure\\w+\\.chase\\.com/': true,
        'www.chase.creditviewdashboard.com': true,
        'chaseloyalty.chase.com': true,
        'secure.chase.com': true,
    },

    getStartingUrl: function (params) {
        if (provider.isMobile) {
            return 'https://chaseonline.chase.com/secure/LogOff.aspx';
        }

        return 'https://www.chase.com/';
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        browserAPI.log("[Current URL]: " + document.location.href);

        if (typeof(params.account.afterLogin) == 'string')
            provider.showFader('Please wait, we are logging you in to ' + params.account.nextAccount.providerName +
                               ' via your personal Chase account so that you can earn ' + params.account.price + ' on your purchases.');

        // switch to desktop version
        if (document.location.host === 'm.chase.com') {
            browserAPI.log("switch to desktop version");
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }

        if (
            document.location.href === 'https://www.chase.com/espanol'
            || document.location.href === 'https://www.chase.com/business'
            || document.location.host === 'autopreferred.chase.com'
            || document.location.href.indexOf('https://www.chase.com/personal/credit-cards/mychaseplan-hub') !== -1
        ) {
            browserAPI.log("switch to english version");
            provider.setNextStep('start', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }

        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("start waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
            if (isLoggedIn !== null) {
                clearInterval(start);
                provider.setTimeout(function () {
                    if (isLoggedIn) {
                        if (plugin.isSameAccount(params.account))
                            plugin.loadAccount(params);
                        else
                            plugin.logout();
                    }
                    else
                        plugin.login(params);
                }, 3000)
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                browserAPI.log(">>> save lastPage");
                provider.logBody("lastPage", true);
                // You're being signed out.
                if ($('h3#logoff-header:contains("You\'re being signed out."):visible').length > 0) {
                    plugin.logout();
                    return;
                }

                // todo: strange behavior
                // We don't recognize the computer you're using.
                if (plugin.isTwoFactor()) {
                    browserAPI.log(">>> 2fa, do logout " + counter);
                    plugin.logout();
                    return;
                }

                // todo: not captured
                if ($('h2:contains("It looks like this part of our site isn\'t working right now."):visible').length > 0) {
                    provider.setError(["It looks like this part of our site isn't working right now. Please try again later. Thanks for your patience.", util.errorCodes.providerError], true);
                    return;
                }
                if (plugin.backToMyAccount(params, 'logout')) {
                    return;
                }
                provider.setError(util.errorMessages.unknownLoginState, true);
                return;
            }// if (isLoggedIn === null && counter > 20)
            counter++;
        }, 500);
    },

    isTwoFactor: function () {
        browserAPI.log("isTwoFactor");

        if ($('td.pagetitle:contains("For Your Protection"), h1.header:contains("For Your Protection")').length > 0// old
            || $('iframe#logonbox:visible, iframe#actual-login-iframe:visible').contents().find('h1.header:contains("For Your Protection")').length > 0// old
            || $('iframe#logonbox:visible, iframe#actual-login-iframe:visible').contents().find('h3:contains("Signing in on a new device?"):visible').length > 0
            || $('h3:contains("Signing in on a new device?"):visible').length > 0
            || $('h1:contains("Confirm Your Identity"):visible').length > 0
            || $('h2:contains("We don\'t recognize this device"):visible').length > 0
            || $('iframe#logonbox:visible, iframe#actual-login-iframe:visible').contents().find("p:contains('Please tell us your one-time code, along with your sign in password and choose \"Next.\"'):visible").length > 0
            || $("p:contains('Please tell us your one-time code, along with your sign in password and choose \"Next.\"'):visible").length > 0
            || document.location.href.indexOf('.chase.com/web/auth/#/logon/recognizeUser/instructions') !== -1
            || document.location.href.indexOf('.chase.com#/logon/recognizeUser/simplerAuthOptions') !== -1
        ) {
            browserAPI.log("isTwoFactor: true");

            return true;
        }

        browserAPI.log("isTwoFactor: false");

        return false;
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if (
            $(plugin.logoutSelector).length > 0
            || $('span[class = "header-label"]:contains("Accounts"):visible').length > 0
            || $(plugin.alternativeLogoutSelector).length > 0
        ) {
            browserAPI.log("LoggedIn");
            return true;
        }

        if (
            $('iframe#logonbox:visible, iframe#actual-login-iframe:visible').length > 0
            || $('a[data-src = "https://{pod}/web/auth/dashboard"]:contains("Sign in"):visible, a[href *= "chase.com/web/auth/dashboard"]:contains("Sign "):not(:contains("ut")):visible, a[data-pt-name="unknwnlogin"]:visible').length > 0
            || $('iframe#logonbox:visible, iframe#actual-login-iframe:visible').contents().find('form[id = "login-form"]:visible').length
            || $('form[id = "login-form"]:visible').length
        ) {
            browserAPI.log("not LoggedIn");
            return false;
        }

        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let username = null;
        $.ajax({
            url: "/svc/rr/profile/l4/v1/overview/list",
            async: false,
            type: 'POST',
            xhr: plugin.getXMLHttp,
            contentType: "application/x-www-form-urlencoded",
            beforeSend: function (request) {
                request.setRequestHeader("x-jpmc-csrf-token", 'NONE');
            },
            dataType: 'json',
            success: function (responseProfile) {
                browserAPI.log("---------------- profile data ----------------");
                browserAPI.log(JSON.stringify(responseProfile));
                browserAPI.log("---------------- profile data ----------------");
                username = responseProfile.userId;
            },
            error: function (data) {
                browserAPI.log("fail: isSameAccount");
                data = $(data);
                browserAPI.log("---------------- fail data ----------------");
                browserAPI.log(JSON.stringify(data));
                browserAPI.log("---------------- fail data ----------------");
            }
        });
        browserAPI.log("username: " + username);
        browserAPI.log("parse username: " + account.login);
        return ((typeof(account.properties) != 'undefined')
            && username
            && (account.login !== '')
            && (username.toLowerCase() === account.login.toLowerCase())
        );
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.querySelector("mds-brand-bar").shadowRoot.querySelector("mds-button#brand_bar_sign_in_out").shadowRoot.querySelector("button").click();
            //document.location.href = 'https://chaseonline.chase.com/secure/LogOff.aspx';
        });
    },

    loadLoginForm: function(params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        browserAPI.log("[Current URL]: " + document.location.href);
        let iframe = $('iframe#logonbox:visible, iframe#actual-login-iframe:visible');
        let signIn = $('a[data-src = "https://{pod}/web/auth/dashboard"]:contains("Sign in"):visible, a[href *= "chase.com/web/auth/dashboard"]:contains("Sign "):not(:contains("ut")):visible, a[data-pt-name="unknwnlogin"]:visible');
        browserAPI.log("[signIn length]: " + signIn.length);
        browserAPI.log("[iframe length]: " + iframe.length);
        if (iframe.length === 0 && signIn.length === 0) {
            provider.logBody("loginPage");

            let form = $('form[id = "login-form"]:visible');
            browserAPI.log("[form length]: " + form.length);

            if (form.length > 0) {
                plugin.loadLoginFormTwo(params);
                return;
            }

            provider.setError(util.errorMessages.loginFormNotFound, true);
            return;
        }

        let nextStep = 'loadLoginFormTwoFullPage';
        if (/Safari/.test(JSON.stringify(util.detectBrowser())) && provider.isMobile) {
            nextStep = 'loadLoginFormTwoFullPageSafari';
        }

        if (iframe.length > 0) {
            browserAPI.log("[New Design]: open login form");
            browserAPI.log("[iframe]: " + iframe.attr('src'));
            return provider.setNextStep(nextStep, function () {
                document.location.href = new URL(iframe.attr('src')).origin;
            });
        }// if (iframe.length > 0)

        browserAPI.log("[New Design]: open login form by click 'Sign in'");
        return provider.setNextStep(nextStep, function () {
            provider.eval('$(\'a[data-src = "https://{pod}/web/auth/dashboard"]:contains("Sign in"):visible, a[href *= "chase.com/web/auth/dashboard"]:contains("Sign "):not(:contains("ut")):visible, a[data-pt-name="unknwnlogin"]:visible\').get(0).click()');
            // signIn.get(0).click();
        });
    },

    loadLoginFormTwoFullPageSafari: function(params) {
        browserAPI.log(">>> loadLoginFormTwoFullPageSafari");
        browserAPI.log("[Current URL]: " + document.location.href);
        provider.logBody("loadLoginFormTwoFullPageSafari");
        provider.setTimeout(function () {
            let logout = $(plugin.logoutSelector);
            browserAPI.log("[logout length]: " + logout.length);
            let dashboard = $(plugin.alternativeLogoutSelector);
            browserAPI.log("[dashboard length]: " + dashboard.length);

            if (logout.length > 0 || dashboard.length > 0) {
                browserAPI.log("log out");
                if (plugin.isSameAccount(params.account)) {
                    plugin.checkLoginErrors(params);
                    return;
                }
                provider.setNextStep('start', function () {
                    logout.get(0).click();
                });
                return;
            }// if (logout.length > 0)

            // broken redirect?
            if ($.inArray(document.location.href, [
                    'https://www.chase.com/business',
                    'https://www.chase.com',
                ]) !== -1
                || document.location.href.indexOf('https://apps.apple.com/us/app/chase-mobile-sm/') !== -1
            ) {
                return provider.setNextStep('loadLoginFormTwo', function () {
                    let url = 'https://secure01b.chase.com/web/auth/dashboard#/dashboard/overviewAccounts/overview/index';
                    browserAPI.log("[force redirect]: " + url);
                    document.location.href = url;
                });
            }

            plugin.loadLoginFormTwo(params);
        }, 2000);
    },

    loadLoginFormTwoFullPage: function(params) {
        browserAPI.log(">>> loadLoginFormTwoFullPage");
        browserAPI.log("[Current URL]: " + document.location.href);

        if (!provider.isMobile) {
            browserAPI.log("setIdleTimer: 180");
            provider.setIdleTimer(180);
        }

        let counter = 0;
        let loadLoginFormTwoFullPage = setInterval(function () {
            browserAPI.log("loadLoginFormTwoFullPage waiting... " + counter);
            let iframe = $('iframe#logonbox:visible, iframe#actual-login-iframe:visible');
            const form = iframe.contents().find('form[id = "login-form"]:visible');
            const logoutUniversal = $(plugin.logoutSelector);
            browserAPI.log("[iframe length]: " + iframe.length);
            browserAPI.log("[logoutUniversal length]: " + logoutUniversal.length);

            let dashboard = $(plugin.alternativeLogoutSelector);
            browserAPI.log("[dashboard length]: " + dashboard.length);

            if (
                counter > 5
                && (
                    logoutUniversal.length > 0
                    || dashboard.length > 0
                )
            ) {
                clearInterval(loadLoginFormTwoFullPage);
                return plugin.loadLoginFormTwo(params);
            }

            if (form.length > 0) {
                clearInterval(loadLoginFormTwoFullPage);
                browserAPI.log("[loadLoginFormTwoFullPage]: open login form");
                browserAPI.log("[iframe]: " + iframe.attr('src'));
                return provider.setNextStep('loadLoginFormTwo', function () {
                    let url = new URL(iframe.attr('src'), document.location.origin).href;
                    browserAPI.log("[open iframe by url]: " + url);
                    document.location.href = url;
                });
            }// if (form.length > 0)


            if (
                counter > 100
                && logoutUniversal.length === 0
                && form.length > 0
            ) {
                clearInterval(loadLoginFormTwoFullPage);
                browserAPI.log("[Error]: something went wrong");
                provider.logBody("loadLoginFormTwoFullPage_Error");
            }

            counter++;
        }, 500);
    },

    loadLoginFormTwo: function(params) {
        browserAPI.log(">>> loadLoginFormTwo");
        browserAPI.log("[Current URL]: " + document.location.href);
        let counter = 0;
        let longWaiting = /Safari/.test(JSON.stringify(util.detectBrowser())) || provider.isMobile;
        let loadLoginFormTwo = setInterval(function () {
            browserAPI.log("loadLoginFormTwo waiting... " + counter);
            const logoutUniversal = $(plugin.logoutSelector);
            browserAPI.log("[logout length]: " + logoutUniversal.length);

            let dashboard = $(plugin.alternativeLogoutSelector);
            browserAPI.log("[dashboard length]: " + dashboard.length);

            // todo: strange behavior
            if (plugin.backToMyAccount(params, 'start')) {
                return;
            }

            if (logoutUniversal.length > 0 || dashboard.length > 0) {
                browserAPI.log("log out");
                clearInterval(loadLoginFormTwo);

                if (plugin.isSameAccount(params.account)) {
                    plugin.checkLoginErrors(params);
                    return;
                }

                // refs #22163
                if (logoutUniversal.length === 0) {
                    provider.setNextStep('start', function () {
                        document.querySelector("mds-brand-bar").shadowRoot.querySelector("mds-button#brand_bar_sign_in_out").shadowRoot.querySelector("button").click();
                        //document.location.href = 'https://chaseonline.chase.com/secure/LogOff.aspx';
                    });
                    return;
                }

                provider.setNextStep('start', function () {
                    logoutUniversal.get(0).click();
                });
                return;
            }// if (logout.length > 0)

            let iframe = $('iframe#logonbox:visible, iframe#actual-login-iframe:visible').contents();
            let form = iframe.find('form[id = "login-form"]:visible');
            if (form.length === 0)
                form = $('form[id = "login-form"]:visible');
            if (form.length > 0) {
                clearInterval(loadLoginFormTwo);

                var scope = form.get(0);
                /*
                if (!provider.isMobile) {
                    scope = document;
                } else {
                    scope = form.get(0);
                }
                */
                browserAPI.log("submitting saved credentials");
                scope.querySelector("mds-text-input").shadowRoot.querySelector("input").value = params.account.login;
                scope.querySelector("mds-text-input-secure").shadowRoot.querySelector("input").value = params.account.password;

                /*form.find('input[name = "userId"]').val(params.account.login);
                form.find('input[name = "password"]').get(0).dispatchEvent(new Event('focus', {
                    bubbles: true
                }));
                form.find('input[name = "password"]').val(params.account.password);*/
                // refs #11326
                util.sendEvent(scope.querySelector("mds-text-input").shadowRoot.querySelector("input"), 'input');
                util.sendEvent(scope.querySelector("mds-text-input-secure").shadowRoot.querySelector("input"), 'input');
                form.find('input[name = "rememberMe"]').prop('checked', false);

                // disable auth via rsaToken
                let rsaToken = form.find('input[name = "rsaToken"]');
                if (rsaToken.length === 1 && form.find('input[name = "securityToken"]:visible').length > 0) {
                    rsaToken.get(0).click();
                }

                provider.setNextStep('checkLoginErrors', function () {
                     setTimeout(function() {
                        iframe.find('.mds-bottom-sheet-dialog--cpo').hide()

                         setTimeout(function () {

                            // lastpass gap     // refs #9411, #12201
                           /*
                            form.find('input[name = "userId"]').val(params.account.login);
                            form.find('input[name = "password"]').get(0).dispatchEvent(new Event('focus', {
                                bubbles: true
                            }));
                            form.find('input[name = "password"]').val(params.account.password);
                            */
                            scope.querySelector("mds-text-input").shadowRoot.querySelector("input").value = params.account.login;
                            scope.querySelector("mds-text-input-secure").shadowRoot.querySelector("input").value = params.account.password;
                            util.sendEvent(scope.querySelector("mds-text-input").shadowRoot.querySelector("input"), 'input');
                            util.sendEvent(scope.querySelector("mds-text-input-secure").shadowRoot.querySelector("input"), 'input');
                            browserAPI.log("click 'Sign In'");
                            //form.find("button#signin-button").get(0).click();
                            scope.querySelector("mds-button").shadowRoot.querySelector("button").click();

                            provider.setTimeout(function () {
                                browserAPI.log("setTimeout");
                                if (
                                    longWaiting
                                    || $('h2[id = "inner-logon-error"]:visible').length
                                    || plugin.isTwoFactor()
                                ) {
                                    browserAPI.log("force call checkLoginErrors");
                                    plugin.checkLoginErrors(params);
                                }
                            }, 15000);
                        }, 1000);
                    }, 3000);
                });
            }// if (form.length > 0)
            if (
                (counter > 40 && !longWaiting)
                || (counter > 80 && longWaiting)
            ) {
                clearInterval(loadLoginFormTwo);
                provider.logBody("formPage");

                // Safari bug fix
                if (
                    logoutUniversal.length > 0
                    // update profile
                    || $('div.BODY:contains("This should be your full legal name as it appears on your government ID."):visible').length > 0
                    || document.location.href.indexOf('web/auth/dashboard#/dashboard/overviewAccounts/overview/index') > -1
                    || document.location.href === 'https://www.chase.com/'
                ) {
                    plugin.logout();
                    return;
                }

                // Our site is temporarily unavailable. Please try again later. If you need help right away, please contact us.
                if ($('h1:contains("Our site is temporarily unavailable."):visible').length)
                    provider.setError(["Our site is temporarily unavailable. Please try again later. If you need help right away, please contact us.", util.errorCodes.providerError], true);
                // We'll Be Back Shortly
                if ($('h2:contains("We\'ll Be Back Shortly"):visible').length)
                    provider.setError(["Our site is temporarily unavailable. We'll Be Back Shortly", util.errorCodes.providerError], true);
                else
                    provider.setError(util.errorMessages.loginFormNotFound, true);
            }
            counter++;
        }, 500);
    },

    checkLoginErrors: function(params) {
        browserAPI.log(">>> checkLoginErrors");
        browserAPI.log("[Current URL]: " + document.location.href);

        let providerSetTimeout = 1500;

        if (provider.isMobile) {
            providerSetTimeout = 6500;
        }

        browserAPI.log("[providerSetTimeout]: " + providerSetTimeout);

        provider.setTimeout(function () {
            browserAPI.log("[providerSetTimeout]: " + providerSetTimeout);

            provider.logBody("checkLoginErrorsPage");

            let errors = $('h2[id = "inner-logon-error"]:visible');
            if (errors.length === 0) {
                errors = $('iframe#logonbox:visible, iframe#actual-login-iframe:visible').contents().find('h2[id = "inner-logon-error"]:visible');
            }

            browserAPI.log("[errors]: " + errors.text());

            if (errors.length > 0) {
                let retry = $.cookie("chase.com_aw_retry_" + params.account.login);
                browserAPI.log(">>> retry: " + retry);

                provider.logBody("checkLoginErrorsLastPage");

                /**
                 * We're unable to complete your request. Please reenter your User ID and Password or click "Forgot your User ID/Password?".
                 * You also may want to make sure your computer's Date and Time setting is correct.
                 */
                if (errors.text().indexOf("s Date and Time setting is correct.") !== -1 && ((typeof(retry) === 'undefined' || retry === null) || retry < 2)) {
                    if (typeof(retry) === 'undefined' || retry === null)
                        retry = 0;
                    retry++;
                    $.cookie("chase.com_aw_retry_" + params.account.login, retry, { expires: 0.01, path:'/', domain: '.chase.com', secure: true });
                    provider.setNextStep('loadLoginFormTwo', function () {
                        document.location.href = 'https://chaseonline.chase.com/';
                    });
                }// if (errors.text().indexOf("s Date and Time setting is correct.") !== -1 && (retry == null || retry < 2))
                else {

                    if (provider.isMobile) {
                        provider.command('hide', function () {
                        });
                    }

                    let message = errors.text();
                    if (message.indexOf('Important:') === 0) {
                        message = util.findRegExp(message, /Important:\s*(.+)/ig);
                    }

                    browserAPI.log("[message]: " + message);

                    if (message.indexOf("s Date and Time setting is correct.") !== -1)
                        provider.setError([message, util.errorCodes.providerError], true);
                    else
                        provider.setError(message, true);
                }
                return;
            }// if (errors.length > 0)
            // We don't recognize the computer you're using.
            if (plugin.isTwoFactor()) {
                if (!provider.isMobile) {
                    if (params.autologin)
                        provider.setError(['It seems that Chase needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your Chase authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                    else {
                        provider.setError(['It seems that Chase needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your Chase authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                    }

                    return;
                }

                provider.command('show', function () {
                    provider.showFader('Message from AwardWallet: In order to log in into this account please identify this device and click the “Next” button. Once logged in, sit back and relax, we will do the rest.', true);/*review*/
                    provider.setNextStep('loadAccount', function () {
                        browserAPI.log("waiting answers...");
                        browserAPI.log("[Current URL]: " +  document.location.href);
                        let counter = 0;
                        let waitingAnswers = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            let error = $('h2#inner-alert-the-user:visible');
                            let errorFrame = $('iframe#logonbox:visible, iframe#actual-login-iframe:visible').contents().find('h2#inner-alert-the-user:visible').length;

                            let password = $('#password_input-input-field:visible');
                            if (password.length && password.val() === "") {
                                browserAPI.log("set pass to main page");
                                /*password.get(0).dispatchEvent(new Event('focus', {
                                    bubbles: true
                                }));*/
                                password.val(params.account.password);
                                util.sendEvent(password.get(0), 'click');
                                util.sendEvent(password.get(0), 'focus');
                                util.sendEvent(password.get(0), 'input');
                                util.sendEvent(password.get(0), 'blur');
                                util.sendEvent(password.get(0), 'change');
                            }

                            let passwordFrame = $('iframe#logonbox:visible, iframe#actual-login-iframe:visible').contents().find('#password_input-input-field:visible');
                            if (passwordFrame.length && passwordFrame.val() === "") {
                                browserAPI.log("set pass to iframe page");
                                passwordFrame.get(0).dispatchEvent(new Event('focus', {
                                    bubbles: true
                                }));
                                passwordFrame.val(params.account.password);
                                util.sendEvent(passwordFrame.get(0), 'input');
                            }

                            if (
                                (error.length > 0 && util.filter(error.text()) !== '')
                                || (errorFrame.length > 0 && util.filter(errorFrame.text()) !== '')
                                || counter > 180
                            ) {
                                clearInterval(waitingAnswers);
                                browserAPI.log("[Current URL]: " +  document.location.href);
                                provider.setError(['Message from AwardWallet: In order to log in into this account please identify this device and click the “Next” button. Once logged in, sit back and relax, we will do the rest.', util.errorCodes.providerError], true);
                                return;
                            }// if (error.length > 0 && error.text().trim() != '')
                            if (
                                // new design cards
                                $('div#creditcardGroupaccounts > div[data-attr], div#depositGroupaccounts > div.account-summary-tab, div[id *= "creditCard"] > section > div[data-attr], div#accountTileCollection div.account-tile[id *= "tile-"]:not([id *= "Fav"])').length > 0
                                && (($('div#urPseudoTile').find('div.smalltileamount').length > 0 && $('div#urPseudoTile').find('div.smalltileamount').text() != '--')
                                    || ($('div[id *= "mainAccount"]').find('span.points').length > 0 && $('div[id *= "mainAccount"]').find('span.points').text() != '--'))
                            ) {
                                clearInterval(waitingAnswers);
                                plugin.loadAccount(params);
                            }
                            counter++;
                        }, 500);
                    });
                });
                return;
            }// if ($('td.pagetitle:contains("For Your Protection"), h1.header:contains("For Your Protection")').length > 0)

            errors = $('div.modalContent:visible');
            if (errors.length > 0) {
                let message = errors.text();
                browserAPI.log("[Error]: -> '" + message + "'");

                provider.logBody("checkLoginErrorsLastPage");

                if (provider.isMobile) {
                    provider.command('hide', function () {
                    });
                }

                if (message.indexOf("It looks like this part of our site isn't working right now.") !== -1) {
                    provider.setError([message, util.errorCodes.providerError], true);
                }

                if (message.indexOf("Thanks for visiting chase.com. We'd love to hear what you think of our new site.") === -1) {
                    provider.complete();
                    return;
                }
            }

            /**
             *  A newer browser will help make your chase.com experience even better,
             * and help keep your accounts and personal information secure
             */
            let upgrade = $('h2:contains("You need to upgrade your browser to access your accounts and statements."):visible');
            if (upgrade.length > 0) {
                provider.setError([upgrade.text(), util.errorCodes.providerError], true);
                return;
            }
            // We've sent you mail, but it's been returned to us. Make sure your mailing address is up-to-date by entering it below.
            if ($('p:contains("Please update your mailing address."):visible').length > 0
                && $('a:contains("Action Required"):visible').length > 0) {
                provider.setError(["Please update your mailing address.", util.errorCodes.providerError], true);
                return;
            }
            if (
                $('span:contains("I agree to the Digital Services Agreement"):visible').length > 0
                || $('div#profileMessage:contains("Please tell us your income info to keep your profile up-to-date."):visible').length > 0
                || $('h2#updateEmailAddressHeader:contains("Please update your email address."):visible').length > 0
                || $('h2.enrollmentGettingStartedAdvisory:contains("We need a bit more info to verify your identity."):visible').length > 0
            ) {
                provider.setError(["Chase website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError], true);
                return;
            }
            // You don't have any accounts to show.
            if ($('h2:contains("You don\'t have any accounts to show."):visible').length > 0) {
                provider.setError(["You don't have any accounts to show.", util.errorCodes.providerError], true);
                return;
            }
            // We were unable to process your request. We're sorry. Please try again later. (1604)
            var error = $('span.errorText:has(span#lblUserMessagePrefix:contains("We were unable to process your request.")):visible').parent('td:eq(0)');
            if (error.length > 0) {
                provider.setError([error, util.errorCodes.providerError], true);
                return;
            }
            error = $('h1:contains("It looks like this part of our site isn\'t working right now."):visible');
            if (error.length > 0) {
                provider.setError(["It looks like this part of our site isn't working right now. Please try again later. Thanks for your patience.", util.errorCodes.providerError], true);
                return;
            }
            /* Our site is temporarily unavailable. - chase.com
             * 2000: Please try again later. If you need help right away, please contact us.
             */
            if ($('li:contains("Our site is temporarily unavailable. - chase.com"):visible').length > 0) {
                provider.setError(["Our site is temporarily unavailable. - chase.com. Please try again later. If you need help right away, please contact us.", util.errorCodes.providerError], true);
                return;
            }
            /**
             * It looks like we're having some trouble with our site.
             * Please sign in again. Thanks for your patience.
             *
             * Try again
             */
            if ($('p:contains("It looks like we\'re having some trouble with our site."):visible').length > 0
                && $('div:contains("Please sign in again. Thanks for your patience."):visible').length > 0) {
                provider.setError(["It looks like we're having some trouble with our site. Please sign in again. Thanks for your patience.", util.errorCodes.providerError], true);
                return;
            }
            // We'll Be Back Shortly
            if ($('h2:contains("We\'ll Be Back Shortly"):visible').length) {
                provider.setError(["Our site is temporarily unavailable. We'll Be Back Shortly", util.errorCodes.providerError], true);
                return;
            }

            plugin.loadAccount(params);
        }, providerSetTimeout)
    },

    loadAccount: function (params) {
        browserAPI.log(">>> loadAccount");
        if (params.autologin) {
            browserAPI.log(">>> Only autologin");
            provider.complete();
            return;
        }
        browserAPI.log("Loading account");
        browserAPI.log("[Current URL]: " + document.location.href);

        if (plugin.backToMyAccount(params, 'myTimeout')) {
            return;
        }

        plugin.myTimeout(params);
    },

    backToMyAccount: function (params, step) {
        browserAPI.log(">>> backToMyAccount");
        if (
            document.location.href.indexOf('ultimaterewardspoints.chase.com/') > -1
            || document.location.href.indexOf('https://chaseloyalty.chase.com/home') !== -1
        ) {
            provider.setNextStep(step, function () {
                browserAPI.log("Back to My Account");
                $('div.menu-link:contains("Back to My Account"):eq(0)').get(0).click();
            });
            return true;
        }
        browserAPI.log("link not found");

        return false;
    },

    myTimeout: function (params) {
        browserAPI.log(">>> myTimeout");
        var counter = 0;
        var myTimeout = setInterval(function () {
            browserAPI.log("myTimeout waiting... " + counter);
            if (
                $(plugin.logoutSelector).length > 0
                || $(plugin.alternativeLogoutSelector).length > 0
                // new design cards
                || ($('div#creditcardGroupaccounts > div[data-attr], div#depositGroupaccounts > div.account-summary-tab, div[id *= "creditCard"] > section > div[data-attr], div#accountTileCollection div.account-tile[id *= "tile-"]:not([id *= "Fav"])').length > 0
                    && (($('div#urPseudoTile').find('div.smalltileamount').length > 0 && $('div#urPseudoTile').find('div.smalltileamount').text() != '--')
                        || ($('div[id *= "mainAccount"]').find('span.points').length > 0 && $('div[id *= "mainAccount"]').find('span.points').text() != '--')))
                || counter > 40
            ) {
                clearInterval(myTimeout);
                if (counter > 40) {
                    browserAPI.log(">>> force call method parse");
                    provider.logBody("myTimeoutPage");
                }
                provider.setTimeout(function () {
                    browserAPI.log(">>> delay");
                    plugin.parse(params);
                }, 3000);

                if (plugin.backToMyAccount(params, 'parse')) {
                    return;
                }
            }
            if (
                plugin.isTwoFactor()
                || $('.errortext:visible').parent('td').length === 1
            ) {
                clearInterval(myTimeout);
            }
            counter++;
        }, 500);
    },

    parse: function (params) {
        browserAPI.log('-------------------------------------------------------');
        browserAPI.log(">>> parse");
        browserAPI.log("[Current URL]: " + document.location.href);

        // refs #19361 #note-42
        if (plugin.parseCategories(params)) {
            provider.showFader('Message from AwardWallet: we are currently updating your account; this process may take up to 8 minutes on some accounts. Please don’t close this tab until we are done updating. In some cases, you may see an empty gray screen, which is normal.');
        } else {
            provider.updateAccountMessage();
        }

        var data = {};
        data.properties = {};
        data.properties.SubAccounts = [];
        data.properties.DetectedCards = [];
        data.properties.SouthwestTravelCredits = [];
        // data.links = [];
        // data.additionalInfo = {};
        data.properties.CombineSubAccounts = 'false';
        params.data = data;

        provider.saveTemp(params.data);

        // Maintenance
        if (document.location.href.indexOf('chase_outage.htm') > -1) {
            browserAPI.log(">>> Maintenance");
            var errors = $('strong:contains("We\'ll be back shortly.")');
            if (errors.length > 0) {
                provider.setError(errors.text(), true);
                return;
            }
        }// if (document.location.href.indexOf('chase_outage.htm') > -1)
        // Update your accounts to tell us how you'd like to receive your statements.
        if (
            $('h1:contains("Please update your statement delivery preferences")').length > 0
            || $('span#label-accnt-chkbx-all-doc:contains("Go paperless for all document types below "):visible').length > 0
        ) {
            provider.setError(["Chase website is asking you to update your Paperless Preferences, until you do so we would not be able to retrieve your account information.", util.errorCodes.providerError]);
        }
        let contToAccount = $('button#confirmStep:visible');
        // New design
        if (document.location.href.indexOf('.chase.com/web/accounts/dashboard#') > -1
            || document.location.href.indexOf('.chase.com/web/auth/dashboard#') > -1
            || contToAccount.length
        ) {
            browserAPI.log(">>> New design");

            if (contToAccount.length) {
                browserAPI.log(">>> click 'Go to accounts'");
                contToAccount.click();
            }

            var newDesignCounter = 0;
            var newDesign = setInterval(function () {
                browserAPI.log("cards loading... " + newDesignCounter);
                var loading = $('div#logonDialog:visible, div#spinner:visible');
                if (loading.length === 0 || newDesignCounter > 10) {
                    clearInterval(newDesign);
                    plugin.parseNewDesign(params);
                }// if (loading.length > 0 || newDesignCounter > 10)
                newDesignCounter++;
            }, 500);
            return;
        }
        provider.setTimeout(function () {
            provider.logBody("lastPage", true);
            provider.complete();
        }, 5000);
    },

    parseCategories: function (params) {
        // todo: add check for mobile here
        // if (provider.isMobile) {
        //     browserAPI.log("parseCategories: false");
        //     return false;
        // }
        browserAPI.log("parseCategories: true");

        return true;
    },

    async parseNewDesign(params) {
        browserAPI.log('-------------------------------------------------------');
        browserAPI.log("parseNewDesign");
        $('#awFader').remove();
        provider.logBody("NewDesignPage");

        if (provider.isMobile) {
            provider.command('hide', function () {
            });
        }

        profileId = util.findRegExp( $('script:contains("profileId")').text() , /profileId = (\d+)/);

        if (!profileId) {
            browserAPI.log(">>>> profileId not found");
            // provider.logBody("profileIdLastPage");
            // provider.complete();
            //
            // return false;
        }

        var accountWithoutCards = false;
        var business = false;
        var jpMorgan = false;
        var subAccountBalance = null;
        let goToUltimateRewards = false;
        let goToSouthwestRewards = false;

        let requestDataList;
        try {
            requestDataList = await plugin.fetch('/svc/rl/accounts/secure/v1/dashboard/data/list', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'x-jpmc-csrf-token': 'NONE',
                },
                body: 'context=CBO_DASHBOARD',
            });
            const response = await requestDataList.json();

            browserAPI.log('Get Card data');
            const {cache} = response;

            // browserAPI.log("---------------- Card data ----------------");
            // browserAPI.log(JSON.stringify(cache));
            // browserAPI.log("---------------- Card data ----------------");
            // TODO: parallel
            for (const node in cache) {
                if (cache.hasOwnProperty(node)) {
                    const {url} = cache[node];

                    browserAPI.log(`[URL]: '${url}'`);
                    if (typeof url === 'undefined') {
                        continue;
                    }
                    if (
                        $.inArray(url, [
                            '/svc/rr/accounts/secure/v1/dashboard/overview/accounts/list',
                            '/svc/rr/accounts/secure/v2/dashboard/tiles/list',
                            '/svc/rr/accounts/secure/v3/dashboard/tiles/list',
                            '/svc/rr/accounts/secure/v4/dashboard/tiles/list',
                        ]) !== -1
                    ) {
                        browserAPI.log('Personal account');
                        let version = 2;
                        let accounts;

                        if (
                            $.inArray(url, [
                                '/svc/rr/accounts/secure/v2/dashboard/tiles/list',
                                '/svc/rr/accounts/secure/v1/dashboard/overview/accounts/list',
                            ]) !== -1
                        ) {
                            accounts = cache[node].response.accounts;
                        } else {
                            accounts = cache[node].response.accountTiles;
                            version = 3;
                        }
                        // browserAPI.log("---------------- Card info ----------------");
                        // browserAPI.log(JSON.stringify(accounts));
                        // browserAPI.log("---------------- Card info ----------------");
                        subAccountBalance = await parseCardDetails(accounts, version, accountWithoutCards);
                        // You don't have any accounts to show.
                        if (accounts.length === 0) {
                            provider.setError(["You don't have any accounts to show.", util.errorCodes.providerError], true);
                        }
                        break;
                    }

                    // Business account
                    if (
                        url == '/svc/rl/accounts/secure/v1/user/metadata/list' &&
                        typeof cache[node].response.defaultLandingPage !== 'undefined' &&
                        cache[node].response.defaultLandingPage == 'BUSINESS_OVERVIEW'
                    ) {
                        business = true;
                    }
                    // J.P. Morgan account
                    if (
                        $url == '/svc/rl/accounts/secure/v1/user/metadata/list' &&
                        typeof cache[node].response.defaultLandingPage !== 'undefined' &&
                        $.inArray(cache[node].response.defaultLandingPage, ['GWM_OVERVIEW', 'ACCOUNTS']) !== -1
                    ) {
                        jpMorgan = true;
                    }
                }

                // Business account
                if (business) {
                    try {
                        browserAPI.log('-------------------- Business account --------------------');
                        const requestBusinessList = await plugin.fetch('/svc/rr/accounts/secure/v4/dashboard/tiles/list', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'x-jpmc-csrf-token': 'NONE',
                            }
                        });
                        const responseBusiness = await requestBusinessList.json();
                        const version = 3;
                        const accounts = responseBusiness.accountTiles;

                        subAccountBalance = await parseCardDetails(accounts, version, accountWithoutCards);
                        // You don't have any accounts to show.
                        if (accounts.length === 0) {
                            provider.setError(["You don't have any accounts to show.", util.errorCodes.providerError], true);
                        }
                    } catch (error) {

                    }
                } // if (!isset($this->Properties['SubAccounts']) && $business)
                // J.P. Morgan account
                if (jpMorgan) {
                    try {
                        browserAPI.log('-------------------- J.P. Morgan account --------------------');
                        const jpMorganRequest = await plugin.fetch('/svc/rr/accounts/secure/v2/portfolio/account/options/list', {
                            method: 'POST',
                            body: 'filterOption=ALL',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'x-jpmc-csrf-token': 'NONE',
                            }
                        });
                        const responseMorgan = await jpMorganRequest.json();
                        const version = 4;
                        const {accounts} = responseMorgan;

                        // Access Agreement (AccountID: 4281042)
                        if (
                            accounts.length == 0 &&
                            typeof responseMorgan.statusCode !== 'undefined' &&
                            responseMorgan.statusCode == 'INVESTMENT_LA_ACCEPTANCE_REQUIRED'
                        ) {
                            provider.setError(
                                [
                                    'Chase (Ultimate Rewards) website is asking you to accept their new Terms and Conditions, until you do so we would not be able to retrieve your account information.',
                                    util.errorCodes.providerError,
                                ],
                                true,
                            );
                        }
                        subAccountBalance = await parseCardDetails(accounts, version, accountWithoutCards);
                    } catch (error) {

                    }
                } // if (!isset($this->Properties['SubAccounts']) && $business)
            } // for (let node in cache)

            const countSubAccounts = params.data.properties.SubAccounts.length;

            browserAPI.log(`count subAccounts: ${countSubAccounts}`);
            const countDetectedCards = params.data.properties.DetectedCards.length;

            browserAPI.log(`count DetectedCards: ${countDetectedCards}`);

            // Chase UR Total   refs #6276
            if (params.data.properties.SubAccounts.length) {
                browserAPI.log('[Current Url]: ' + document.location.href);
                browserAPI.log('get Total UR Balance');
                try {
                    const requestURTotal = await plugin.fetch('/svc/rr/accounts/secure/v2/dashboard/tile/ur/detail/list', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'x-jpmc-csrf-token': 'NONE',
                        }
                    });
                    const responseURTotal = await requestURTotal.json();
                    browserAPI.log("---------------- UR Balance data ----------------");
                    browserAPI.log(JSON.stringify(responseURTotal));
                    browserAPI.log("---------------- UR Balance data ----------------");
                    let urTotal = null;

                    if (typeof responseURTotal.urSummary !== 'undefined' && typeof responseURTotal.urSummary.balance !== 'undefined') {
                        urTotal = responseURTotal.urSummary.balance;
                        urTotal = number_format(urTotal, 0, '.', ',');
                    }
                    browserAPI.log(`Chase UR Total: ${urTotal}`);

                    subAccountBalance = number_format(subAccountBalance, 0, '.', ',');
                    browserAPI.log(`Summary of subAccounts: ${subAccountBalance}`);
                    if (urTotal != null && urTotal === subAccountBalance) {
                        params.data.properties.Balance = urTotal;
                        const countSubAccounts = params.data.properties.SubAccounts.length;

                        browserAPI.log(`count subAccounts: ${countSubAccounts}`);
                        // Don't show a single sub-account // refs #6830
                        // refs #16147
                        for (const card in params.data.properties.SubAccounts) {
                            if (
                                params.data.properties.SubAccounts.hasOwnProperty(card)
                                && !util.stristr(params.data.properties.SubAccounts[card].DisplayName, 'Amazon')
                                && !util.stristr(params.data.properties.SubAccounts[card].DisplayName, 'Disney')
                            ) {
                                params.data.properties.SubAccounts[card].BalanceInTotalSum = true;
                            }
                        } // for (let card in params.account.properties.SubAccounts)
                    } // if (urTotal != null && urTotal === subAccountBalance)
                    else {
                        browserAPI.log('set Balance NA');
                        params.data.properties.Balance = 'null';
                    }
                } catch (error) {
                    if (error) {
                        browserAPI.log('fail: Total UR Balance');
                        browserAPI.log('---------------- fail data ----------------');
                        browserAPI.log(JSON.stringify(error));
                        browserAPI.log('---------------- fail data ----------------');

                        // refs #21616
                        subAccountBalance = number_format(subAccountBalance, 0, '.', ',');
                        browserAPI.log(`Summary of subAccounts: ${subAccountBalance}`);
                        params.data.properties.Balance = subAccountBalance;
                        const countSubAccounts = params.data.properties.SubAccounts.length;
                        browserAPI.log(`count subAccounts: ${countSubAccounts}`);
                        // refs #16147
                        for (const card in params.data.properties.SubAccounts) {
                            if (
                                params.data.properties.SubAccounts.hasOwnProperty(card)
                                && !util.stristr(params.data.properties.SubAccounts[card].DisplayName, 'Amazon')
                                && !util.stristr(params.data.properties.SubAccounts[card].DisplayName, 'Disney')
                            ) {
                                params.data.properties.SubAccounts[card].BalanceInTotalSum = true;
                            }
                        } // for (let card in params.account.properties.SubAccounts)
                    }
                }
            } // if (params.data.properties.SubAccounts.length)

            // Name

            try {
                browserAPI.log('>>> Name');
                $.ajax({
                    url: "/svc/rr/profile/l4/v1/overview/list",
                    async: false,
                    type: 'POST',
                    xhr: plugin.getXMLHttp,
                    contentType: "application/x-www-form-urlencoded",
                    beforeSend: function (request) {
                        request.setRequestHeader("x-jpmc-csrf-token", 'NONE');
                    },
                    dataType: 'json',
                    success: function (responseProfile) {
                        browserAPI.log("---------------- profile data ----------------");
                        browserAPI.log(JSON.stringify(responseProfile));
                        browserAPI.log("---------------- profile data ----------------");
                        params.data.properties.Name = util.beautifulName(responseProfile.fullName);
                        browserAPI.log(`Name: ${params.data.properties.Name}`);
                    },
                    error: function (data) {
                        browserAPI.log("fail: isSameAccount");
                        data = $(data);
                        browserAPI.log("---------------- fail data ----------------");
                        browserAPI.log(JSON.stringify(data));
                        browserAPI.log("---------------- fail data ----------------");
                    }
                });
                /*
                const requestProfile = await plugin.fetch('/svc/rr/profile/l4/v1/overview/list', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'x-jpmc-csrf-token': 'NONE',
                    }
                });
                const responseProfile = await requestProfile.json();
                browserAPI.log("---------------- profile data ----------------");
                browserAPI.log(JSON.stringify(responseProfile));
                browserAPI.log("---------------- profile data ----------------");
                params.data.properties.Name = util.beautifulName(responseProfile.fullName);
                browserAPI.log(`Name: ${params.data.properties.Name}`);
                */
            } catch (error) {

            }

            // refs #20165
            try {
                browserAPI.log('>>> Rewards balances');
                browserAPI.log('[Current Url]: ' + document.location.href);
                const requestRewards = await plugin.fetch('/svc/rr/accounts/secure/card/rewards/v1/summary/list', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'x-jpmc-csrf-token': 'NONE',
                    }
                });
                const responseRewards = await requestRewards.json();
                browserAPI.log('Rewards');
                browserAPI.log('---------------- data ----------------');
                browserAPI.log(JSON.stringify(responseRewards));
                browserAPI.log('---------------- data ----------------');

                if (
                    typeof (responseRewards.statusCode) != 'undefined'
                    && responseRewards.statusCode === 'UNAUTHORIZED'
                ) {
                    // retries
                    browserAPI.log(">>> retry");
                    browserAPI.log("[Current URL]: " + document.location.href);
                    let retry = $.cookie("chase.com_aw_retry_" + params.account.login);
                    provider.logBody("startPage-" + retry);
                    if ((retry === null || typeof(retry) === 'undefined') || retry < 3) {
                        if (retry === null || typeof(retry) === 'undefined')
                            retry = 0;
                        provider.logBody("lastPage-" + retry, true);
                        browserAPI.log(">>> Retry: " + retry);
                        retry++;
                        $.cookie("chase.com_aw_retry_" + params.account.login, retry, { expires: 0.01, path:'/', domain: '.chase.com', secure: true });
                        provider.setNextStep('start', function () {
                            document.location.href = plugin.getStartingUrl(params);
                        });
                        return;
                    }// if (retry == null || retry < 3)
                }

                if (typeof (responseRewards.cardRewardsSummary) != 'undefined') {
                    for (const cardRewardsSummary in responseRewards.cardRewardsSummary) {
                        if (
                            !responseRewards.cardRewardsSummary.hasOwnProperty(cardRewardsSummary)
                            || responseRewards.cardRewardsSummary[cardRewardsSummary].cardRewardType !== 'PARTNER_REWARDS'
                        ) {
                            continue;
                        }

                        let cardType = responseRewards.cardRewardsSummary[cardRewardsSummary].cardType;
                        let name = null;
                        let subAccount = null;

                        if (typeof (params.data.properties.Name) != 'undefined') {
                            name = params.data.properties.Name;
                        }

                        switch (cardType) {
                            case 'SOUTHWEST_AIRLINES':
                            case 'SOUTHWEST_PREMIER':
                                browserAPI.log('ignore cardType: ' + cardType);
                                // refs #20925
                                /*
                                subAccount = {
                                    "ProviderUserName": name,
                                    "ProviderCode"    : 'rapidrewards',
                                    "Code"            : 'chasePartnerRewardsRapidrewards',
                                    "DisplayName"     : "Rapid Rewards® Points",
                                    "Balance"         : responseRewards.cardRewardsSummary[cardRewardsSummary].currentRewardsBalance,
                                };

                                params.data.properties.SubAccounts.push(subAccount);
                                */

                                break;
                            case 'UNITED':
                            case 'UNITED_MILEAGE_PLUS_MIDDLE':
                            case 'UNITED_MILEAGEPLUS_CLUB':
                            case 'UNITED_MILEAGEPLUS_EXPLORER':
                            case 'UNITED_MILEAGEPLUS_PRESIDENTIAL_PLUS':
                                subAccount = {
                                    "ProviderUserName": name,
                                    "ProviderCode"    : 'mileageplus',
                                    "Code"            : 'chasePartnerRewardsMileageplus',
                                    "DisplayName"     : "MileagePlus® Miles",
                                    "Balance"         : responseRewards.cardRewardsSummary[cardRewardsSummary].currentRewardsBalance,
                                };

                                params.data.properties.SubAccounts.push(subAccount);

                                break;
                            case 'AEROPLAN_CARD':
                            case 'HYATT':
                            case 'HYATT_HOTELS':
                            case 'HYATT_BUSINESS':
                            case 'MARRIOTT':
                            case 'MARRIOTT_REWARDS_PREMIER':
                            case 'MARRIOTT_BONSAI':
                            case 'RITZ_CARLTON':
                            case 'BRITISH_AIRWAYS':
                            case 'INTERCONTINENTAL_HOTELS_GROUP':
                            case 'STARBUCKS':
                            case 'AER_LINGUS_AVIOS':
                            case 'IBERIA_AVIOS':
                                browserAPI.log('ignore cardType: ' + cardType);

                                break;
                            default:
                                browserAPI.log('unknown cardType: ' + cardType);
                        }// switch (cardType)
                    }// for (const cardRewardsSummary in responseRewards.cardRewardsSummary)
                }// if (typeof (responseRewards.cardRewardsSummary) != 'undefined')
            } catch (error) {
                if (error) {
                    browserAPI.log('fail: Rewards balances');
                    browserAPI.log('---------------- fail data ----------------');
                    browserAPI.log(JSON.stringify(error));
                    browserAPI.log('---------------- fail data ----------------');
                }
            }

            try {
                browserAPI.log('>>> Zip Code');
                const requestZip = await plugin.fetch('/svc/rr/profile/secure/v1/address/profile/list', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'x-jpmc-csrf-token': 'NONE',
                    }
                });
                const responseZip = await requestZip.json();
                params.data.properties.Name = util.beautifulName(responseProfile.fullName);
                browserAPI.log(`Name: ${params.data.properties.Name}`);

                if (typeof responseZip.primaryAddress === 'undefined') {
                    throw false;
                }
                if (typeof responseZip.primaryAddress.zipcode === 'undefined') {
                    throw false;
                }
                const zip = responseZip.primaryAddress.zipcode;
                let zipCode = zip;

                if (zip.length === 9) {
                    zipCode = `${zip.substr(0, 5)} ${zip.substr(5)}`;
                }
                browserAPI.log(`ZipCode: ${zipCode}`);
                params.data.properties.ZipCode = zipCode;
                params.data.properties.ParsedAddress = `${responseZip.primaryAddress.line1}, ${responseZip.primaryAddress.city}, ${responseZip.primaryAddress.stateCode}, ${zipCode}, ${responseZip.primaryAddress.countryCode}`;
            } catch (e) {

            }


            // No cards with balance
            if (
                typeof params.data.properties.DetectedCards !== 'undefined' &&
                typeof params.data.properties.Balance === 'undefined' &&
                ((typeof params.data.properties.Name !== 'undefined' && params.data.properties.DetectedCards.length > 0) || accountWithoutCards)
            ) {
                browserAPI.log('No cards with balance');
                browserAPI.log('set Balance NA');
                params.data.properties.Balance = 'null';
            }

            try {
                browserAPI.log('[Current Url]: ' + document.location.href);
                const requestFICO = await plugin.fetch('/svc/wr/profile/secure/creditscore/v2/credit-journey/servicing/inquiry-maintenance/v2/customers/credit-score-outlines', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'x-jpmc-csrf-token': 'NONE',
                    }
                });

                const responseFICO = await requestFICO.json();

                browserAPI.log('---------------- FICO VantageScore® 3.0 (Experian) ----------------');
                // console.log(responseFICO);
                browserAPI.log(JSON.stringify(responseFICO));
                browserAPI.log('---------------- FICO VantageScore® 3.0 (Experian) ----------------');
                // VantageScore® 3.0 (Experian)
                if (
                    typeof responseFICO.creditBureauName !== 'undefined' &&
                    typeof responseFICO.creditScore !== 'undefined' &&
                    typeof responseFICO.creditScore.currentCreditScoreSummary !== 'undefined' &&
                    typeof responseFICO.creditScore.currentCreditScoreSummary.creditRiskScore !== 'undefined'
                ) {
                    const creditBureauName = util.beautifulName(responseFICO.creditBureauName);
                    const {riskModelName} = responseFICO.creditScoreModelIdentifier;
                    const {riskModelVersionNumber} = responseFICO.creditScoreModelIdentifier;

                    browserAPI.log('Adding FICO...');
                    browserAPI.log('>>> VantageScore® 3.0 (Experian)');
                    const fico = {
                        Code: 'chaseFICO',
                        DisplayName: `${riskModelName} ${riskModelVersionNumber} (${creditBureauName})`,
                        Balance: responseFICO.creditScore.currentCreditScoreSummary.creditRiskScore,
                        // As of
                        FICOScoreUpdatedOn: responseFICO.updateDate.replace(/(\d{4})(\d{2})(\d{2})/, '$2/$3/$1'),
                    };

                    browserAPI.log(JSON.stringify(fico));
                    params.data.properties.SubAccounts.push(fico);
                    params.data.FICO = true;
                }
            } catch (error) {
                browserAPI.log('>>> FICO VantageScore® 3.0 (Experian)');
                browserAPI.log('---------------- fail data ----------------');
                browserAPI.log(JSON.stringify(error));
                browserAPI.log('---------------- fail data ----------------');
            }

            // console.log(params.data);
            provider.saveTemp(params.data);

            if (goToUltimateRewards) {
                return provider.setNextStep('parseUltimateRewards', function () {
                    document.location.href = params.data.linkUR;
                });
            }
            if (goToSouthwestRewards) {
                if (params.data.linkSouthwestTravelCredits.length) {
                    return provider.setNextStep('parseSouthwestTravelCredit', function () {
                        document.location.href = params.data.linkSouthwestTravelCredits;
                    });
                }
            }

            plugin.beforeFICOPage(params);
        } catch (e) {
            browserAPI.log('fail: Get Card data');
            // error = $(error);
            browserAPI.log('status ' + requestDataList.status);
            browserAPI.log('---------------- fail data ----------------');
            browserAPI.log(JSON.stringify(requestDataList));
            browserAPI.log('---------------- fail data ----------------');
        }

        async function parseCardDetails(accounts, version, accountWithoutCards) {
            browserAPI.log(">>> parseCardDetails");
            browserAPI.log("[Version]: " + version);
            // Chase UR Total   refs #6276
            subAccountBalance = 0;

            const delay = (ms = 3000) => {
                browserAPI.log('---------------- set delay: ' + ms + ' ----------------');
                return new Promise(r => setTimeout(r, ms));
            }

            // foreach ($accounts as $account) {
            for (let account in accounts) {
                if (!accounts.hasOwnProperty(account)) {
                    browserAPI.log("skip bad card #" + account);
                    continue;
                }
                // console.log(accounts[account]);
                let accountId = null;
                if (typeof (accounts[account].accountId) != 'undefined') {
                    accountId = accounts[account].accountId;
                } else if (typeof (accounts[account].id) != 'undefined') {
                    accountId = accounts[account].id;
                }
                browserAPI.log("[accountId]: " + accountId);
                let cardType = accounts[account].cardType;
                let nickname = accounts[account].nickname;
                // browserAPI.log("[mask]: " + accounts[account].mask);
                let code = util.findRegExp( accounts[account].mask, /x?(\d+)/);
                browserAPI.log("[code]: " + code);
                let unavailable = accounts[account].unavailable;
                let summaryType = '';
                let accountTileDetailType = '';
                let closed = false;
                let summary = [];
                if (version === 2) {
                    // summaryType
                    if (typeof (accounts[account].summaryType) != 'undefined') {
                        summaryType = accounts[account].summaryType;
                    } else if (typeof (accounts[account].groupType) != 'undefined') {
                        summaryType = accounts[account].groupType;
                    }
                    // summary
                    if (typeof (accounts[account].summary) != 'undefined') {
                        summary = accounts[account].summary;
                    } else if (typeof (accounts[account].detail) != 'undefined') {
                        summary = accounts[account].detail;
                    }
                    // accountTileDetailType
                    if (typeof (accounts[account].accountTileDetailType) != 'undefined') {
                        accountTileDetailType = accounts[account].accountTileDetailType;
                    } else if (typeof (accounts[account].detailType) != 'undefined') {
                        accountTileDetailType = accounts[account].detailType;
                    }
                    closed = summary.closed;
                }
                else if (version === 4) {
                    summaryType = accounts[account].accountCategoryType;
                    accountTileDetailType = accounts[account].detailType;
                    // todo: fake
                    summary = account.summary;
                    closed = summary.closed;
                }
                else {
                    summaryType = accounts[account].accountTileType;
                    accountTileDetailType = accounts[account].accountTileDetailType;
                    summary = accounts[account].tileDetail;
                    closed = summary.closed;
                }
                let availableBalance = summary.availableBalance;
                let currentBalance = summary.currentBalance;

                browserAPI.log("-------------------- Business account --------------------");
                browserAPI.log("card # " + code + " / " + summaryType + " - " + cardType +  " / " + accountTileDetailType);
                if ($.inArray(summaryType, [
                    'UKN',
                    'AUTOLEASE',
                    'MORTGAGE',
                    'AUTOLOAN',
                    'LOAN',
                ]) !== -1
                ) {
                    browserAPI.log("Skip card # " + code + ", this is not credit card");
                    accountWithoutCards = true;
                    continue;
                }

                let type = '';
                switch (summaryType) {
                    case 'CARD':
                        type = 'Personal ';
                        break;
                    case 'DDA':
                        type = '';
                        break;
                    default:
                        type = 'Credit ';
                }// switch ($cardType)
                switch (accountTileDetailType) {
                    case 'BCC':
                        type = 'Business ';
                        break;
                    default:
                        browserAPI.log("Unknown type -> " + accountTileDetailType);
                }// switch ($accountTileDetailType)
                let cardDescription = 'Does not earn points';
                let skip;
                let kind;
                [skip, kind, cardDescription] = plugin.getCardType(cardType, kind, cardDescription, type);
                // Co-branded card
                let coBrandedCard = false;

                let displayName = "..." + code + " (" + type +"Card)";
                browserAPI.log("displayName -> " + displayName);
                if (typeof (kind) != 'undefined') {
                    displayName = kind + " " + displayName;
                }
                browserAPI.log("displayName -> " + displayName);

                if (nickname === 'TOTAL CHECKING' || !cardType) {
                    displayName = nickname + " ..." + code;
                    browserAPI.log("displayName -> " + displayName);
                }// if ($nickname == 'TOTAL CHECKING')

                if (closed) {
                    cardDescription = 'Closed';
                }

                if (displayName && code) {
                    browserAPI.log("fixed card code: " + code);
                    if (displayName.toLowerCase().indexOf( 'Sapphire Preferred'.toLowerCase() ) !== -1) {
                        code = 'SP' + code;
                    }
                    else if (displayName.toLowerCase().indexOf( 'Freedom'.toLowerCase() ) !== -1) {
                        code = 'Freedom' + code;
                    }
                    else if (displayName.toLowerCase().indexOf( 'Ink Unlimited'.toLowerCase() ) !== -1) {
                        code = 'InkUnlimited' + code;
                    }
                    else if (displayName.toLowerCase().indexOf( 'Ink Cash'.toLowerCase() ) !== -1) {
                        code = 'InkCash' + code;
                    }
                    browserAPI.log("new code: " + code);

                    params.data.properties.DetectedCards = plugin.addDetectedCard(params.data.properties.DetectedCards, [{
                        Code: 'chase' + code,
                        DisplayName: displayName,
                        CardDescription: cardDescription
                    }]);
                }// if (displayName) && code)

                if (!accountId || unavailable || nickname === 'TOTAL CHECKING' || closed || skip
                    // CHECKING, SAVINGS, Asset, Brokerage
                    || ($.inArray(summaryType, ['DDA','INVESTMENT']) !== -1 && $.inArray(accountTileDetailType, ['CHK', 'SAV', 'BR2', 'WR2', 'MMA']) !== -1)
                ) {
                    browserAPI.log("Skip card # " + code + ", accountId not found or card does not earn points or account has been closed");

                    // refs #19660 Southwest travel credit
                    if (accountId && ['SOUTHWEST_PREMIER', 'SOUTHWEST_AIRLINES'].indexOf(cardType) !== -1) {
                        goToSouthwestRewards = true;
                        params.data.linkSouthwestTravelCredits = "https://chaseloyalty.chase.com/home?AI=" + accountId;
                        params.data.properties.SouthwestTravelCredits.push({
                            displayName: displayName,
                            link: params.data.linkSouthwestTravelCredits,
                        });
                    }// if (accountId && cardType === 'SOUTHWEST_AIRLINES')

                    if (accountId && ['SOUTHWEST_PREMIER', 'SOUTHWEST_AIRLINES'].indexOf(cardType) !== -1) {
                        try {
                            browserAPI.log('[Current Url]: ' + document.location.href);
                            const requestCardList = await plugin.fetch('/svc/rr/accounts/secure/v1/account/rewards/detail/card/list', {
                                body: 'accountId=' + accountId,
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                    'x-jpmc-csrf-token': 'NONE',
                                }
                            });
                            const rewardSW = await requestCardList.json();
                            browserAPI.log(JSON.stringify(rewardSW));
                            let rewardProgramNameSW = rewardSW.rewardProgramName;
                            browserAPI.log("rewardProgramName -> " + rewardProgramNameSW);
                            browserAPI.log("cardDescription -> " + cardDescription);

                            [skip, kind, cardDescription] = plugin.getCardType(cardType, kind, cardDescription, rewardProgramNameSW);
                            browserAPI.log("displayName for Southwest Rapid Rewards card -> " + displayName);

                            displayName = "..." + code + " (" + type +"Card)";
                            browserAPI.log("displayName -> " + displayName);
                            browserAPI.log("cardDescription -> " + cardDescription);
                            if (typeof (kind) != 'undefined') {
                                displayName = kind + " " + displayName;
                            }
                            browserAPI.log("fixed displayName for Southwest Rapid Rewards card -> " + displayName);

                            if (closed) {
                                cardDescription = 'Closed';
                            }
                            params.data.properties.DetectedCards = plugin.addDetectedCard(params.data.properties.DetectedCards, [{
                                Code: 'chase' + code,
                                DisplayName: displayName,
                                CardDescription: cardDescription
                            }]);
                        } catch (error) {

                        }
                    }// if (accountId && ['SOUTHWEST_PREMIER', 'SOUTHWEST_AIRLINES'].indexOf(cardType) !== -1)

                    if (!skip) {
                        continue;
                    }

                    // Co-branded card
                    coBrandedCard = true;
                    browserAPI.log("Co-branded card => #" + code + ": " + displayName);
                }// if (empty($accountId) || $unavailable || $nickname == 'TOTAL CHECKING' || $closed || $skip)

                // get card Balance
                browserAPI.log("card #" + code + ": " + displayName);
                browserAPI.log("get card Balance");
                let balance;
                let rewardProgramName;
                let requestReward;

                try {
                    await delay();
                    browserAPI.log('[Current Url]: ' + document.location.href);
                    requestReward = await plugin.fetch("/svc/rr/accounts/secure/v1/account/rewards/detail/card/list", {
                        body: 'accountId=' + accountId,
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'x-jpmc-csrf-token': 'NONE',
                        }
                    });
                    const reward = await requestReward.json();

                    balance = reward.rewardBalance;
                    browserAPI.log(JSON.stringify(reward));
                    browserAPI.log("balance -> " + balance);
                    rewardProgramName = reward.rewardProgramName;
                    browserAPI.log("rewardProgramName -> " + rewardProgramName);

                    // J.P. Morgan
                    // Business accounts: 3747771, 4613170
                    if (
                        (!kind || rewardProgramName || util.findRegExp(displayName, /^CREDIT CARD \.\.\.\d+$/))
                        && typeof (reward.rewardsCardType) != 'undefined'
                    ) {
                        cardType = reward.rewardsCardType;
                        [skip, kind, cardDescription] = plugin.getCardType(cardType, kind, cardDescription, rewardProgramName);
                        browserAPI.log("displayName for Business accounts -> " + displayName);
                        browserAPI.log("kind -> " + kind);
                        if (kind) {
                            displayName = kind + " ..." + code.replace(/[^\d]+/, '') + " (" + type + "Card)";
                        }

                        browserAPI.log("displayName for Business accounts -> " + displayName);
                        params.data.properties.DetectedCards = plugin.addDetectedCard(params.data.properties.DetectedCards, [{
                            Code: 'chase' + code,
                            DisplayName: displayName,
                            CardDescription: cardDescription
                        }]);
                    }

                    // Co-branded card
                    if (coBrandedCard === true) {
                        browserAPI.log("Co-branded card => set balance null");
                        balance = null;
                    }

                    if (
                        (balance || coBrandedCard === true)
                        && displayName
                        && code
                    ) {
                        let subAccount = {
                            "Code": 'chase' + code,
                            "DisplayName": displayName,
                            "Balance": balance,
                        };

                        // detect closed cards
                        let cardInfo = null;
                        let replacementAccountMask;

                        // Co-branded card
                        if (coBrandedCard === true) {
                            browserAPI.log("Co-branded card => set IsHidden = true");
                            subAccount.IsHidden = true;
                        } else {
                            try {
                                const requestDetail = await plugin.fetch('/svc/rr/accounts/secure/v2/account/detail/card/list', {
                                    body: 'accountId=' + accountId,
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                        'x-jpmc-csrf-token': 'NONE',
                                    }
                                });
                                const responseDetail = await requestDetail.json();

                                browserAPI.log("---------------- Chase Freedom 5% cash back tracking ----------------");
                                browserAPI.log(JSON.stringify(responseDetail));
                                browserAPI.log("---------------- Chase Freedom 5% cash back tracking ----------------");

                                replacementAccountMask = responseDetail.detail.replacementAccountMask;
                                browserAPI.log("replacementAccountMask: " + replacementAccountMask);

                                cardInfo = responseDetail;
                            } catch (error) {

                            }
                        }

                        // We've transferred your account details to your new credit card
                        if (replacementAccountMask) {
                            browserAPI.log("We've transferred your account details to your new credit card " + replacementAccountMask);
                            params.data.properties.DetectedCards = plugin.addDetectedCard(params.data.properties.DetectedCards, [{
                                Code: subAccount.Code,
                                DisplayName: subAccount.DisplayName,
                                CardDescription: 'Active'
                            }]);
                            throw false;
                        }// if ($replacementAccountMask)

                        // 5% cash back  // refs #15406
                        if (kind == 'Freedom' && typeof (cardInfo.cashBackStatus) != 'undefined' ) {
                            let cashBackStatus = cardInfo.cashBackStatus;
                            let current_quarter = Math.floor((new Date().getMonth() + 3) / 3);
                            browserAPI.log("current_quarter: " + current_quarter);
                            let quarter = '';
                            switch (current_quarter) {
                                case 1:
                                    quarter = 'Jan-Mar';
                                    break;
                                case 2:
                                    quarter = 'Apr-Jun';
                                    break;
                                case 3:
                                    quarter = 'Jul-Sep';
                                    break;
                                case 4:
                                    quarter = 'Oct-Dec';
                                    break;
                            }// switch ($current_quarter)
                            quarter = "<a target='_blank' href='https://awardwallet.com/blog/link/ChaseFreedomCurrentQuarter'>" + quarter + "</a>";
                            if (cashBackStatus == 'ENROLLED') {
                                let description = "";
                                subAccount.CashBack = "Activated (" + quarter + ")" + description;
                            }// if ($cashBackStatus == 'ENROLLED')
                            else if (cashBackStatus == 'ELIGIBLE') {
                                subAccount.CashBack = "Not Activated (" + quarter + ")";
                            }
                            else if (cashBackStatus == null && availableBalance === null && currentBalance === null) {
                                browserAPI.log("Skip transferred card #" + subAccount.Code + ": " + displayName);
                                throw false;
                            }
                        }// if (kind == 'Freedom')

                        if (
                            util.stristr(rewardProgramName, 'Ultimate Rewards')
                            || util.stristr(rewardProgramName, 'Chase Sapphire Reserve')
                            || util.stristr(rewardProgramName, 'Sapphire Preferred')
                            || util.stristr(rewardProgramName, 'Ink ')
                        ) {
                            subAccount.DisplayName = subAccount.DisplayName.replace(kind + " ...", kind + " / Ultimate Rewards ...");
                            browserAPI.log("upd displayName -> " + subAccount.DisplayName);
                            subAccount.kind = kind;
                            subAccount.linkUR = "https://ultimaterewardspoints.chase.com/home?AI=" + accountId;
                            goToUltimateRewards = true;
                            params.data.linkUR = subAccount.linkUR;
                            params.data.subAccountCode = subAccount.Code;
                        }// if (strstr($rewardProgramName, 'Ultimate Rewards'))

                        // refs #19361
                        if (
                            plugin.parseCategories(params)
                            && (
                                typeof (params.data.linkUR) !== 'undefined'
                                // Co-branded card
                                || coBrandedCard === true
                                || util.stristr(rewardProgramName, 'Amazon ')
                            )
                        ) {
                            browserAPI.log(">> get categories from Chase for card " + subAccount.DisplayName);

                            let activities = [];
                            let startDate = util.getSubAccountHistoryStartDate(params.account, subAccount.Code);
                            browserAPI.log(">> [historyStartDate " + startDate + "]");

                            // refs #19361, note-78
                            if (startDate != null && startDate !== 0) {
                                let newStartDate = new Date(startDate * 1000);
                                newStartDate.setDate(newStartDate.getDate() - 4);
                                startDate = newStartDate / 1000;
                                browserAPI.log('>> [set historyStartDate date -4 days]: ' + startDate);
                            }

                            var activityInfoPage = 0;
                            var lastSortField = '';
                            var paginationContextualText = '';
                            var moreTransactionsIndicator = null;
                            var lastSortFieldToTime = null;
                            var activityInfo = [];
                            let dateHi = new Date();
                            let dateLow = dateHi.setFullYear(dateHi.getFullYear() - 2);

                            await plugin.extendChaseSession(params);

                            if (!profileId) {
                                browserAPI.log(">>> old categories requests");
                                lastSortField = null;
                                var pageStartIndicator = null;
                                var moreActivitiesAvailable = null;

                                let getHistoryData = {
                                    "accountId"          : accountId,
                                    "numberOfActivities" : 50,
                                    "dateLow"            : plugin.getDate(2),
                                    "dateHi"             : plugin.getDate(),
                                    "sortOrder"          : "DESC",
                                    "activityType"       : "POSTED",
                                    "sortBy"             : "AUTH_DATE",
                                };

                                await plugin.extendChaseSession(params);

                                //TODO: Parallel requests
                                do {
                                    browserAPI.log(">> [page " + activityInfoPage + "]: get categories from Chase for card " + subAccount.DisplayName);
                                    activityInfoPage++;

                                    if (
                                        activityInfoPage > 0
                                        && typeof (lastSortField) !== 'undefined' && lastSortField !== null
                                        && typeof (pageStartIndicator) !== 'undefined' && pageStartIndicator !== null
                                    ) {
                                        getHistoryData['lastSortField'] = lastSortField;
                                        getHistoryData['pageStartIndicator'] = pageStartIndicator;
                                    }

                                    let requestActivityInfo;
                                    try {
                                        await delay();
                                        requestActivityInfo = await plugin.fetch('/svc/rr/accounts/secure/v3/activity/card/list', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded',
                                                'x-jpmc-csrf-token': 'NONE',
                                            },
                                            body: new URLSearchParams(getHistoryData).toString()
                                        });
                                        const responseActivityInfo = await requestActivityInfo.json();
                                        browserAPI.log("---------------- get categories from Chase ----------------");
                                        // browserAPI.log(JSON.stringify(responseActivityInfo));
                                        // browserAPI.log("---------------- get categories from Chase for card ----------------");

                                        activityInfo = activityInfo.concat(responseActivityInfo.activities);

                                        moreActivitiesAvailable = null;
                                        lastSortField = null;
                                        pageStartIndicator = null;
                                        if (typeof (responseActivityInfo.moreActivitiesAvailable) !== 'undefined') {
                                            moreActivitiesAvailable = responseActivityInfo.moreActivitiesAvailable;
                                        }
                                        if (typeof (responseActivityInfo.lastSortFieldValue) !== 'undefined') {
                                            lastSortField = responseActivityInfo.lastSortFieldValue;
                                        }
                                        if (typeof (responseActivityInfo.pageStartIndicator) !== 'undefined') {
                                            pageStartIndicator = responseActivityInfo.pageStartIndicator;
                                        }

                                        browserAPI.log("moreActivitiesAvailable: " + moreActivitiesAvailable);
                                        let lastSortFieldToTime =  lastSortField.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
                                        browserAPI.log("lastSortField: " + lastSortField  + " / " + Math.floor(new Date(lastSortFieldToTime) / 1000));
                                        browserAPI.log("pageStartIndicator: " + pageStartIndicator);
                                        browserAPI.log("activityInfoPage: " + activityInfoPage);

                                        browserAPI.log("Total " + activityInfo.length + " activity rows were found");
                                    } catch (error) {
                                        browserAPI.log("fail: get categories from Chase");
                                        browserAPI.log('status ' + requestActivityInfo.status);
                                        // error = $(error);
                                        browserAPI.log("---------------- fail data ----------------");
                                        browserAPI.log(JSON.stringify(requestActivityInfo));
                                        browserAPI.log("---------------- fail data ----------------");
                                    }
                                } while (
                                    lastSortField !== null
                                    && pageStartIndicator !== null
                                    && activityInfoPage < plugin.maxActivityInfoPage
                                    && moreActivitiesAvailable === true
                                    && (startDate === null || Math.floor(new Date(lastSortFieldToTime) / 1000) > startDate)
                                );
                            } else

                            do {
                                browserAPI.log(">> [page " + activityInfoPage + "]: get categories from Chase for card " + subAccount.DisplayName);
                                activityInfoPage++;

                                if (
                                    activityInfoPage > 0
                                    && typeof (lastSortField) !== 'undefined' && lastSortField !== ''
                                    && typeof (paginationContextualText) !== 'undefined' && paginationContextualText !== ''
                                ) {
                                    lastSortField = '&last-sort-field-value=' + lastSortField;
                                    paginationContextualText = '&pagination-contextual-text=' + paginationContextualText.replace('#', '%23');
                                }

                                let requestActivityInfo;
                                try {
                                    browserAPI.log('[Current Url]: ' + document.location.href);
                                    requestActivityInfo = await plugin.fetch('/svc/rr/accounts/secure/v4/activity/card/credit-card/transactions/inquiry-maintenance/etu-digital-card-activity/v1/profiles/' + profileId + '/accounts/' + accountId + '/account-activities?record-count=50&account-activity-end-date=' + plugin.getDate() + '&account-activity-start-date=' + plugin.getDate(2) + '&request-type-code=T&sort-order-code=D&sort-key-code=T' + paginationContextualText, {
                                        method: 'GET',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                            'x-jpmc-csrf-token': 'NONE',
                                        },
                                    });
                                    const responseActivityInfo = await requestActivityInfo.json();
                                    browserAPI.log("---------------- get categories from Chase ----------------");
                                    // browserAPI.log(JSON.stringify(responseActivityInfo));
                                    // browserAPI.log("---------------- get categories from Chase for card ----------------");

                                    activityInfo = activityInfo.concat(responseActivityInfo.activities);

                                    moreTransactionsIndicator = null;
                                    lastSortField = null;
                                    paginationContextualText = null;
                                    if (typeof (responseActivityInfo.moreTransactionsIndicator) !== 'undefined') {
                                        moreTransactionsIndicator = responseActivityInfo.moreTransactionsIndicator;
                                    }
                                    if (typeof (responseActivityInfo.lastSortFieldValue) !== 'undefined') {
                                        lastSortField = responseActivityInfo.lastSortFieldValue;
                                    }
                                    if (typeof (responseActivityInfo.paginationContextualText) !== 'undefined') {
                                        paginationContextualText = responseActivityInfo.paginationContextualText;
                                    }

                                    browserAPI.log("moreTransactionsIndicator: " + moreTransactionsIndicator);
                                    let lastSortFieldToTime =  lastSortField.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
                                    browserAPI.log("lastSortField: " + lastSortField  + " / " + Math.floor(new Date(lastSortFieldToTime) / 1000));
                                    browserAPI.log("paginationContextualText: " + paginationContextualText);
                                    browserAPI.log("activityInfoPage: " + activityInfoPage);

                                    browserAPI.log("Total " + activityInfo.length + " activity rows were found");
                                } catch (error) {
                                    browserAPI.log("fail: get categories from Chase");
                                    browserAPI.log('status ' + requestActivityInfo.status);
                                    // error = $(error);
                                    browserAPI.log("---------------- fail data ----------------");
                                    browserAPI.log(JSON.stringify(requestActivityInfo));
                                    browserAPI.log("---------------- fail data ----------------");
                                }
                            } while (
                                lastSortField !== null
                                && paginationContextualText !== null
                                && activityInfoPage < plugin.maxActivityInfoPage
                                && moreTransactionsIndicator === true
                                && (startDate === null || Math.floor(new Date(lastSortFieldToTime) / 1000) > startDate)
                            );

                            await plugin.extendChaseSession(params);

                            if (!provider.isMobile) {
                                browserAPI.log("setIdleTimer: 180");
                                provider.setIdleTimer(180);
                            }

                            activityInfo = activityInfo.slice(0, 170).map(async (activityInfoRow) => {
                                // optimization, parse categories only for new transactions
                                let d = null;

                                if (!profileId) {
                                    d = activityInfoRow.transactionDate.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
                                } else

                                d = activityInfoRow.transactionPostDate.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3');
                                let transactionDate = new Date(d + ' UTC');
                                // browserAPI.log("startDate " + startDate);
                                // browserAPI.log("transactionDate " + transactionDate);
                                // browserAPI.log("transactionDate " + (transactionDate/1000 + 87000) );
                                // browserAPI.log((transactionDate/1000 + 87000) < startDate );
                                if (startDate !== null && ((transactionDate/1000 + 87000) < startDate)) {
                                    browserAPI.log("---------------- skip searching category: " + d + " / " + transactionDate/1000 + " ----------------");

                                    return activityInfoRow;
                                }

                                let getMerchantData = null;
                                let requestTransactionMerchantData = null;

                                if (!profileId) {
                                    getMerchantData = {
                                        "accountId"          : accountId,
                                        "transactionId"      : activityInfoRow.tranUniqueId,
                                        "postDate"           : activityInfoRow.postDate,
                                        "cardReferenceNumber": activityInfoRow.cardReferenceNumber,
                                        "relatedAccountId"   : activityInfoRow.transAccountId,
                                        "merchantName"       : activityInfoRow.merchantName,
                                    };

                                    requestTransactionMerchantData = await plugin.fetch('/svc/rr/accounts/secure/card/activity/ods/v2/detail/list', {
                                        body: new URLSearchParams(getMerchantData).toString(),//todo
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                            'x-jpmc-csrf-token': 'NONE',
                                        },
                                    });
                                }
                                else {
                                    getMerchantData = {
                                        "accountId"          : accountId,
                                        "transactionId"      : activityInfoRow.derivedUniqueTransactionIdentifier,
                                        "postDate"           : activityInfoRow.transactionPostDate,
                                        "cardReferenceNumber": activityInfoRow.cardReferenceNumber,
                                        "relatedAccountId"   : activityInfoRow.digitalAccountIdentifier,
                                        "merchantName"       : activityInfoRow.merchantDbaName,
                                    };
                                    const requestTransactionMerchantDataURL = '/svc/wr/accounts/secure/gateway/credit-card/transactions/inquiry-maintenance/digital-card-transaction/v1/profiles/' + profileId + '/card-transaction-details?digital-account-identifier=' + activityInfoRow.digitalAccountIdentifier + '&transaction-post-date=' + activityInfoRow.transactionPostDate + '&transaction-post-time=' + activityInfoRow.transactionPostTime + '&transaction-identifier=' + activityInfoRow.derivedUniqueTransactionIdentifier;

                                    requestTransactionMerchantData = await plugin.fetch(requestTransactionMerchantDataURL, {
                                        method: 'GET',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                            'x-jpmc-csrf-token': 'NONE',
                                        },
                                    });
                                }

                                const responsetransactionMerchantData = await requestTransactionMerchantData.json();
                                // browserAPI.log("---------------- get merchantCategoryCode ----------------");
                                // browserAPI.log(JSON.stringify(responsetransactionMerchantData));
                                // browserAPI.log("---------------- get merchantCategoryCode ----------------");

                                if (responsetransactionMerchantData.merchantCategoryCode) {
                                    activityInfoRow.merchantCategoryCode = responsetransactionMerchantData.merchantCategoryCode;
                                } else {
                                    activityInfoRow.merchantCategoryCode = responsetransactionMerchantData.transactionDetails.merchantCategoryCode;
                                }
                                // browserAPI.log("merchantCategoryCode: " + activityInfo[activityInfoRow].merchantCategoryCode);

                                // #note-48
                                if (
                                    (
                                        typeof (responsetransactionMerchantData.merchantCategoryCode) == 'undefined'
                                        && typeof (responsetransactionMerchantData.transactionDetails.merchantCategoryCode) == 'undefined'
                                    )
                                    &&
                                    (
                                        (
                                            responsetransactionMerchantData.cardReferenceNumber === 0
                                            || responsetransactionMerchantData.transactionDetails.cardReferenceNumber === 0
                                        )
                                        || JSON.stringify(responsetransactionMerchantData) === '{"code":"SUCCESS"}'
                                    )
                                ) {
                                    browserAPI.log("SET merchantCategoryCode as: 0");
                                    activityInfoRow.merchantCategoryCode = 0;
                                }

                                return activityInfoRow;
                            });

                            activityInfo = await plugin.allSettled(activityInfo);
                            // activityInfo = activityInfo.map(promise => promise.value);

                            activityInfo = activityInfo.map(promise => {
                                if (!promise.value) {
                                    browserAPI.log(">>>> [something went wrong]: " + JSON.stringify(promise));
                                    // debugger
                                }
                                return promise.value
                            });

                            // browserAPI.log(">>>>> " + JSON.stringify(activityInfo));
                            // console.log(activityInfo);

                            // Co-branded card
                            if (
                                coBrandedCard === true
                                || util.stristr(rewardProgramName, 'Amazon ')
                            ) {
                                let categoryDescription = {
                                    "AUTOMOTIVE"           : "Automotive",
                                    "AUTO"                 : "Automotive",
                                    "BILLS_UTILITIES"      : "Bills & utilities",
                                    "BILL"                 : "Bills & utilities",
                                    "EDUCATION"            : "Education",
                                    "EDUC"                 : "Education",
                                    "FOOD_DRINK"           : "Food & drink",
                                    "FOOD"                 : "Food & drink",
                                    "ENTERTAINMENT"        : "Entertainment",
                                    "ENTT"                 : "Entertainment",
                                    "FEES"                 : "Fees & adjustments",
                                    "GAS"                  : "Gas",
                                    "GASS"                  : "Gas",
                                    "GIFTS_DONATIONS"      : "Gifts & donations",
                                    "GIFT"                 : "Gifts & donations",
                                    "GROCERIES"            : "Groceries",
                                    "GROC"                 : "Groceries",
                                    "HEALTH_FITNESS"       : "Health & wellness",
                                    "HEAL"                 : "Health & wellness",
                                    "HOME"                 : "Home",
                                    "MERCHANDISE_INVENTORY": "Merchandise & inventory",
                                    "MRCH"                 : "Merchandise & inventory",
                                    "OFFICE_SHIPPING"      : "Office & shipping",
                                    "OFFI"                 : "Office & shipping",
                                    "PERSONAL"             : "Personal",
                                    "PERS"                 : "Personal",
                                    "PETS"                 : "Pet care",
                                    "PROFESSIONAL_SERVICES": "Professional services",
                                    "PROF"                 : "Professional services",
                                    "REPAIR_MAINTENANCE"   : "Repairs & maintenance",
                                    "REPA"                 : "Repairs & maintenance",
                                    "SHOPPING"             : "Shopping",
                                    "SHOP"                 : "Shopping",
                                    "TRANSPORTATION"       : "Transportation",
                                    "TRAVEL"               : "Travel",
                                    "TRAV"                 : "Travel",
                                    "MISCELLANEOUS"        : "Miscellaneous",
                                    "MISC"                 : "Miscellaneous",
                                    null                   : null,
                                };

                                let activities = activityInfo;
                                let history = [];

                                for (let activity in activities) {
                                    if (!activities.hasOwnProperty(activity)) {
                                        continue;
                                    }

                                    let transactionDate;
                                    let description;
                                    let amount;
                                    let category;
                                    let row = {};

                                    if (
                                        typeof (activities[activity].authorizationDate) === 'undefined'
                                        && typeof (activities[activity].transactionDate) !== 'undefined'
                                    ) {
                                        if (plugin.enableHistoryLogs === true) {
                                            browserAPI.log("old categories request");
                                        }
                                        transactionDate = new Date(activities[activity].transactionDate.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3'));
                                        description = activities[activity].description;
                                        amount = activities[activity].amount;
                                        category = activities[activity].category;
                                    }
                                    else {
                                        // refs #22134
                                        if (typeof (activities[activity].authorizationDate) === 'undefined') {
                                            browserAPI.log("---------------- parseCardDetails: broken transaction ----------------");
                                            browserAPI.log(JSON.stringify(activities[activity]));
                                            browserAPI.log("---------------- parseCardDetails: broken transaction ----------------");
                                        }

                                        transactionDate = new Date(activities[activity].authorizationDate.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3'));
                                        description = activities[activity].merchantDbaName;
                                        amount = activities[activity].transactionAmount;
                                        category = activities[activity].expenseCategoryCode;
                                    }

                                    let postDate = transactionDate / 1000;
                                    if (startDate && postDate < startDate) {
                                        browserAPI.log("break at date " + transactionDate + " " + postDate);
                                        continue;
                                    }

                                    row = {
                                        "Date"                   : postDate,
                                        "Description"            : description,
                                        "Points"                 : activities[activity].earnedRewardsAmount,
                                        "Amount"                 : amount,
                                        "Currency"               : 'USD',
                                        "Transaction Description": activities[activity].transactionReferenceNumber,
                                    };

                                    let merchantCategoryCode = activities[activity].merchantCategoryCode;

                                    if (plugin.enableHistoryLogs === true) {
                                        browserAPI.log("[" + plugin.getDate(null, transactionDate) + "]: " + postDate + " | " + description + " | " + amount + " | Category: " + category + " | Merchant: " + merchantCategoryCode);
                                    }

                                    let merchantCategory;
                                    if (merchantCategoryCode !== 0) {
                                        merchantCategory = plugin.getMerchantCode(merchantCategoryCode);

                                        if (plugin.enableHistoryLogs === true) {
                                            browserAPI.log("Matched >>> set category from Chase: " + merchantCategory);
                                        }

                                        row.Category = merchantCategory;
                                    } else {
                                        merchantCategory = categoryDescription[category];

                                        if (plugin.enableHistoryLogs === true) {
                                            browserAPI.log("Matched >>> set category from UR: " + merchantCategory);
                                        }

                                        row.Category = merchantCategory;
                                    }

                                    history.push(row);
                                }// for (let activity in activities)

                                subAccount.HistoryRows = history;
                            }// if (coBrandedCard === true)
                            else {
                                subAccount.ChaseHistory = activityInfo;
                            }
                        }//if (plugin.parseCategories(params))

                        params.data.properties.SubAccounts.push(subAccount);
                        // not Co-branded card
                        if (coBrandedCard === false) {
                            params.data.properties.DetectedCards = plugin.addDetectedCard(params.data.properties.DetectedCards, [{
                                Code           : subAccount.Code,
                                DisplayName    : subAccount.DisplayName,
                                CardDescription: 'Active'
                            }]);
                        }
                        browserAPI.log("subAccountBalance: " + subAccountBalance);
                        if (
                            !util.stristr(rewardProgramName, 'Amazon')
                            && !util.stristr(rewardProgramName, 'Disney')
                            && !util.stristr(rewardProgramName, 'Prime Visa')// Amazon
                        ) {
                            // subAccountBalance += floatval(str_replace([',', '.'], ['', ','], balance));
                            balance = balance.replace(/\,/gi, '');
                            subAccountBalance += parseFloat(balance);
                        }
                        browserAPI.log("subAccountBalance after counting: " + subAccountBalance);
                    }
                } catch (error) {
                    if (error) {
                        browserAPI.log("fail: get card Balance");
                        // error = $(error);
                        browserAPI.log('status ' + requestReward.status);
                        browserAPI.log("---------------- fail data ----------------");
                        browserAPI.log(JSON.stringify(requestReward));
                        browserAPI.log("---------------- fail data ----------------");
                    }
                }
            }// foreach ($accounts as $accounts)

            return subAccountBalance;
        }

        function number_format(number, decimals, dec_point, thousands_sep) {
            browserAPI.log("[number_format / before]: " + number);
            // Strip all characters but numerical ones.
            number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
            var n = !isFinite(+number) ? 0 : +number,
                prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
                sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
                dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
                s = '',
                toFixedFix = function (n, prec) {
                    var k = Math.pow(10, prec);
                    return '' + Math.round(n * k) / k;
                };
            // Fix for IE parseFloat(0.55).toFixed(0) = 0;
            s = (prec ? toFixedFix(n, prec) : '' + Math.floor(n)).split('.');
            if (s[0].length > 3) {
                s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
            }
            if ((s[1] || '').length < prec) {
                s[1] = s[1] || '';
                s[1] += new Array(prec - s[1].length + 1).join('0');
            }
            browserAPI.log("[number_format / after]: " + s.join(dec));
            return s.join(dec);
        }
    },

    // for Firefox, refs #19191, #note-24
    getXMLHttp: function () {
        if (typeof content !== 'undefined' && content && content.XMLHttpRequest) {
            return new content.XMLHttpRequest();
        }
        return new XMLHttpRequest();
    },

    fetch(...args) {
        browserAPI.log('fetch: ' + args[0]);
        if (typeof content !== 'undefined' && content && content.fetch) {
            return content.fetch(...args);
        }
        return fetch(...args);
    },

    async parseSubAccHistory(params) {
        browserAPI.log("History for card ..." + params.data.subAccountCode);
        let startDate = util.getSubAccountHistoryStartDate(params.account, params.data.subAccountCode);
        browserAPI.log("historyStartDate: " + startDate);

        // refs #19361, note-78
        if (startDate !== null) {
            let newStartDate = new Date(startDate * 1000);
            newStartDate.setDate(newStartDate.getDate() - 4);
            startDate = newStartDate / 1000;
            browserAPI.log('>> [set historyStartDate date -4 days]: ' + startDate);
        }

        let page = 0;
        let endHistory = false;
        let result = [];
        let emptyResult = false;
        let newURL = false;

        // TODO: parallel requests
        do {
            browserAPI.log("[Page: " + page +"]");
            emptyResult = false;

            plugin.extendURSession(params);

            let data;
            let requestRewardsActivity;

            const delay = (ms = 500) => {
                browserAPI.log('---------------- set delay: ' + ms + ' ----------------');
                return new Promise(r => setTimeout(r, ms));
            }

            if (newURL === false) {
                try {
                    await delay();
                    requestRewardsActivity = await plugin.fetch("https://ultimaterewardspoints.chase.com/rewardsActivity?cycle=" + page, {
                        method : 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept'      : 'application/json, text/plain, */*'
                        },
                    });
                    data = await requestRewardsActivity.json();
                } catch (error) {
                    browserAPI.log('Failed to parse history ' + JSON.stringify(requestRewardsActivity));
                    browserAPI.log('status ' + requestRewardsActivity.status);
                    browserAPI.log('---------------- fail data ----------------');
                    browserAPI.log(JSON.stringify(error));
                    browserAPI.log('---------------- fail data ----------------');

                    if (requestRewardsActivity.status === 500) {
                        browserAPI.log('>>> endHistory reached');
                        endHistory = true;
                    }

                    if (requestRewardsActivity.status == 404) {
                        browserAPI.log('>>> needed New URL');
                        newURL = true;
                    }

                    if (util.stristr(JSON.stringify(requestRewardsActivity), '[]')) {
                        browserAPI.log('>>> empty results');
                        emptyResult = true;
                    }

                    if (!newURL) {
                        page++;

                        continue;
                    }
                } // } catch (error)
            }// if (newURL === false)

            if (/"status":404,"error":"Not Found","path":"\/rewardsActivity"}/.test(JSON.stringify(data))) {
                browserAPI.log("[history data]: " + JSON.stringify(data));
                browserAPI.log("use NEW history link");
                newURL = true;
            }

            if (newURL === true) {
                try {
                    await delay();
                    requestRewardsActivity = await plugin.fetch("https://ultimaterewardspoints.chase.com/rest/rewards-activity/all-activity?cycle=" + page, {
                        method: 'GET',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json, text/plain, */*'
                        },
                    });
                    data = await requestRewardsActivity.json();
                } catch (error) {
                    browserAPI.log('Failed to parse history ' + JSON.stringify(requestRewardsActivity));
                    browserAPI.log('status ' + requestRewardsActivity.status);
                    browserAPI.log('---------------- fail data ----------------');
                    browserAPI.log(JSON.stringify(error));
                    browserAPI.log('---------------- fail data ----------------');

                    if (requestRewardsActivity.status === 500) {
                        browserAPI.log('>>> endHistory reached');
                        endHistory = true;
                    }

                    if (util.stristr(JSON.stringify(requestRewardsActivity), '[]')) {
                        browserAPI.log('>>> empty results');
                        emptyResult = true;
                    }

                    page++;

                    continue;
                }
            }// if (newURL === true)

            browserAPI.log("[history data]: " + JSON.stringify(data));

            if (util.stristr(JSON.stringify(data), '[]')) {
                browserAPI.log('>>> empty results 2');
                emptyResult = true;
            }
            if (!data || data.length === 0) {
                browserAPI.log('>>> no data');
                browserAPI.log("break: stop parse history at " + result.length);
                break;
            }
            browserAPI.log("Total " + data.length + " activity rows were found");
            for (let activity in data) {
                let row = {};
                if (!data.hasOwnProperty(activity)) {
                    browserAPI.log("skip history: " + JSON.stringify(data[activity]));
                    continue;
                }
                let dateStr = data[activity].transactionDateInMillis;
                var postDate = dateStr / 1000;
                dateStr = new Date(dateStr);
                if (startDate && postDate < startDate) {
                    browserAPI.log("break at date " + dateStr + " " + postDate);
                    endHistory = true;
                    continue;
                }
                // Transaction
                row.Date = postDate;
                // Description
                row.Description = data[activity].transactionName;

                if (
                    (!row.Description || typeof (row.Description) == 'undefined')
                    && $.inArray(data[activity].activityType, [
                        'STATEMENT_CREDIT',
                    ]
                    ) !== -1
                ) {
                    row.Description = 'Statement Credit';
                }

                // Amount
                if (
                    typeof (data[activity].amountSpent) != 'undefined'
                    && typeof (data[activity].amountSpent.amount) != 'undefined'
                ) {
                    row.Amount = data[activity].amountSpent.amount;
                    row.Currency = 'USD';
                }
                // Points
                if (
                    typeof (data[activity].pointsEarned) != 'undefined'
                    && data[activity].pointsEarned
                    && typeof (data[activity].pointsEarned.amount) != 'undefined'
                ) {
                    row.Points = data[activity].pointsEarned.amount;
                } else if (
                    typeof (data[activity].pointsActivity) != 'undefined'
                    && typeof (data[activity].pointsActivity.amount) != 'undefined'
                ) {
                    row.Points = data[activity].pointsActivity.amount;
                }

                // fixed transfer transaction
                if (
                    util.stristr(row.Description, 'Points Moved To')
                    || (
                        typeof (data[activity].activityType) != 'undefined'
                        && $.inArray(data[activity].activityType, ['STATEMENT_CREDIT', 'TRIPS', 'REDEMPTION']) !== -1
                    )
                ) {
                    if (row.Points > 0) {
                        row.Points = -1 * row.Points;
                    }

                    if (row.Amount > 0) {
                        row.Amount =  -1 * row.Amount;
                    }
                }

                // Details: Statement Credit, Qualified purchase, Bonus earn etc.
                if (
                    typeof (data[activity].bonusCategory) != 'undefined'
                    && data[activity].bonusCategory === true
                ) {
                    // https://redmine.awardwallet.com/issues/15714#note-4
                    if (row.Points < 0)
                        row.Details = 'Return, Bonus earn';
                    else
                        row.Details = 'Bonus earn';
                }// if (ArrayVal($activity, 'bonusCategory', null) == true)

                // refs #19835 #note-8
                if (row.Points < 0 && row.Amount > 0) {
                    row.Amount *= -1;
                }

                // #note-57
                let activityItems = data[activity].activityItems;
                row['Transaction Description'] = JSON.stringify(activityItems);
                for (let activityItem in activityItems) {
                    if (!activityItems.hasOwnProperty(activityItem)) {
                        browserAPI.log("skip bad activityItem: " + JSON.stringify(activityItems[activityItem]));
                        continue;
                    }
                    let earnedTransactionDescription = activityItems[activityItem].earnedTransactionDescription;
                    browserAPI.log("activityItem: " + earnedTransactionDescription);
                    let description = util.findRegExp(earnedTransactionDescription, /(?:Pts|Points?) per \$1s*(?:earned on all|earned on|on all|on|)s*(.+)(?:purchases|)/ig);
                    if (!description) {
                        description = util.findRegExp(earnedTransactionDescription, /(?:cat|category):\s*(.+)/ig);
                    }
                    if (!description) {
                        description = util.findRegExp(earnedTransactionDescription, /Bonus on purchases at (.+)/ig);
                    }
                    browserAPI.log("result: " + description);
                    if (!description) {
                        continue;
                    }
                    let category = util.trim(description.replace(',', ', ').replace(/(other purchases|purchases|you spend|earned on all purchases|on all other purchases|earned on |^on )/ig, ''));
                    row.Category = category;
                    if (category) {
                        break;
                    }
                }// foreach ($activityItems as $activityItem)

                result.push(row);
            }

            page++;

            // refs #19361
            if (plugin.parseCategories(params) && result.length > plugin.maxHistoryRows) {
                browserAPI.log("break: stop parse history at " + result.length);
                break;
            }
        }
        while (
            page < 15
            && (!emptyResult || (emptyResult && page < 3))
            && !endHistory
        );

        browserAPI.log("[parseSubAccHistory]: return result");

        // refs #19361
        if (plugin.parseCategories(params)) {
            browserAPI.log("history rows: " + result.length);
            result = result.slice(0, plugin.maxHistoryRows);
            browserAPI.log("history rows after truncating: " + result.length);
        }

        return result;
    },

    extendURSession: function(params) {
        browserAPI.log('>>> Extend UR session');
        // let stay = $('button:contains("Stay logged in")');
        let stay = $('button[aria-label *= "Account Info is "]');

        if (stay) {
            stay.click();
        }
    },

    async extendChaseSession(params) {
        try {
            browserAPI.log('>>> Extend Chase session');

            const extendSession = await plugin.fetch('/svc/wr/accounts/l4/v1/user/session/extend', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'x-jpmc-csrf-token': 'NONE',
                }
            });
            const responseExtendSession = await extendSession.json();
            browserAPI.log(`extendSession: ` + JSON.stringify(responseExtendSession));

            // browserAPI.log('>>> Extend Chase session, click by btn');
            // $('#requestSessionExtension').click();
        } catch (error) {

        }
    },

    async parseUltimateRewards(params) {
        browserAPI.log(">>> parseUltimateRewards");
        // refs #13946 Gathering transaction history for Chase
        // todo: add check for mobile here
        let travelBenefitsCards = [];
        let historyRows = await plugin.parseSubAccHistory(params);
        // add history to subAccount
        for (let card in params.data.properties.SubAccounts) {

            if (!params.data.properties.SubAccounts.hasOwnProperty(card)) {
                browserAPI.log(">> parseUltimateRewards: skip wrong subAccount #" + card);
                browserAPI.log(JSON.stringify(params.data.properties.SubAccounts));
                continue;
            }

            if (params.data.subAccountCode !== params.data.properties.SubAccounts[card].Code) {
                continue;
            }

            browserAPI.log(">> card with code was found: " + JSON.stringify(params.data.properties.SubAccounts[card]));

            if (!provider.isMobile) {
                browserAPI.log("setIdleTimer: 180");
                provider.setIdleTimer(180);
            }

            // refs #19361
            if (typeof (params.data.properties.SubAccounts[card].ChaseHistory) !== 'undefined') {
                // https://static.chasecdn.com/content/resource-bundles/digital-ui/3-2-1-6/en/bundles.json/BUSINESS/gallery.json
                // https://static.chasecdn.com/web/hash/dashboard/convoDeck/js/area_f13c3555300adcd4f3c0c41fd8f7b8f2.js
                let categoryDescription = {
                    "AUTOMOTIVE"           : "Automotive",
                    "AUTO"                 : "Automotive",
                    "BILLS_UTILITIES"      : "Bills & utilities",
                    "BILL"                 : "Bills & utilities",
                    "EDUCATION"            : "Education",
                    "EDUC"                 : "Education",
                    "FOOD_DRINK"           : "Food & drink",
                    "FOOD"                 : "Food & drink",
                    "ENTERTAINMENT"        : "Entertainment",
                    "ENTT"                 : "Entertainment",
                    "FEES"                 : "Fees & adjustments",
                    "GAS"                  : "Gas",
                    "GASS"                  : "Gas",
                    "GIFTS_DONATIONS"      : "Gifts & donations",
                    "GIFT"                 : "Gifts & donations",
                    "GROCERIES"            : "Groceries",
                    "GROC"                 : "Groceries",
                    "HEALTH_FITNESS"       : "Health & wellness",
                    "HEAL"                 : "Health & wellness",
                    "HOME"                 : "Home",
                    "MERCHANDISE_INVENTORY": "Merchandise & inventory",
                    "MRCH"                 : "Merchandise & inventory",
                    "OFFICE_SHIPPING"      : "Office & shipping",
                    "OFFI"                 : "Office & shipping",
                    "PERSONAL"             : "Personal",
                    "PERS"                 : "Personal",
                    "PETS"                 : "Pet care",
                    "PROFESSIONAL_SERVICES": "Professional services",
                    "PROF"                 : "Professional services",
                    "REPAIR_MAINTENANCE"   : "Repairs & maintenance",
                    "REPA"                 : "Repairs & maintenance",
                    "SHOPPING"             : "Shopping",
                    "SHOP"                 : "Shopping",
                    "TRANSPORTATION"       : "Transportation",
                    "TRAVEL"               : "Travel",
                    "TRAV"                 : "Travel",
                    "MISCELLANEOUS"        : "Miscellaneous",
                    "MISC"                 : "Miscellaneous",
                    null                   : null,
                };

                let activities = params.data.properties.SubAccounts[card].ChaseHistory;

                // plugin.consoleGroup("Chase History");
                //     console.log(activities);
                // plugin.consoleGroupEnd();

                plugin.extendURSession(params);

                for (let historyRow in historyRows) {

                    if (!historyRows.hasOwnProperty(historyRow)) {
                        continue;
                    }

                    if (plugin.enableHistoryLogs === true) {
                        plugin.consoleGroup("#" + historyRow + " [" + plugin.getDate(null, new Date(historyRows[historyRow].Date * 1000)) + "]: " + historyRows[historyRow].Date + " | "+ historyRows[historyRow].Description + " | " + historyRows[historyRow].Amount);
                    }

                    for (let activity in activities) {
                        if (!activities.hasOwnProperty(activity) || activities[activity] == null) {
                            continue;
                        }

                        let transactionDate;
                        let description;
                        let amount;
                        let category;

                        // refs #22134
                        browserAPI.log(">>>>>>>>>>>>>>>> parseUltimateRewards: search broken transaction, debug ----------------");
                        browserAPI.log(JSON.stringify(activities[activity]));
                        browserAPI.log("<<<<<<<<<<<<<<<< parseUltimateRewards: search broken transaction, debug ----------------");

                        if (
                            (typeof (activities[activity].authorizationDate) === 'undefined' || activities[activity].authorizationDate === null)
                            && typeof (activities[activity].transactionDate) !== 'undefined'
                        ) {
                            if (plugin.enableHistoryLogs === true) {
                                browserAPI.log("old categories request");
                            }
                            transactionDate = new Date(activities[activity].transactionDate.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3'));
                            description = activities[activity].description;
                            amount = activities[activity].amount;
                            category = activities[activity].category;
                        }
                        else {
                            transactionDate = new Date(activities[activity].authorizationDate.replace(/(\d{4})(\d{2})(\d{2})/, '$1-$2-$3'));
                            description = activities[activity].merchantDbaName;
                            amount = activities[activity].transactionAmount;
                            category = activities[activity].expenseCategoryCode;
                        }

                        let merchantCategoryCode = activities[activity].merchantCategoryCode;

                        if (
                            // plugin.getDate(null, transactionDate) === plugin.getDate(null, historyRows[historyRow].Date)
                            // &&
                        (
                                historyRows[historyRow].Description === description
                                || historyRows[historyRow].Description.replace('+ ', '').toLowerCase() === description.toLowerCase()// "transactionName":"+ PELOTON MEMBERSHIP CREDIT" vs "description":"Peloton Membership Credit",
                            )
                            && historyRows[historyRow].Amount === amount
                        ) {
                            if (plugin.enableHistoryLogs === true) {
                                browserAPI.log("Matched >>> [" + plugin.getDate(null, transactionDate) + "]: " + transactionDate + " | " + description + " - " + amount + " | category: " + category);
                            }

                            let merchantCategory;
                            if (merchantCategoryCode !== 0) {
                                merchantCategory = plugin.getMerchantCode(merchantCategoryCode);

                                if (plugin.enableHistoryLogs === true) {
                                    browserAPI.log("Matched >>> set category from Chase: " + merchantCategory);
                                }

                                historyRows[historyRow].Category = merchantCategory;
                            } else {
                                merchantCategory = categoryDescription[category];

                                if (plugin.enableHistoryLogs === true) {
                                    browserAPI.log("Matched >>> set category from UR: " + merchantCategory);
                                }

                                historyRows[historyRow].Category = merchantCategory;
                            }

                            break;
                        }
                    }// for (let activity in activities)

                    if (plugin.enableHistoryLogs === true) {
                        // browserAPI.log(">> New historyRow: " + JSON.stringify(historyRows[historyRow]));
                        plugin.consoleGroupEnd();
                    }
                }// for (let historyRow in historyRows)

                delete params.data.properties.SubAccounts[card].ChaseHistory;
            }// if (typeof (params.data.properties.SubAccounts[card].ChaseHistory) !== 'undefined')

            plugin.extendURSession(params);

            params.data.properties.SubAccounts[card].HistoryRows = historyRows;
            delete params.data.properties.SubAccounts[card].linkUR;

            // Chase freedom gathering current spend   // refs #16001
            if (
                params.data.properties.SubAccounts[card].kind === 'Freedom'
                && typeof (params.data.properties.SubAccounts[card].CashBack) != 'undefined'
                && !util.stristr(params.data.properties.SubAccounts[card].CashBack, 'Not Activated')
            ) {
                browserAPI.log("Current spend for " + params.data.properties.SubAccounts[card].DisplayName);

                try {
                    const request = await plugin.fetch('https://ultimaterewardspoints.chase.com/rest/five-percent-cashback/scenario', {
                        method: 'GET'
                    });
                    const response = await request.json();

                    browserAPI.log("---------------- Chase freedom gathering current spend ----------------");
                    browserAPI.log(JSON.stringify(response));
                    browserAPI.log("---------------- Chase freedom gathering current spend ----------------");

                    if (typeof (response) !== 'undefined') {
                        for (let scenario in response) {
                            if (!response.hasOwnProperty(scenario)) {
                                continue;
                            }
                            if (
                                response[scenario].enrollmentQuarter === 'CURRENT'
                                && $.inArray(response[scenario].status.name, ['OPEN']) === -1
                                && response[scenario].status.label !== ''
                            ) {
                                // "offerStartDate":"Jul 01"
                                let currentQuarter = new Date(response[scenario].offerStartDate + ' ' + new Date().getUTCFullYear() + ' UTC');
                                if ((currentQuarter / 1000) > (Math.floor(Date.now() / 1000))) {
                                    browserAPI.log("currentQuarter: " + currentQuarter);
                                    currentQuarter.setFullYear(currentQuarter.getFullYear() - 1);
                                    browserAPI.log("currentQuarter corrected: " + currentQuarter);
                                }
                                params.data.properties.SubAccounts[card].CurrentQuarter = currentQuarter / 1000;
                                // Total Cash Back Rewards
                                params.data.properties.SubAccounts[card].TotalCashBackRewards = "$" + response[scenario].totalCashback.amount;
                                // Max Reached
                                params.data.properties.SubAccounts[card].MaxReached = response[scenario].status.label === 'Max Reached';
                                if (typeof (response[scenario].categories) != 'undefined') {
                                    let description = " for <br> " + response[scenario].categories.join(', ');
                                    params.data.properties.SubAccounts[card].CashBack = params.data.properties.SubAccounts[card].CashBack + description;
                                }// if (!empty($scenario->categories))
                            }// if ($scenario->enrollmentQuarter == 'CURRENT'
                        }// for (let scenario in response)
                    }
                } catch (e) {

                }
            }// if (params.data.properties.SubAccounts[card].kind == 'Freedom' ...)

            travelBenefitsCards.push(card);

            delete params.data.properties.SubAccounts[card].kind;
            browserAPI.log(">> New SubAccount: " + JSON.stringify(params.data.properties.SubAccounts[card]));
            break;
        }// for (let card in params.data.properties.SubAccounts)

        plugin.extendURSession(params);

        if ($.isArray(travelBenefitsCards)) {
            await plugin.allSettled(travelBenefitsCards.map((card) => {
                return plugin.travelBenefits(params.data.properties.SubAccounts[card].DisplayName, params.data.properties.SubAccounts[card].Code, params)
            }));
        }

        delete params.data.linkUR;
        delete params.data.subAccountCode;
        provider.saveTemp(params.data);

        // find next card
        for (let card in params.data.properties.SubAccounts) {
            if (!params.data.properties.SubAccounts.hasOwnProperty(card)) {
                browserAPI.log(">> skip wrong subAccount #" + card);
                continue;
            }

            if (typeof (params.data.properties.SubAccounts[card].linkUR) == 'undefined') {
                browserAPI.log(">> skip wrong subAccount #" + card);
                continue;
            }

            browserAPI.log(">> card UR was found: " + JSON.stringify(params.data.properties.SubAccounts[card]));
            params.data.linkUR = params.data.properties.SubAccounts[card].linkUR;
            params.data.subAccountCode = params.data.properties.SubAccounts[card].Code;
            provider.saveTemp(params.data);
            break;
        }// for (let card in params.data.properties.SubAccounts)

        if (params.data.linkUR) {
            browserAPI.log(">> Open URL: " + params.data.linkUR);
            return provider.setNextStep('parseUltimateRewards', function () {
                document.location.href = params.data.linkUR;

                // refs #21433
                if (provider.isMobile) {
                    setTimeout(function () {
                        plugin.parseUltimateRewards(params);
                    }, 1000);
                }
            });
        }
        if (params.data.linkSouthwestTravelCredits) {
            return provider.setNextStep('parseSouthwestTravelCredit', function () {
                document.location.href = params.data.linkSouthwestTravelCredits;
            });
        }

        plugin.beforeFICOPage(params);
    },

    getDate: function(offset, originalDate) {
        // browserAPI.log("getDate");
        let date = new Date();

        if (typeof (originalDate) != 'undefined') {
            date = new Date(originalDate);
        }

        if (typeof (offset) != 'undefined')
            date.setFullYear(date.getUTCFullYear() - offset);
        let result = date.getUTCFullYear();
        // browserAPI.log(">>> Date Y: " + result);
        if ((date.getUTCMonth() + 1) < 10)
            result = result + '0' + (date.getUTCMonth() + 1);
        else
            result = result + '' + (date.getUTCMonth() + 1);
        // browserAPI.log(">>> Date + m: " + result);
        if (/^\d$/.test(date.getUTCDate()))
            result = result + '0' + date.getUTCDate();
        else
            result = result + '' + date.getUTCDate();

        // browserAPI.log(">>> Date: " + result);

        return result;
    },

    async parseSouthwestTravelCredit(params) {
        browserAPI.log(">>> parseSouthwestTravelCredit");
        browserAPI.log("[Current URL]: " + document.location.href);

        browserAPI.log(">> Southwest Cards: " + JSON.stringify(params.data.properties.SouthwestTravelCredits));

        //TODO: parallel requests
        for (let card in params.data.properties.SouthwestTravelCredits) {

            if (!params.data.properties.SouthwestTravelCredits.hasOwnProperty(card)) {
                browserAPI.log(">> skip wrong subAccount #" + card);
                continue;
            }

            browserAPI.log(">> Southwest Annual Travel Credit: " + JSON.stringify(params.data.properties.SouthwestTravelCredits[card]));
            browserAPI.log(">> Southwest Annual Travel Credit: " + JSON.stringify(params.data.properties.SouthwestTravelCredits[card].displayName));

            try {
                const requestSouthwest = await plugin.fetch('https://chaseloyalty.chase.com/rest/home/dashboard-trackers', {method: 'GET'});
                const responseSouthwest = await requestSouthwest.json();

                browserAPI.log("---------------- Southwest Annual Travel Credit ----------------");
                browserAPI.log(JSON.stringify(responseSouthwest));
                browserAPI.log("---------------- Southwest Annual Travel Credit ----------------");

                if (typeof (responseSouthwest) !== 'undefined') {
                    if (
                        typeof (responseSouthwest.maximumStatementCreditAmount) != 'undefined'
                        && typeof (responseSouthwest.statementCreditTrackerState) != 'undefined'
                        && $.inArray(responseSouthwest.statementCreditTrackerState, ['COMPLETE']) === -1
                    ) {

                        let maximumStatementCreditAmount = Math.floor(responseSouthwest.maximumStatementCreditAmount / 100);
                        let balance = (responseSouthwest.maximumStatementCreditAmount - responseSouthwest.statementCreditTrackerAmount) / 100;
                        if (balance <= 0) {
                            browserAPI.log("skip empty travel Southwest Annual Travel Credit for " + params.data.properties.SouthwestTravelCredits[card].displayName);
                        } else {
                            let code = util.findRegExp(params.data.properties.SouthwestTravelCredits[card].displayName, /(\.\.\.\d+)/);
                            browserAPI.log("code: " + code);

                            let benefitSubAccount = {
                                'Code': 'chaseSouthwestAnnualTravelCredit' + code,
                                'DisplayName': "$" + maximumStatementCreditAmount + " Southwest Annual Travel Credit (card ending " + code + ")",
                                'Balance': balance,
                                'Currency': "$",
                                'ExpirationDate': new Date(responseSouthwest.rewardsAnniversaryDate) / 1000,
                            };
                            browserAPI.log("Adding subAccount...");
                            browserAPI.log(JSON.stringify(benefitSubAccount));
                            params.data.properties.SubAccounts.push(benefitSubAccount);
                        }

                        // Upgraded Boarding
                        if (
                            typeof (responseSouthwest.upgradedBoardingTrackerData) != 'undefined'
                            && typeof (responseSouthwest.upgradedBoardingTrackerData.maximumTransactionCount) != 'undefined'
                        ) {
                            let maximumTransactionCount = responseSouthwest.upgradedBoardingTrackerData.maximumTransactionCount;
                            let balance = maximumTransactionCount - responseSouthwest.upgradedBoardingTrackerData.trackerAmount;

                            if (balance <= 0) {
                                browserAPI.log("skip empty Upgraded Boarding for " + params.data.properties.SouthwestTravelCredits[card].displayName);
                            } else {
                                let code = util.findRegExp(params.data.properties.SouthwestTravelCredits[card].displayName, /(\.\.\.\d+)/);
                                browserAPI.log("code: " + code);

                                let benefitSubAccount = {
                                    'Code'          : 'chaseSouthwestUpgradedBoarding' + code,
                                    'DisplayName'   : "Southwest Upgraded Boarding (card ending " + code + ")",
                                    'Balance'       : balance,
                                    'ExpirationDate': new Date(responseSouthwest.rewardsAnniversaryDate) / 1000,
                                };
                                browserAPI.log("Adding subAccount...");
                                browserAPI.log(JSON.stringify(benefitSubAccount));
                                params.data.properties.SubAccounts.push(benefitSubAccount);
                            }
                        }
                    }
                }
            } catch (error) {

            }

            params.data.properties.SouthwestTravelCredits.splice(card, 1);
            break;
        }// for (let card in params.data.properties.SouthwestTravelCredits)

        delete params.data.linkSouthwestTravelCredits;

        // find next card
        for (let card in params.data.properties.SouthwestTravelCredits) {
            if (!params.data.properties.SouthwestTravelCredits.hasOwnProperty(card)) {
                browserAPI.log(">> skip wrong subAccount #" + card);
                continue;
            }

            if (typeof (params.data.properties.SouthwestTravelCredits[card].link) == 'undefined') {
                browserAPI.log(">> skip wrong subAccount #" + card);
                continue;
            }

            browserAPI.log(">> card UR was found: " + JSON.stringify(params.data.properties.SouthwestTravelCredits[card]));
            params.data.linkSouthwestTravelCredits = params.data.properties.SouthwestTravelCredits[card].link;
            provider.saveTemp(params.data);
            break;
        }// for (let card in params.data.properties.SubAccounts)

        if (params.data.linkSouthwestTravelCredits) {
            return provider.setNextStep('parseSouthwestTravelCredit', function () {
                document.location.href = params.data.linkSouthwestTravelCredits;
            });
        }

        plugin.beforeFICOPage(params);
    },

    beforeFICOPage: function(params) {
        browserAPI.log(">>> beforeFICOPage");
        plugin.saveLastPage(params, 500);
    },

    consoleGroup: function(message) {
        if (provider.isMobile) {
            browserAPI.log(">>>>> " + message);
            return;
        }

        console.group(message);
    },

    consoleGroupEnd: function() {
        if (provider.isMobile) {
            browserAPI.log("<<<<<<<<<<<<<<<<< ");
            return;
        }

        console.groupEnd();
    },

    async travelBenefits(subAccountDisplayName, subAccountCode, params) {
        browserAPI.log(">>> travelBenefits");
        if (
            !util.stristr(subAccountDisplayName, 'Sapphire Reserve')
            && !util.stristr(subAccountDisplayName, 'J.P.Morgan Reserve')
            // && !util.stristr(subAccountDisplayName, 'Sapphire Preferred')
        ) {
            return;
        }
        browserAPI.log("Annual Travel Credit for card ..." + subAccountCode);

        try {
            browserAPI.log('>>> Airport Lounge Access');
            const benefitStatus = await plugin.fetch('https://ultimaterewardspoints.chase.com/rest/card-benefits/benefit/status', {
                method: 'GET'
            });
            const status = await benefitStatus.json();

            browserAPI.log("---------------- Airport Lounge Access ----------------");
            browserAPI.log(JSON.stringify(status));
            browserAPI.log("---------------- Airport Lounge Access ----------------");

            if (typeof (status.payload) == 'undefined') {
                throw false;
            }

            if (status.payload === 'ACTIVE') {
                params.data.properties.AirportLoungeAccess = "Activated";
            } else if ($.inArray(status.payload, ["NOT ENROLLED", "Not Enrolled"]) !== -1) {
                params.data.properties.AirportLoungeAccess = "Not Activated";
            }
            browserAPI.log("AirportLoungeAccess: " + params.data.properties.AirportLoungeAccess);
        } catch (e) {
            browserAPI.log('fail: Airport Lounge Access');
            // error = $(error);
            browserAPI.log('status ' + benefitStatus.status);
            browserAPI.log('---------------- fail data ----------------');
            browserAPI.log(JSON.stringify(benefitStatus));
            browserAPI.log('---------------- fail data ----------------');
        }

        try {
            browserAPI.log('>>> Annual Travel Credit');
            const requestTravelstatmentCredit = await plugin.fetch('https://ultimaterewardspoints.chase.com/rest/travelstatementcredit', {
                method: 'GET'
            });
            const travelstatementcredit = await requestTravelstatmentCredit.json();

            browserAPI.log("---------------- Annual Travel Credit ----------------");
            browserAPI.log(JSON.stringify(travelstatementcredit));
            browserAPI.log("---------------- Annual Travel Credit ----------------");

            if (typeof (travelstatementcredit.availableAmount) == 'undefined') {
                throw false;
            }

            let balance = 300 - travelstatementcredit.availableAmount;
            let month, year;
            [month, year] = travelstatementcredit.travelCreditRefreshDate.split('/');
            browserAPI.log('[Annual Travel Credit | exp]: ' + month + ' / ' + year);
            let day = new Date(year, month, 0).getDate();
            browserAPI.log('[Annual Travel Credit | day]: ' + day);
            let benefitSubAccount = {
                'Code'           : 'chaseAnnualTravelCredit' + subAccountCode,
                'DisplayName'    : travelstatementcredit.earnStateHeader + " (" + util.findRegExp(subAccountDisplayName, /(...\d+)/) + ")",
                'Balance'        : balance,
                'Currency'       : "$",
                'ExpirationDate' : new Date(month + "/" + day + "/" + year + " UTC") / 1000,
            };
            browserAPI.log("Adding subAccount...");
            browserAPI.log(JSON.stringify(benefitSubAccount));

            if (balance === 0) {
                browserAPI.log(">>> Skip used Annual Travel Credit: [" + travelstatementcredit.title + "]: " + balance);
            } else {
                params.data.properties.SubAccounts.push(benefitSubAccount);
            }
        } catch (e) {
            browserAPI.log('fail: Annual Travel Credit');
            browserAPI.log('status ' + requestTravelstatmentCredit.status);
            browserAPI.log('---------------- fail data ----------------');
            browserAPI.log(JSON.stringify(e));
            browserAPI.log(JSON.stringify(requestTravelstatmentCredit));
            browserAPI.log('---------------- fail data ----------------');
        }

        // Chase sign up bonus tracker // refs #17639
        browserAPI.log('>>> Chase sign up bonus tracker for card ...' + subAccountCode);

        try {
            const requestBonusTracker = await plugin.fetch('https://ultimaterewardspoints.chase.com/rest/earn-offer/premium-tracker-offer', {
                method: 'GET'
            });
            const bonusTracker = await requestBonusTracker.json();

            browserAPI.log("---------------- Chase sign up bonus tracker for card ----------------");
            browserAPI.log(JSON.stringify(bonusTracker));
            browserAPI.log("---------------- Chase sign up bonus tracker for card ----------------");

            if (
                typeof (bonusTracker.displayTracker) == 'undefined'
                || typeof (bonusTracker.bonusState) == 'undefined'
                || typeof (bonusTracker.amountLeftToSpend) == 'undefined'
                || bonusTracker.amountLeftToSpend <= 0
                || bonusTracker.bonusState === 'received'
                || bonusTracker.displayTracker === false
            ) {
                throw false;
            }

            let displayName = subAccountDisplayName.replace(/\s*\(.+\)$/, '');
            displayName = displayName.replace('/ Ultimate Rewards ', '');
            browserAPI.log("[DisplayName]: " + displayName);
            let exp = util.findRegExp(bonusTracker.shortDescription, /(?:until|by) (.+) to earn/);
            let benefitSubAccount = {
                'Code'           : 'chaseMinimumSpendAmountLeft' + subAccountCode,
                'DisplayName'    : "Minimum spend amount left on card " + util.findRegExp(displayName, /(...\d+)/),
                'Balance'        : bonusTracker.amountLeftToSpend,
                'Currency'       : "$",
                'ExpirationDate' : new Date(exp + ' UTC') / 1000,//todo: need to check
                'Spent'          : "$" + bonusTracker.amountSpent,
            };
            browserAPI.log("Adding subAccount...");
            browserAPI.log(JSON.stringify(benefitSubAccount));
            params.data.properties.SubAccounts.push(benefitSubAccount);
        } catch (e) {
            browserAPI.log('fail: Chase sign up bonus tracker for card');
            // error = $(error);
            browserAPI.log('status ' + requestBonusTracker.status);
            browserAPI.log('---------------- fail data ----------------');
            browserAPI.log(JSON.stringify(requestBonusTracker));
            browserAPI.log('---------------- fail data ----------------');
        }

        provider.saveTemp(params.data);
    },

    _allSettled(promises) {
        let mappedPromises = promises.map((p) => {
            return p
            .then((value) => {
                return {
                    status: 'fulfilled',
                    value,
                };
            })
            .catch((reason) => {
                return {
                    status: 'rejected',
                    reason,
                };
            });
        });
        return Promise.all(mappedPromises);
    },

    allSettled(promises) {
        return typeof Promise.allSettled === 'function' ? Promise.allSettled(promises) : plugin._allSettled(promises);
    },

    getCardType: function(cardType, kind, cardDescription, rewardProgramName) {
        browserAPI.log(">>> getCardType");
        if (typeof (rewardProgramName) == 'undefined') {
            rewardProgramName = null;
        }
        browserAPI.log("rewardProgramName: " + rewardProgramName);
        let C_CARD_DESC_UNIVERSAL = 'Should be tracked separately as a separate <a target = "_blank" href="/account/add/[Program_ID]">[Program] account added to AwardWallet</a>';
        let C_CARD_DESC_MARRIOTT = 'Should be tracked separately as a separate <a target = "_blank" href="/account/add/17">Marriott account added to AwardWallet</a>';
        let C_CARD_DESC_HHONORS = 'Should be tracked separately as a separate <a target = "_blank" href="/account/add/22">Honors account added to AwardWallet</a>';
        let skip = false;
        switch (cardType) {
            case 'AARP':
                kind = 'AARP Credit Card from Chase';
                break;
            case 'AER_LINGUS_AVIOS':
                kind = 'Aer Lingus Visa Signature® Card';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'Aer Lingus');
                cardDescription = cardDescription.replace('[Program_ID]', '184');
                skip = true;
                break;
            case 'AEROPLAN_CARD':
                kind = 'Aeroplan® Card';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'Air Canada');
                cardDescription = cardDescription.replace('[Program_ID]', '2');
                skip = true;
                break;
            case 'AMAZON':
                kind = 'Amazon Rewards Visa Signature Card';
                break;
            case 'AMAZON_PRIME':
                kind = 'Amazon Prime Rewards Visa Signature Card';
                break;
            case 'AMAZON_REWARDS_VISA':
                kind = 'Amazon';
                break;
            case 'AIRFORCE_CLUB':
            case 'ARMY_AND_AIR_FORCE_EXCHANGE_SERVICE':
            case 'ARMY_MWR':
                kind = 'Military Free Cash Rewards';
                break;
            case 'BRITISH_AIRWAYS':
                kind = 'British Airways Visa Signature® Card';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'British Airways');
                cardDescription = cardDescription.replace('[Program_ID]', '31');
                skip = true;
                break;
            case 'CHASE_MARSHALL':
            case 'SAPPHIRE_SENECA':
                kind = 'Sapphire Reserve';
                break;
            case 'CHASE_INK_BUSINESS_PREFERRED_CORP':
                /*
                 if (
                 rewardProgramName
                 && rewardProgramName.indexOf('Unlimited') !== -1
                 ) {
                 */
                /*
                 'rewardProgramName' => 'Ink Business Unlimited SM',
                 'rewardsCardType' => 'CHASE_INK_BUSINESS_PREFERRED_CORP',
                 * /
                 kind = "Ink Unlimited (Corporate)";
                 }
                 else {
                 /*
                 ‘rewardProgramName’ => ‘’,
                 ‘rewardsCardType’ => ‘CHASE_INK_BUSINESS_PREFERRED_CORP’,
                 * /
                 }
                 */
                kind = "Ink Preferred";
                break;
            case 'CHASE_INK_BUSINESS_PREFERRED':
                if (
                    rewardProgramName
                    && (
                        rewardProgramName.indexOf('Unlimited') !== -1
                    )
                ) {
                    /*
                     'rewardProgramName' => 'Ink Business Unlimited SM',
                     'rewardsCardType' => 'CHASE_INK_BUSINESS_PREFERRED',
                     */
                    kind = "Ink Unlimited";
                }
                else {
                    /*
                     ‘rewardProgramName’ => ‘Ink Business PreferredS SM’,
                     ‘rewardsCardType’ => ‘CHASE_INK_BUSINESS_PREFERRED’,
                     */
                    kind = "Ink Preferred";
                }
                break;
            case 'CHASE_INK_BUSINESS_PREMIER':
                kind = 'Ink Business Premier®';
                break;
            case 'CHASE_SAPPHIRE_PREFERRED':
                kind = 'Sapphire Preferred';
                break;
            case 'CHASE_SAPPHIRE':
                kind = 'Sapphire';
                break;
            case 'CHASE_SLATE':
                kind = 'Slate';
                skip = true;
                break;
            case 'CHASE_SLATE_EDGE':
                kind = 'Slate Edge';
                skip = true;
                break;
            case 'DISNEY':
                kind = 'Disney';
                break;
            case 'FAIRMONT_HOTELS_AND_RESORTS':
                kind = 'Fairmont';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'Fairmont');
                cardDescription = cardDescription.replace('[Program_ID]', '130');
                break;
            case 'FREEDOM_CARD':
            case 'FREEDOM_SIGNATURE':
            case 'FREEDOM_PLATINUM':
                kind = 'Freedom';
                break;
            case 'FREEDOM_UNLIMITED':
                kind = 'Freedom Unlimited';
                break;
            case 'FREEDOM_STUDENT':
                kind = 'Freedom Student';
                break;
            case 'MBAPPE_CARD':
                kind = 'Freedom Flex';
                break;
            case 'JPMORGAN':
            case 'JPMORGAN_PRIVATE_BANK':
                kind = 'J.P.MORGAN';
                break;
            case 'JPM_MARSHALL':
            case 'JPM_SENECA':
                kind = "J.P.Morgan Palladium";
                break;
            case 'HYATT':
                kind = 'The Hyatt Credit Card';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'World of Hyatt');
                cardDescription = cardDescription.replace('[Program_ID]', '10');
                skip = true;
                break;
            case 'HYATT_HOTELS':
                kind = 'The World Of Hyatt Credit Card';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'World of Hyatt');
                cardDescription = cardDescription.replace('[Program_ID]', '10');
                skip = true;
                break;
            case 'HYATT_BUSINESS':
                kind = 'World of Hyatt Business Credit Card';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'World of Hyatt');
                cardDescription = cardDescription.replace('[Program_ID]', '10');
                skip = true;
                break;
            case 'IBERIA_AVIOS':
                kind = 'Iberia Visa Signature® Card';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'Iberia Plus');
                cardDescription = cardDescription.replace('[Program_ID]', '86');
                skip = true;
                break;
            case 'INTERCONTINENTAL_HOTELS_GROUP':
                kind = 'IHG® Rewards Premier Credit Card';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'IHG Rewards Club');
                cardDescription = cardDescription.replace('[Program_ID]', '12');
                skip = true;
                break;
            case 'MARRIOTT':
                kind = 'Marriott Bonvoy Boundless™ Credit Card';
                cardDescription = C_CARD_DESC_MARRIOTT;
                skip = true;
                break;
            case 'MARRIOTT_REWARDS_PREMIER':
                kind = 'Marriott Bonvoy Premier™ Plus Business Credit Card';
                cardDescription = C_CARD_DESC_MARRIOTT;
                skip = true;
                break;
            case 'MARRIOTT_BONSAI':
                kind = 'Marriott Bonvoy Bold™ Credit Card';
                cardDescription = C_CARD_DESC_MARRIOTT;
                skip = true;
                break;
            case 'MARY_KAY':
                kind = 'Mary Kay';
                break;
            case 'RITZ_CARLTON':
                kind = 'The Ritz-Carlton™ Credit Card';
                cardDescription = C_CARD_DESC_MARRIOTT;
                skip = true;
                break;
            case 'SOUTHWEST_PREMIER':
                if (
                    rewardProgramName
                    && rewardProgramName.indexOf('Premier Business Credit Card') !== -1
                ) {
                    /*
                     rewardProgramName: "Southwest Rapid Rewards<sup>&reg;</sup> Premier Business Credit Card",
                     rewardsCardType: "SOUTHWEST_PREMIER",
                     */
                    kind = 'Southwest Rapid Rewards® Premier Business Credit Card';
                }
                else {
                    /*
                     ‘rewardProgramName’ => ‘Southwest Rapid Rewards<sup>&reg;</sup> Performance Business Credit Card"’,
                     ‘rewardsCardType’ => ‘SOUTHWEST_PREMIER’,
                     */
                    kind = 'Southwest Rapid Rewards® Performance Business Credit Card';
                }
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'Southwest');
                cardDescription = cardDescription.replace('[Program_ID]', '16');
                skip = true;
                break;
            case 'SOUTHWEST_AIRLINES':
                if (
                    rewardProgramName
                    && rewardProgramName.indexOf('Premier Credit Card') !== -1
                ) {
                    /*
                     rewardProgramName: "Southwest Rapid Rewards<sup>&reg;</sup> Premier Credit Card",
                     rewardsCardType: "SOUTHWEST_AIRLINES",
                     */
                    kind = 'Southwest Rapid Rewards® Premier Credit Card';
                }
                else if (
                    rewardProgramName
                    && rewardProgramName.indexOf('Plus Credit Card') !== -1
                ) {
                    /*
                     rewardProgramName: "Southwest Rapid Rewards<sup>&reg;</sup> Plus Credit Card",
                     rewardsCardType: "SOUTHWEST_AIRLINES",
                     */
                    kind = 'Southwest Rapid Rewards® Plus Credit Card';
                }
                else {
                    /*
                     ‘rewardProgramName’ => ‘Southwest Rapid Rewards<sup>&reg;</sup> Priority Credit Card"’,
                     ‘rewardsCardType’ => ‘SOUTHWEST_AIRLINES’,
                     */
                    kind = 'Southwest Rapid Rewards® Priority Credit Card';
                }
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'Southwest');
                cardDescription = cardDescription.replace('[Program_ID]', '16');
                skip = true;
                break;
            case 'SOUTHWEST_PLUS':// Southwest Rapid Rewards Plus Business
                kind = 'Southwest Rapid Rewards® Plus Credit Card';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'Southwest');
                cardDescription = cardDescription.replace('[Program_ID]', '16');
                skip = true;
                break;
            case 'STARBUCKS':
                kind = 'Starbucks Rewards';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'Starbucks Card Rewards');
                cardDescription = cardDescription.replace('[Program_ID]', '195');
                skip = true;
                break;
            case 'UNITED':
                kind = 'United';

                if (
                    rewardProgramName
                    && rewardProgramName.indexOf('Visa Infinite Card') !== -1
                ) {
                    kind = 'United Club℠ Infinite Card';
                }

                if (
                    rewardProgramName
                    && rewardProgramName.indexOf('United Gateway') !== -1
                ) {
                    kind = 'United Gateway℠ Card';
                }

                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'United');
                cardDescription = cardDescription.replace('[Program_ID]', '26');
                skip = true;
                break;
            case 'UNITED_TRAVEL_CASH':
            case 'UNITED_MILEAGEPLUS_CLUB':// MileagePlus Club United Chase (Business)
            case 'UNITED_MILEAGEPLUS_EXPLORER':
            case 'UNITED_MILEAGE_PLUS_UA':
            case 'UNITED_MILEAGEPLUS_PRESIDENTIAL_PLUS':// Presidental Plus United Chase (Business)
            case 'UNITED_MILEAGE_PLUS_FCB':// MileagePlus Card United Chase (Business)
            case 'UNITED_MILEAGE_PLUS_MIDDLE':
                kind = 'United';
                cardDescription = C_CARD_DESC_UNIVERSAL.replace('[Program]', 'United');
                cardDescription = cardDescription.replace('[Program_ID]', '26');
                skip = true;
                break;
            case 'ZAPPOS':
                kind = 'Zappos';
                break;
            case 'INK':
                kind = 'Ink';
                break;
            case 'INK_CLASSIC':
                kind = "Ink Classic";
                break;
            case 'INK_521':
                kind = 'Ink 521';
                break;
            case 'INK_CASH':
                kind = "Ink Cash";
                break;
            case 'INK_CASH_521':
                kind = "Ink Cash 521";
                break;
            case 'INK_CASH_LEGACY':
                kind = "Ink Cash (legacy)";
                break;
            case 'INK_CAPITAL':
                kind = "Ink (Capital)";
                break;
            case 'INK_PLUS':
                kind = "Ink Plus";
                break;
            case 'INK_PLUS_521':
                kind = "Ink Plus";// refs #17136
                // kind = "Ink Plus 521";// refs #15541
                break;
            case 'INK_PLUS_521_CORP':
                kind = "Ink Plus 521 (Corporate)";
                break;
            case 'INK_BOLD':
                kind = "Ink Bold";
                break;
            case 'INK_BOLD_521':
                kind = "Ink Bold 521";
                break;
            case 'INK_BOLD_EXCLUSIVES':
                kind = "Ink Bold Exclusives";
                break;
            case 'INK_BUSINESS':
                kind = "Ink Preferred";
                break;
            case 'INK_BUSINESS':
                kind = "Instacart";
                break;
            default:
                kind = '';
                if (cardType) {
                    browserAPI.log("Unknown kind -> " + cardType);
                }// if (cardType)
        }// switch ($cardType)

        return [skip, kind, cardDescription];
    },

    saveLastPage: function(params, delay) {
        browserAPI.log("saveLastPage");
        browserAPI.log(">>> exit");
        provider.logBody("lastPage");

        params.account.properties = params.data.properties;
        provider.saveProperties(params.account.properties);
        browserAPI.log('-------------------------------------------------------');
        browserAPI.log("[Current URL] -> " + document.location.href);
        browserAPI.log('>> exit ' + JSON.stringify(params.account.properties));
        console.log(params.account.properties);
        browserAPI.log('-------------------------------------------------------');

        provider.complete();
    },

    addDetectedCard: function (detectedCards, subAccounts) {
        browserAPI.log(">>> addDetectedCard");
        browserAPI.log("subAccount: " + JSON.stringify(subAccounts));
        if (typeof (subAccounts) == 'undefined') {
            browserAPI.log('>>> wrong  subAccounts');
            return detectedCards;
        }
        let newCard = true;
        // Update detected cards
        for (let card in detectedCards) {
            for (let i = 0; i < subAccounts.length; i++) {
                if (detectedCards.hasOwnProperty(card)) {
                    if (detectedCards[card].Code == subAccounts[i].Code) {
                        newCard = false;
                        browserAPI.log(">> card with the same code: " + JSON.stringify(detectedCards[card]));
                        detectedCards[card]['DisplayName'] = subAccounts[i].DisplayName;
                        if (!util.stristr(subAccounts[i].CardDescription, 'Should be tracked separately')) {
                            if (subAccounts[i].CardDescription == 'Closed')
                                detectedCards[card]['CardDescription'] = 'Closed';
                            else
                                detectedCards[card]['CardDescription'] = 'Active';
                        }
                        browserAPI.log(">> New DetectedCard: " + JSON.stringify(detectedCards[card]));
                    }// if (detectedCards[card].Code == subAccount[0].Code)
                }// if (detectedCards.hasOwnProperty(card))
            }// for (i = 0; i < subAccounts.length; i++)
        }// for (var card in detectedCards)

        if (newCard === true) {
            browserAPI.log(">> adding New DetectedCard: " + JSON.stringify(subAccounts));
            detectedCards = detectedCards.concat(subAccounts);
        }

        return detectedCards;
    },

    basename: function (path) {
        return path.split('/').reverse()[0];
    },

    getProperties: function (code, additionalInfo) {
        var displayName = " ..." + code + " (" + additionalInfo.typeCard + "Card)";
        browserAPI.log('displayName ' + displayName);

        var loyaltyVersion = $('#rewardsActivityLink > span[class *= "card-"]');
        if (loyaltyVersion)
            loyaltyVersion = util.findRegExp( loyaltyVersion.eq(0).attr('class'), /card-([^\s]+)/);
        browserAPI.log('loyaltyVersion ' + loyaltyVersion);
        switch (loyaltyVersion) {
            case 'SAPPHIRE_PREFERRED':
                displayName = additionalInfo.kindCard + "Ultimate Rewards" + displayName;
                break;
            case 'SAPPHIRE':
                displayName = "Sapphire / Ultimate Rewards" + displayName;
                break;
            case 'SAPPHIRE_RESERVE':
                displayName = "Sapphire Reserve / Ultimate Rewards" + displayName;
                break;
            case 'INK_CASH':
                displayName = "Ink Cash / Ultimate Rewards" + displayName;
                break;
            case 'INK_CASH_LEGACY':
                displayName = "Ink Cash (legacy) / Ultimate Rewards" + displayName;
                break;
            case 'INK_PLUS':
                displayName = "Ink Plus / Ultimate Rewards" + displayName;
                break;
            case 'INK_BOLD':
                displayName = "Ink Bold / Ultimate Rewards" + displayName;
                break;
            case 'INK_BUSINESS':
                displayName = "Ink Preferred / Ultimate Rewards" + displayName;
                break;
            case 'INK_CLASSIC':
                displayName = "Ink Classic / Ultimate Rewards" + displayName;
                break;
            case 'INK_BOLD_521':
                displayName = "Ink Bold 521 / Ultimate Rewards" + displayName;
                break;
            case 'INK_BOLD_EXCLUSIVES':
                displayName = "Ink Bold Exclusives / Ultimate Rewards" + displayName;
                break;
            case 'FREEDOM':
                displayName = "Freedom / Ultimate Rewards" + displayName;
                break;
            case 'FREEDOM_UNLIMITED':
                displayName = "Freedom Unlimited / Ultimate Rewards" + displayName;
                break;
            case 'PALLADIUM': case 'SELECT':
                displayName = "J.P.Morgan / Ultimate Rewards" + displayName;
                break;
            case 'CORPORATE':
                displayName = "Chase / Ultimate Rewards" + displayName;
                break;
            default:
                browserAPI.log('loyaltyVersion not found');
                if (typeof (additionalInfo.kindCard) != 'undefined')
                    displayName = additionalInfo.kindCard + "Ultimate Rewards" + displayName;
                else
                    displayName = "Ultimate Rewards" + displayName;
        }
        browserAPI.log('displayName ' + displayName);

        return displayName;
    },

    getMerchantCode: function(code) {
        // https://static.chasecdn.com/content/site-services/content-pairs/configuration/en/merchant-codes.json
        let mappings = [{
            "key"  : "1520",
            "value": "General contractors: residential and commercial"
        }, {
            "key"  : "1711",
            "value": "Heating, plumbing and air conditioning contractors"
        }, {
            "key"  : "1731",
            "value": "Electrical contractors"
        }, {
            "key"  : "1740",
            "value": "Masonry, stonework, tile setting, plastering, insulation"
        }, {
            "key"  : "1750",
            "value": "Carpentry "
        }, {
            "key"  : "1761",
            "value": "Roofing, siding and sheet metal work contractors"
        }, {
            "key"  : "1771",
            "value": "Concrete work contractors"
        }, {
            "key"  : "1799",
            "value": "Special trade contractors"
        }, {
            "key"  : "2741",
            "value": "Publishing and printing services"
        }, {
            "key"  : "2791",
            "value": "Typesetting, plate making and related services"
        }, {
            "key"  : "2842",
            "value": "Speciality cleaning, polishing and sanitation preparations"
        }, {
            "key"  : "3000",
            "value": "UNITED AIRLINES"
        }, {
            "key"  : "3001",
            "value": "AMERICAN AIRLINES"
        }, {
            "key"  : "3002",
            "value": "PAN AMERICAN"
        }, {
            "key"  : "3003",
            "value": "EUROFLY AIRLINES"
        }, {
            "key"  : "3004",
            "value": "DRAGON AIRLINES"
        }, {
            "key"  : "3005",
            "value": "BRITISH AIRWAYS"
        }, {
            "key"  : "3006",
            "value": "JAPAN AIR LINES"
        }, {
            "key"  : "3007",
            "value": "AIR FRANCE"
        }, {
            "key"  : "3008",
            "value": "LUFTHANSA"
        }, {
            "key"  : "3009",
            "value": "AIR CANADA"
        }, {
            "key"  : "3010",
            "value": "KLM (ROYAL DUTCH AIRLINES)"
        }, {
            "key"  : "3011",
            "value": "AEROFLOT"
        }, {
            "key"  : "3012",
            "value": "QANTAS"
        }, {
            "key"  : "3013",
            "value": "ALITALIA"
        }, {
            "key"  : "3014",
            "value": "SAUDI ARABIAN AIRLINES"
        }, {
            "key"  : "3015",
            "value": "SWISS INTERNATIONAL AIRLINES"
        }, {
            "key"  : "3016",
            "value": "SAS"
        }, {
            "key"  : "3017",
            "value": "SOUTH AFRICAN AIRWAYS"
        }, {
            "key"  : "3018",
            "value": "VARIG (BRAZIL)"
        }, {
            "key"  : "3019",
            "value": "GERMANWINGS"
        }, {
            "key"  : "3020",
            "value": "AIR INDIA"
        }, {
            "key"  : "3021",
            "value": "AIR ALGERIE"
        }, {
            "key"  : "3022",
            "value": "PHILIPPINE AIRLINES"
        }, {
            "key"  : "3023",
            "value": "MEXICANA"
        }, {
            "key"  : "3024",
            "value": "PAKISTAN INTERNATIONAL"
        }, {
            "key"  : "3025",
            "value": "AIR NEW ZEALAND "
        }, {
            "key"  : "3026",
            "value": "EMIRATES AIRLINES"
        }, {
            "key"  : "3027",
            "value": "UTA/INTERAIR"
        }, {
            "key"  : "3028",
            "value": "AIR MALTA"
        }, {
            "key"  : "3029",
            "value": "SN BRUSSELS AIRLINES"
        }, {
            "key"  : "3030",
            "value": "AEROLINEAS ARGENTINAS"
        }, {
            "key"  : "3031",
            "value": "OLYMPIC AIRWAYS"
        }, {
            "key"  : "3032",
            "value": "EL AL"
        }, {
            "key"  : "3033",
            "value": "ANSETT AIRLINES"
        }, {
            "key"  : "3034",
            "value": "ETIHAD AIRWAYS"
        }, {
            "key"  : "3035",
            "value": "TAP (PORTUGAL)"
        }, {
            "key"  : "3036",
            "value": "VASP (BRAZIL)"
        }, {
            "key"  : "3037",
            "value": "EGYPTAIR"
        }, {
            "key"  : "3038",
            "value": "KUWAIT AIRWAYS"
        }, {
            "key"  : "3039",
            "value": "AVIANCA"
        }, {
            "key"  : "3040",
            "value": "GULF AIR (BAHRAIN)"
        }, {
            "key"  : "3041",
            "value": "BALKAN-BULGARIAN AIRLINES"
        }, {
            "key"  : "3042",
            "value": "FINNAIR"
        }, {
            "key"  : "3043",
            "value": "AER LINGUS"
        }, {
            "key"  : "3044",
            "value": "AIR LANKA"
        }, {
            "key"  : "3045",
            "value": "NIGERIA AIRWAYS"
        }, {
            "key"  : "3046",
            "value": "CRUZEIRO DO SUL (BRAZIL)"
        }, {
            "key"  : "3047",
            "value": "TURKISH AIRLINES"
        }, {
            "key"  : "3048",
            "value": "ROYAL AIR MAROC"
        }, {
            "key"  : "3049",
            "value": "TUNIS AIR"
        }, {
            "key"  : "3050",
            "value": "ICELANDAIR"
        }, {
            "key"  : "3051",
            "value": "AUSTRIAN AIRLINES"
        }, {
            "key"  : "3052",
            "value": "LAN AIR"
        }, {
            "key"  : "3053",
            "value": "AVIACO (SPAIN)"
        }, {
            "key"  : "3054",
            "value": "LADECO (CHILE)"
        }, {
            "key"  : "3055",
            "value": "LAB (BOLIVIA)"
        }, {
            "key"  : "3056",
            "value": "JET AIRWAYS"
        }, {
            "key"  : "3057",
            "value": "VIRGIN AMERICA"
        }, {
            "key"  : "3058",
            "value": "DELTA"
        }, {
            "key"  : "3059",
            "value": "DBA AIRLINES"
        }, {
            "key"  : "3060",
            "value": "NORTHWEST "
        }, {
            "key"  : "3061",
            "value": "CONTINENTAL"
        }, {
            "key"  : "3062",
            "value": "HAPAG-LLOYD EXPRESS AIRLINES"
        }, {
            "key"  : "3063",
            "value": "US AIRWAYS"
        }, {
            "key"  : "3064",
            "value": "ADRIA AIRWAYS"
        }, {
            "key"  : "3065",
            "value": "AIRINTER"
        }, {
            "key"  : "3066",
            "value": "SOUTHWEST "
        }, {
            "key"  : "3067",
            "value": "VANGUARD AIRLINES"
        }, {
            "key"  : "3068",
            "value": "AIR ASTANA"
        }, {
            "key"  : "3069",
            "value": "SUN COUNTRY AIRLINES"
        }, {
            "key"  : "3071",
            "value": "AIR BRITISH COLUMBIA"
        }, {
            "key"  : "3072",
            "value": "CEBU PACIFIC AIRLINES"
        }, {
            "key"  : "3075",
            "value": "SINGAPORE AIRLINES"
        }, {
            "key"  : "3076",
            "value": "AEROMEXICO"
        }, {
            "key"  : "3077",
            "value": "THAI AIRWAYS"
        }, {
            "key"  : "3078",
            "value": "CHINA AIRLINES"
        }, {
            "key"  : "3079",
            "value": "JETSTAR AIRWAYS"
        }, {
            "key"  : "3081",
            "value": "NORDAIR"
        }, {
            "key"  : "3082",
            "value": "KOREAN AIRLINES"
        }, {
            "key"  : "3083",
            "value": "AIR AFRIQUE"
        }, {
            "key"  : "3084",
            "value": "EVA AIRLINES"
        }, {
            "key"  : "3085",
            "value": "MIDWEST EXPRESS AIRLINES"
        }, {
            "key"  : "3086",
            "value": "CARNIVAL AIRLINES"
        }, {
            "key"  : "3087",
            "value": "METRO AIRLINES"
        }, {
            "key"  : "3088",
            "value": "CROATIA AIR"
        }, {
            "key"  : "3089",
            "value": "TRANSAERO"
        }, {
            "key"  : "3090",
            "value": "UNI AIRWAYS CORPORATION"
        }, {
            "key"  : "3092",
            "value": "MIDWAY AIRLINES"
        }, {
            "key"  : "3094",
            "value": "ZAMBIA AIRWAYS"
        }, {
            "key"  : "3096",
            "value": "AIR ZIMBABWE"
        }, {
            "key"  : "3097",
            "value": "SPANAIR"
        }, {
            "key"  : "3098",
            "value": "ASIANA AIRLINES"
        }, {
            "key"  : "3099",
            "value": "CATHAY PACIFIC"
        }, {
            "key"  : "3100",
            "value": "MALAYSIAN AIRLINE SYSTEM"
        }, {
            "key"  : "3102",
            "value": "IBERIA"
        }, {
            "key"  : "3103",
            "value": "GARUDA (INDONESIA)"
        }, {
            "key"  : "3106",
            "value": "BRAATHENS S.A.F.E. (NORWAY)"
        }, {
            "key"  : "3110",
            "value": "WINGS AIRWAYS"
        }, {
            "key"  : "3111",
            "value": "BRITISH MIDLAND"
        }, {
            "key"  : "3112",
            "value": "WINDWARD ISLAND"
        }, {
            "key"  : "3115",
            "value": "TOWER AIR"
        }, {
            "key"  : "3117",
            "value": "VIASA"
        }, {
            "key"  : "3118",
            "value": "VALLEY AIRLINES"
        }, {
            "key"  : "3125",
            "value": "TAN"
        }, {
            "key"  : "3126",
            "value": "TALAIR"
        }, {
            "key"  : "3127",
            "value": "TACA INTERNATIONAL"
        }, {
            "key"  : "3129",
            "value": "SURINAM AIRWAYS"
        }, {
            "key"  : "3130",
            "value": "SUNWORLD INTERNATIONAL"
        }, {
            "key"  : "3131",
            "value": "VLM AIRLINES"
        }, {
            "key"  : "3132",
            "value": "FRONTIER AIRLINES"
        }, {
            "key"  : "3133",
            "value": "SUNBELT AIRLINES"
        }, {
            "key"  : "3135",
            "value": "SUDAN AIRWAYS"
        }, {
            "key"  : "3136",
            "value": "QATAR AIRWAYS"
        }, {
            "key"  : "3137",
            "value": "SINGLETON"
        }, {
            "key"  : "3138",
            "value": "SIMMONS AIRLINES"
        }, {
            "key"  : "3143",
            "value": "SCENIC AIRLINES"
        }, {
            "key"  : "3144",
            "value": "VIRGIN ATLANTIC"
        }, {
            "key"  : "3145",
            "value": "SAN JUAN AIRLINES"
        }, {
            "key"  : "3146",
            "value": "LUXAIR"
        }, {
            "key"  : "3148",
            "value": "AIR LITTORAL SA"
        }, {
            "key"  : "3151",
            "value": "AIR ZAIRE"
        }, {
            "key"  : "3154",
            "value": "PRINCEVILLE"
        }, {
            "key"  : "3156",
            "value": "GO FLY, LTD"
        }, {
            "key"  : "3159",
            "value": "PBA"
        }, {
            "key"  : "3161",
            "value": "ALL NIPPON AIRWAYS"
        }, {
            "key"  : "3164",
            "value": "NORONTAIR"
        }, {
            "key"  : "3165",
            "value": "NEW YORK HELICOPTER"
        }, {
            "key"  : "3167",
            "value": "AEROCONTINENTE"
        }, {
            "key"  : "3170",
            "value": "MOUNT COOK"
        }, {
            "key"  : "3171",
            "value": "CANADIAN AIRLINES INTERNATIONAL"
        }, {
            "key"  : "3172",
            "value": "NATIONAIR"
        }, {
            "key"  : "3174",
            "value": "JETBLUE AIRWAYS"
        }, {
            "key"  : "3175",
            "value": "MIDDLE EAST AIR"
        }, {
            "key"  : "3176",
            "value": "METROFLIGHT AIRLINES"
        }, {
            "key"  : "3177",
            "value": "AIRTRAN AIRWAYS"
        }, {
            "key"  : "3178",
            "value": "MESA AIR"
        }, {
            "key"  : "3180",
            "value": "WESTJET AIRLINES"
        }, {
            "key"  : "3181",
            "value": "MALEV"
        }, {
            "key"  : "3182",
            "value": "LOT (POLAND)"
        }, {
            "key"  : "3183",
            "value": "OMAN AVIATION SERVICES"
        }, {
            "key"  : "3184",
            "value": "LIAT"
        }, {
            "key"  : "3185",
            "value": "LAV (VENEZUELA)"
        }, {
            "key"  : "3186",
            "value": "LAP (PARAGUAY)"
        }, {
            "key"  : "3187",
            "value": "LACSA (COSTA RICA)"
        }, {
            "key"  : "3188",
            "value": "VIRGIN EXPRESS"
        }, {
            "key"  : "3190",
            "value": "JUGOSLAV AIR"
        }, {
            "key"  : "3191",
            "value": "ISLAND AIRLINES"
        }, {
            "key"  : "3192",
            "value": "IRAN AIR"
        }, {
            "key"  : "3193",
            "value": "INDIAN AIRLINES"
        }, {
            "key"  : "3196",
            "value": "HAWAIIAN AIR"
        }, {
            "key"  : "3197",
            "value": "HAVASU AIRLINES"
        }, {
            "key"  : "3200",
            "value": "GUYANA AIRWAYS"
        }, {
            "key"  : "3203",
            "value": "GOLDEN PACIFIC AIR"
        }, {
            "key"  : "3204",
            "value": "FREEDOM AIR"
        }, {
            "key"  : "3206",
            "value": "CHINA EASTERN AIRLINES"
        }, {
            "key"  : "3211",
            "value": "NORWEGIAN AIR SHUTTLE"
        }, {
            "key"  : "3212",
            "value": "DOMINICANA"
        }, {
            "key"  : "3213",
            "value": "BRAATHENS REGIONAL AIR"
        }, {
            "key"  : "3215",
            "value": "DAN AIR SERVICES"
        }, {
            "key"  : "3216",
            "value": "CUMBERLAND AIRLINES"
        }, {
            "key"  : "3217",
            "value": "CSA"
        }, {
            "key"  : "3218",
            "value": "CROWN AIR"
        }, {
            "key"  : "3219",
            "value": "COPA"
        }, {
            "key"  : "3220",
            "value": "COMPANIA FAUCETT"
        }, {
            "key"  : "3221",
            "value": "TRANSPORTES AEROS MILITARES ECUATORIANOS"
        }, {
            "key"  : "3222",
            "value": "COMMAND AIRWAYS"
        }, {
            "key"  : "3223",
            "value": "COMAIR"
        }, {
            "key"  : "3226",
            "value": "SKYWAYS AIR"
        }, {
            "key"  : "3228",
            "value": "CAYMAN AIRWAYS"
        }, {
            "key"  : "3229",
            "value": "SAETA-SOCIEDAD ECUATORIANOS DE TRANSPORTES AEREOS"
        }, {
            "key"  : "3231",
            "value": "SAHSA-SERVICIO AERO DE HONDURAS"
        }, {
            "key"  : "3233",
            "value": "CAPITOL AIR"
        }, {
            "key"  : "3234",
            "value": "CARIBBEAN AIRLINES"
        }, {
            "key"  : "3235",
            "value": "BROCKWAY AIR"
        }, {
            "key"  : "3236",
            "value": "AIR ARABIA"
        }, {
            "key"  : "3238",
            "value": "BEMIDJI AVIATION"
        }, {
            "key"  : "3239",
            "value": "BAR HARBOR AIRLINES"
        }, {
            "key"  : "3240",
            "value": "BAHAMASAIR"
        }, {
            "key"  : "3241",
            "value": "AVIATECA (GUATEMALA)"
        }, {
            "key"  : "3242",
            "value": "AVENSA"
        }, {
            "key"  : "3243",
            "value": "AUSTRIAN AIR SERVICE"
        }, {
            "key"  : "3245",
            "value": "EASYJET AIRLINES"
        }, {
            "key"  : "3246",
            "value": "RYANAIR"
        }, {
            "key"  : "3247",
            "value": "GOL AIRLINES"
        }, {
            "key"  : "3248",
            "value": "TAM AIRLINES"
        }, {
            "key"  : "3251",
            "value": "ALOHA AIRLINES"
        }, {
            "key"  : "3252",
            "value": "ALM"
        }, {
            "key"  : "3253",
            "value": "AMERICA WEST"
        }, {
            "key"  : "3254",
            "value": "US AIR SHUTTLE"
        }, {
            "key"  : "3256",
            "value": "ALASKA AIRLINES"
        }, {
            "key"  : "3259",
            "value": "AMERICAN TRANS AIR"
        }, {
            "key"  : "3260",
            "value": "SPIRIT AIRLINES"
        }, {
            "key"  : "3261",
            "value": "AIR CHINA"
        }, {
            "key"  : "3262",
            "value": "RENO AIR, INC."
        }, {
            "key"  : "3263",
            "value": "AERO SERVICIO CARABOBO"
        }, {
            "key"  : "3266",
            "value": "AIR SEYCHELLES"
        }, {
            "key"  : "3267",
            "value": "AIR PANAMA"
        }, {
            "key"  : "3273",
            "value": "RICA HOTELS"
        }, {
            "key"  : "3274",
            "value": "INTER NOR HOTELS"
        }, {
            "key"  : "3280",
            "value": "AIR JAMAICA"
        }, {
            "key"  : "3281",
            "value": "AIR DJIBOUTI"
        }, {
            "key"  : "3282",
            "value": "AIR DJIBOUTI"
        }, {
            "key"  : "3284",
            "value": "AERO VIRGIN ISLANDS"
        }, {
            "key"  : "3285",
            "value": "AEROPERU"
        }, {
            "key"  : "3286",
            "value": "AEROLINEAS NICARAGUENSIS"
        }, {
            "key"  : "3287",
            "value": "AERO COACH AVIATION"
        }, {
            "key"  : "3292",
            "value": "CYPRUS AIRWAYS"
        }, {
            "key"  : "3293",
            "value": "EQUATORIANA"
        }, {
            "key"  : "3294",
            "value": "ETHIOPIAN AIRLINES"
        }, {
            "key"  : "3295",
            "value": "KENYA AIRWAYS"
        }, {
            "key"  : "3296",
            "value": "AIR BERLIN"
        }, {
            "key"  : "3297",
            "value": "TAROM ROMANIAN AIR TRANSPORT"
        }, {
            "key"  : "3298",
            "value": "AIR MAURITIUS"
        }, {
            "key"  : "3299",
            "value": "WIDEROES FLYVESELSKAP"
        }, {
            "key"  : "3300",
            "value": "AZUL AIR"
        }, {
            "key"  : "3301",
            "value": "WIZZ AIR"
        }, {
            "key"  : "3302",
            "value": "FLYBE LTD"
        }, {
            "key"  : "3351",
            "value": "AFFILIATED AUTO RENTAL"
        }, {
            "key"  : "3352",
            "value": "AMERICAN INTL RENT-A-CAR"
        }, {
            "key"  : "3353",
            "value": "BROOKS RENT-A-CAR"
        }, {
            "key"  : "3354",
            "value": "ACTION AUTO RENTAL"
        }, {
            "key"  : "3355",
            "value": "SIXT CAR RENTAL"
        }, {
            "key"  : "3357",
            "value": "HERTZ "
        }, {
            "key"  : "3359",
            "value": "PAYLESS CAR RENTAL"
        }, {
            "key"  : "3360",
            "value": "SNAPPY CAR RENTAL"
        }, {
            "key"  : "3361",
            "value": "AIRWAYS RENT-A-CAR"
        }, {
            "key"  : "3362",
            "value": "ALTRA AUTO RENTAL"
        }, {
            "key"  : "3364",
            "value": "AGENCY RENT-A-CAR"
        }, {
            "key"  : "3366",
            "value": "BUDGET RENT-A-CAR"
        }, {
            "key"  : "3368",
            "value": "HOLIDAY RENT-A-CAR"
        }, {
            "key"  : "3370",
            "value": "RENT-A-WRECK"
        }, {
            "key"  : "3374",
            "value": "ACCENT RENT-A-CAR"
        }, {
            "key"  : "3376",
            "value": "AJAX RENT-A-CAR"
        }, {
            "key"  : "3380",
            "value": "TRIANGLE RENT A CAR"
        }, {
            "key"  : "3381",
            "value": "EUROPCAR"
        }, {
            "key"  : "3385",
            "value": "TROPICAL RENT-A-CAR"
        }, {
            "key"  : "3386",
            "value": "SHOWCASE RENTAL CARS"
        }, {
            "key"  : "3387",
            "value": "ALAMO RENT-A-CAR"
        }, {
            "key"  : "3388",
            "value": "MERCHANTS RENT-A-CAR"
        }, {
            "key"  : "3389",
            "value": "AVIS RENT-A-CAR"
        }, {
            "key"  : "3390",
            "value": "DOLLAR RENT-A-CAR"
        }, {
            "key"  : "3391",
            "value": "EUROPE BY CAR"
        }, {
            "key"  : "3393",
            "value": "NATIONAL CAR RENTAL"
        }, {
            "key"  : "3394",
            "value": "KEMWELL GROUP RENT-A-CAR"
        }, {
            "key"  : "3395",
            "value": "THRIFTY CAR RENTAL"
        }, {
            "key"  : "3396",
            "value": "TILDEN RENT-A-CAR"
        }, {
            "key"  : "3398",
            "value": "ECONO-CAR RENT-A-CAR"
        }, {
            "key"  : "3400",
            "value": "AUTO HOST RENTAL CARS"
        }, {
            "key"  : "3405",
            "value": "ENTERPRISE RENT-A-CAR"
        }, {
            "key"  : "3409",
            "value": "GENERAL RENT-A-CAR"
        }, {
            "key"  : "3412",
            "value": "A-1 RENT-A-CAR"
        }, {
            "key"  : "3414",
            "value": "GODFREY NATIONAL RENT-A-CAR"
        }, {
            "key"  : "3420",
            "value": "ANSA INTERNATIONAL RENT-A-CAR"
        }, {
            "key"  : "3421",
            "value": "ALLSTATE RENT-A-CAR"
        }, {
            "key"  : "3423",
            "value": "AVCAR RENT-A-CAR"
        }, {
            "key"  : "3425",
            "value": "AUTOMATE RENT-A-CAR"
        }, {
            "key"  : "3427",
            "value": "AVON RENT-A-CAR"
        }, {
            "key"  : "3428",
            "value": "CAREY RENT-A-CAR"
        }, {
            "key"  : "3429",
            "value": "INSURANCE RENT-A-CAR"
        }, {
            "key"  : "3430",
            "value": "MAJOR RENT-A-CAR"
        }, {
            "key"  : "3431",
            "value": "REPLACEMENT RENT-A-CAR"
        }, {
            "key"  : "3432",
            "value": "RESERVE RENT-A-CAR"
        }, {
            "key"  : "3433",
            "value": "UGLY DUCKLING RENT-A-CAR"
        }, {
            "key"  : "3434",
            "value": "USA RENT-A-CAR"
        }, {
            "key"  : "3435",
            "value": "VALUE RENT-A-CAR"
        }, {
            "key"  : "3436",
            "value": "AUTOHANSA RENT-A-CAR"
        }, {
            "key"  : "3437",
            "value": "CITE RENT-A-CAR"
        }, {
            "key"  : "3438",
            "value": "INTERENT RENT-A-CAR"
        }, {
            "key"  : "3439",
            "value": "MILLEVILLE RENT-A-CAR"
        }, {
            "key"  : "3441",
            "value": "ADVANTAGE RENT A CAR"
        }, {
            "key"  : "3501",
            "value": "HOLIDAY INNS"
        }, {
            "key"  : "3502",
            "value": "BEST WESTERN HOTELS"
        }, {
            "key"  : "3503",
            "value": "SHERATON"
        }, {
            "key"  : "3504",
            "value": "HILTON HOTELS"
        }, {
            "key"  : "3505",
            "value": "FORTE HOTELS"
        }, {
            "key"  : "3506",
            "value": "GOLDEN TULIP HOTELS"
        }, {
            "key"  : "3507",
            "value": "FRIENDSHIP INNS"
        }, {
            "key"  : "3508",
            "value": "QUALITY INNS"
        }, {
            "key"  : "3509",
            "value": "MARRIOTT"
        }, {
            "key"  : "3510",
            "value": "DAYS INNS"
        }, {
            "key"  : "3511",
            "value": "ARABELLA HOTELS"
        }, {
            "key"  : "3512",
            "value": "INTERCONTINENTAL HOTELS"
        }, {
            "key"  : "3513",
            "value": "WESTIN"
        }, {
            "key"  : "3514",
            "value": "AMERISUITES"
        }, {
            "key"  : "3515",
            "value": "RODEWAY INNS"
        }, {
            "key"  : "3516",
            "value": "LA QUINTA INN AND SUITES"
        }, {
            "key"  : "3517",
            "value": "AMERICANA HOTELS"
        }, {
            "key"  : "3518",
            "value": "SOL HOTELS"
        }, {
            "key"  : "3519",
            "value": "PULLMAN INTERNATIONAL HOTELS"
        }, {
            "key"  : "3520",
            "value": "MERIDIEN HOTELS"
        }, {
            "key"  : "3521",
            "value": "CREST HOTELS"
        }, {
            "key"  : "3522",
            "value": "TOKYO HOTEL"
        }, {
            "key"  : "3523",
            "value": "PENINSULA HOTELS"
        }, {
            "key"  : "3524",
            "value": "WELCOMGROUP HOTELS"
        }, {
            "key"  : "3525",
            "value": "DUNFEY HOTELS"
        }, {
            "key"  : "3526",
            "value": "PRINCE HOTELS"
        }, {
            "key"  : "3527",
            "value": "DOWNTOWNER-PASSPORT HOTEL"
        }, {
            "key"  : "3528",
            "value": "RED LION INNS"
        }, {
            "key"  : "3529",
            "value": "CP HOTELS "
        }, {
            "key"  : "3530",
            "value": "RENAISSANCE HOTELS"
        }, {
            "key"  : "3531",
            "value": "KAUAI COCONUT BEACH RESORT"
        }, {
            "key"  : "3532",
            "value": "ROYAL KONA RESORT"
        }, {
            "key"  : "3533",
            "value": "HOTEL IBIS"
        }, {
            "key"  : "3534",
            "value": "SOUTHERN PACIFIC HOTELS"
        }, {
            "key"  : "3535",
            "value": "HILTON INTERNATIONAL"
        }, {
            "key"  : "3536",
            "value": "AMFAC HOTELS"
        }, {
            "key"  : "3537",
            "value": "ANA HOTELS"
        }, {
            "key"  : "3538",
            "value": "CONCORDE HOTELS"
        }, {
            "key"  : "3539",
            "value": "SUMMERFIELD SUITES HOTEL"
        }, {
            "key"  : "3540",
            "value": "IBEROTEL HOTELS"
        }, {
            "key"  : "3541",
            "value": "HOTEL OKURA"
        }, {
            "key"  : "3542",
            "value": "ROYAL HOTELS"
        }, {
            "key"  : "3543",
            "value": "FOUR SEASONS HOTELS"
        }, {
            "key"  : "3544",
            "value": "CIGA HOTELS"
        }, {
            "key"  : "3545",
            "value": "SHANGRI-LA INTERNATIONAL"
        }, {
            "key"  : "3546",
            "value": "HOTEL SIERRA"
        }, {
            "key"  : "3547",
            "value": "THE BREAKERS RESORT"
        }, {
            "key"  : "3548",
            "value": "HOTELS MELIA"
        }, {
            "key"  : "3549",
            "value": "AUBERGE DES GOVERNEURS"
        }, {
            "key"  : "3550",
            "value": "REGAL 8 INNS"
        }, {
            "key"  : "3551",
            "value": "MIRAGE HOTEL AND CASINO"
        }, {
            "key"  : "3552",
            "value": "COAST HOTELS"
        }, {
            "key"  : "3553",
            "value": "PARK INN BY RADISSON"
        }, {
            "key"  : "3554",
            "value": "PINEHURST RESORT"
        }, {
            "key"  : "3555",
            "value": "TREASURE ISLAND HOTEL AND CASINO"
        }, {
            "key"  : "3556",
            "value": "BARTON CREEK RESORT"
        }, {
            "key"  : "3557",
            "value": "MANHATTAN EAST SUITE HOTELS"
        }, {
            "key"  : "3558",
            "value": "JOLLY HOTELS"
        }, {
            "key"  : "3559",
            "value": "CANDLEWOOD SUITES"
        }, {
            "key"  : "3560",
            "value": "ALADDIN RESORT AND CASINO"
        }, {
            "key"  : "3561",
            "value": "GOLDEN NUGGET"
        }, {
            "key"  : "3562",
            "value": "COMFORT INNS"
        }, {
            "key"  : "3563",
            "value": "JOURNEY'S END MOTELS"
        }, {
            "key"  : "3564",
            "value": "SAM'S TOWN HOTEL AND CASINO"
        }, {
            "key"  : "3565",
            "value": "RELAX INNS"
        }, {
            "key"  : "3566",
            "value": "GARDEN PLACE HOTEL"
        }, {
            "key"  : "3567",
            "value": "SOHO FRAND HOTEL"
        }, {
            "key"  : "3568",
            "value": "LADBROKE HOTELS"
        }, {
            "key"  : "3569",
            "value": "TRIBECA GRAND HOTEL"
        }, {
            "key"  : "3570",
            "value": "FORUM HOTELS"
        }, {
            "key"  : "3571",
            "value": "GRAND WAILEA RESORT"
        }, {
            "key"  : "3572",
            "value": "MIYAKO HOTEL"
        }, {
            "key"  : "3573",
            "value": "SANDMAN HOTELS"
        }, {
            "key"  : "3574",
            "value": "VENTURE INN"
        }, {
            "key"  : "3575",
            "value": "VAGABOND HOTELS"
        }, {
            "key"  : "3576",
            "value": "LA QUINTA RESORT"
        }, {
            "key"  : "3577",
            "value": "MANDARIN ORIENTAL HOTEL"
        }, {
            "key"  : "3578",
            "value": "FRANKENMUTH BAVARIAN"
        }, {
            "key"  : "3579",
            "value": "HOTEL MERCURE"
        }, {
            "key"  : "3580",
            "value": "HOTEL DEL CORONADO"
        }, {
            "key"  : "3581",
            "value": "DELTA HOTELS"
        }, {
            "key"  : "3582",
            "value": "CALIFORNIA HOTEL AND CASINO"
        }, {
            "key"  : "3583",
            "value": "RADISSON BLU"
        }, {
            "key"  : "3584",
            "value": "PRINCESS HOTELS INTERNATIONAL"
        }, {
            "key"  : "3585",
            "value": "HUNGAR HOTELS"
        }, {
            "key"  : "3586",
            "value": "SOKOS HOTEL"
        }, {
            "key"  : "3587",
            "value": "DORAL HOTELS"
        }, {
            "key"  : "3588",
            "value": "HELMSLEY HOTELS"
        }, {
            "key"  : "3589",
            "value": "DORAL GOLF RESORT"
        }, {
            "key"  : "3590",
            "value": "FAIRMONT HOTELS"
        }, {
            "key"  : "3591",
            "value": "SONESTA HOTELS"
        }, {
            "key"  : "3592",
            "value": "OMNI HOTELS"
        }, {
            "key"  : "3593",
            "value": "CUNARD HOTELS"
        }, {
            "key"  : "3594",
            "value": "ARIZONA BILTMORE"
        }, {
            "key"  : "3595",
            "value": "HOSPITALITY INNS"
        }, {
            "key"  : "3596",
            "value": "WYNN LAS VEGAS"
        }, {
            "key"  : "3597",
            "value": "RIVERSIDE RESORT AND CASINO"
        }, {
            "key"  : "3598",
            "value": "REGENT INTERNATIONAL HOTELS"
        }, {
            "key"  : "3599",
            "value": "PANNONIA HOTELS"
        }, {
            "key"  : "3600",
            "value": "SADDLEBROOK RESORT TAMPA"
        }, {
            "key"  : "3601",
            "value": "TRADEWINDS RESORTS"
        }, {
            "key"  : "3602",
            "value": "HUDSON HOTEL"
        }, {
            "key"  : "3603",
            "value": "NOAH'S HOTEL"
        }, {
            "key"  : "3604",
            "value": "HILTON GARDEN INN"
        }, {
            "key"  : "3605",
            "value": "JURYS DOYLE HOTEL GROUP"
        }, {
            "key"  : "3606",
            "value": "JEFFERSON HOTEL"
        }, {
            "key"  : "3607",
            "value": "FONTAINEBLEAU RESORT"
        }, {
            "key"  : "3608",
            "value": "GAYLORD OPRYLAND"
        }, {
            "key"  : "3609",
            "value": "GAYLORD PALMS"
        }, {
            "key"  : "3610",
            "value": "GAYLORD TEXAN"
        }, {
            "key"  : "3611",
            "value": "C MON INN"
        }, {
            "key"  : "3612",
            "value": "MOEVENPICK HOTELS"
        }, {
            "key"  : "3613",
            "value": "MICROTEL INNS & SUITES"
        }, {
            "key"  : "3614",
            "value": "AMERICINN"
        }, {
            "key"  : "3615",
            "value": "TRAVELODGE"
        }, {
            "key"  : "3616",
            "value": "HERMITAGE HOTEL"
        }, {
            "key"  : "3617",
            "value": "AMERICA'S BEST VALUE INN"
        }, {
            "key"  : "3618",
            "value": "GREAT WOLF"
        }, {
            "key"  : "3619",
            "value": "ALOFT"
        }, {
            "key"  : "3620",
            "value": "BINION'S HORSESHOE CLUB"
        }, {
            "key"  : "3621",
            "value": "EXTENDED STAY"
        }, {
            "key"  : "3622",
            "value": "MERLIN HOTELS"
        }, {
            "key"  : "3623",
            "value": "DORINT HOTELS"
        }, {
            "key"  : "3624",
            "value": "LADY LUCK HOTEL AND CASINO"
        }, {
            "key"  : "3625",
            "value": "HOTEL UNIVERSALE"
        }, {
            "key"  : "3626",
            "value": "STUDIO PLUS"
        }, {
            "key"  : "3627",
            "value": "EXTENDED STAY AMERICA"
        }, {
            "key"  : "3628",
            "value": "EXCALIBUR HOTEL AND CASINO"
        }, {
            "key"  : "3629",
            "value": "DAN HOTELS"
        }, {
            "key"  : "3630",
            "value": "EXTENDED STAY DELUXE"
        }, {
            "key"  : "3631",
            "value": "SLEEP INN"
        }, {
            "key"  : "3632",
            "value": "THE PHOENICIAN"
        }, {
            "key"  : "3633",
            "value": "RANK HOTELS"
        }, {
            "key"  : "3634",
            "value": "SWISSOTEL"
        }, {
            "key"  : "3635",
            "value": "RESO HOTELS"
        }, {
            "key"  : "3636",
            "value": "SAROVA HOTELS"
        }, {
            "key"  : "3637",
            "value": "RAMADA INNS"
        }, {
            "key"  : "3638",
            "value": "HOWARD JOHNSON"
        }, {
            "key"  : "3639",
            "value": "MOUNT CHARLOTTE THISTLE"
        }, {
            "key"  : "3640",
            "value": "HYATT HOTELS"
        }, {
            "key"  : "3641",
            "value": "SOFITEL HOTELS"
        }, {
            "key"  : "3642",
            "value": "NOVOTEL HOTELS"
        }, {
            "key"  : "3643",
            "value": "STEIGENBERGER HOTELS"
        }, {
            "key"  : "3644",
            "value": "ECONO LODGES"
        }, {
            "key"  : "3645",
            "value": "QUEENS MOAT HOUSES"
        }, {
            "key"  : "3646",
            "value": "SWALLOW HOTELS"
        }, {
            "key"  : "3647",
            "value": "HUSA HOTELS"
        }, {
            "key"  : "3648",
            "value": "DE VERE HOTELS"
        }, {
            "key"  : "3649",
            "value": "RADISSON  HOTELS"
        }, {
            "key"  : "3650",
            "value": "RED ROOF INNS"
        }, {
            "key"  : "3651",
            "value": "IMPERIAL LONDON HOTEL"
        }, {
            "key"  : "3652",
            "value": "EMBASSY HOTELS"
        }, {
            "key"  : "3653",
            "value": "PENTA HOTELS"
        }, {
            "key"  : "3654",
            "value": "LOEWS HOTELS"
        }, {
            "key"  : "3655",
            "value": "SCANDIC HOTELS"
        }, {
            "key"  : "3656",
            "value": "SARA HOTELS"
        }, {
            "key"  : "3657",
            "value": "OBEROI HOTELS"
        }, {
            "key"  : "3658",
            "value": "NEW OTANI HOTELS"
        }, {
            "key"  : "3659",
            "value": "TAJ HOTELS INTERNATIONAL"
        }, {
            "key"  : "3660",
            "value": "KNIGHTS INNS"
        }, {
            "key"  : "3661",
            "value": "METROPOLE HOTELS"
        }, {
            "key"  : "3662",
            "value": "CIRCUS CIRCUS HOTEL AND CASINO"
        }, {
            "key"  : "3663",
            "value": "HOTELES EL PRESIDENTE"
        }, {
            "key"  : "3664",
            "value": "FLAG INN"
        }, {
            "key"  : "3665",
            "value": "HAMPTON INN"
        }, {
            "key"  : "3666",
            "value": "STAKIS HOTELS"
        }, {
            "key"  : "3667",
            "value": "LUXOR HOTEL AND CASINO"
        }, {
            "key"  : "3668",
            "value": "MARITIM HOTELS"
        }, {
            "key"  : "3669",
            "value": "ELDORADO HOTEL AND CASINO"
        }, {
            "key"  : "3670",
            "value": "ARCADE HOTELS"
        }, {
            "key"  : "3671",
            "value": "ARCTIA HOTELS"
        }, {
            "key"  : "3672",
            "value": "CAMPANILE HOTELS"
        }, {
            "key"  : "3673",
            "value": "IBUSZ HOTELS"
        }, {
            "key"  : "3674",
            "value": "RANTASIPI HOTELS"
        }, {
            "key"  : "3675",
            "value": "INTERHOTEL CEDOK"
        }, {
            "key"  : "3676",
            "value": "MONTE CARLO HOTEL AND CASINO"
        }, {
            "key"  : "3677",
            "value": "CLIMAT DE FRANCE HOTELS"
        }, {
            "key"  : "3678",
            "value": "CUMULUS HOTELS"
        }, {
            "key"  : "3679",
            "value": "SILVER LEGACY HOTEL AND CASINO"
        }, {
            "key"  : "3680",
            "value": "HOTEIS OTHAN"
        }, {
            "key"  : "3681",
            "value": "ADAMS MARK HOTELS"
        }, {
            "key"  : "3682",
            "value": "SAHARA HOTEL AND CASINO"
        }, {
            "key"  : "3683",
            "value": "BRADBURY SUITES"
        }, {
            "key"  : "3684",
            "value": "BUDGET HOST INNS"
        }, {
            "key"  : "3685",
            "value": "BUDGETEL HOTELS"
        }, {
            "key"  : "3686",
            "value": "SUSSE CHALET"
        }, {
            "key"  : "3687",
            "value": "CLARION HOTEL"
        }, {
            "key"  : "3688",
            "value": "COMPRI HOTEL"
        }, {
            "key"  : "3689",
            "value": "CONSORT HOTELS"
        }, {
            "key"  : "3690",
            "value": "COURTYARD BY MARRIOTT"
        }, {
            "key"  : "3691",
            "value": "DILLON INNS"
        }, {
            "key"  : "3692",
            "value": "DOUBLETREE HOTELS"
        }, {
            "key"  : "3693",
            "value": "DRURY INN"
        }, {
            "key"  : "3694",
            "value": "ECONOMY INNS OF AMERICA"
        }, {
            "key"  : "3695",
            "value": "EMBASSY SUITES"
        }, {
            "key"  : "3696",
            "value": "EXCEL INN"
        }, {
            "key"  : "3697",
            "value": "FAIRFIELD HOTELS"
        }, {
            "key"  : "3698",
            "value": "HARLEY HOTELS"
        }, {
            "key"  : "3699",
            "value": "MIDWAY MOTOR LODGE"
        }, {
            "key"  : "3700",
            "value": "MOTEL 6"
        }, {
            "key"  : "3701",
            "value": "LA MANSION DEL RIO"
        }, {
            "key"  : "3702",
            "value": "THE REGISTRY HOTELS"
        }, {
            "key"  : "3703",
            "value": "RESIDENCE INN"
        }, {
            "key"  : "3704",
            "value": "ROYCE HOTELS"
        }, {
            "key"  : "3705",
            "value": "SANDMAN INN"
        }, {
            "key"  : "3706",
            "value": "SHILO INN"
        }, {
            "key"  : "3707",
            "value": "SHONEY'S INN"
        }, {
            "key"  : "3708",
            "value": "VIRGIN RIVER HOTEL AND CASINO"
        }, {
            "key"  : "3709",
            "value": "SUPER 8 MOTELS"
        }, {
            "key"  : "3710",
            "value": "THE RITZ-CARLTON"
        }, {
            "key"  : "3711",
            "value": "FLAG INNS (AUSTRALIA)"
        }, {
            "key"  : "3712",
            "value": "BUFFALO BILL'S HOTEL AND CASINO"
        }, {
            "key"  : "3713",
            "value": "QUALITY PACIFIC HOTEL"
        }, {
            "key"  : "3714",
            "value": "FOUR SEASONS HOTEL (AUSTRALIA)"
        }, {
            "key"  : "3715",
            "value": "FAIRFIELD INN"
        }, {
            "key"  : "3716",
            "value": "CARLTON HOTELS"
        }, {
            "key"  : "3717",
            "value": "CITY LODGE HOTELS"
        }, {
            "key"  : "3718",
            "value": "KAROS HOTELS"
        }, {
            "key"  : "3719",
            "value": "PROTEA HOTELS"
        }, {
            "key"  : "3720",
            "value": "SOUTHERN SUN HOTELS"
        }, {
            "key"  : "3721",
            "value": "CONRAD HOTELS"
        }, {
            "key"  : "3722",
            "value": "WYNDHAM"
        }, {
            "key"  : "3723",
            "value": "RICA HOTLES"
        }, {
            "key"  : "3724",
            "value": "INTER NOR HOTELS"
        }, {
            "key"  : "3725",
            "value": "SEA PINES RESORT"
        }, {
            "key"  : "3726",
            "value": "RIO SUITES"
        }, {
            "key"  : "3727",
            "value": "BROADMOOR HOTEL"
        }, {
            "key"  : "3728",
            "value": "BALLY'S HOTEL AND CASINO"
        }, {
            "key"  : "3729",
            "value": "JOHN ASCUAGA'S NUGGET"
        }, {
            "key"  : "3730",
            "value": "MGM GRAND HOTEL"
        }, {
            "key"  : "3731",
            "value": "HARRAH'S HOTELS AND CASINOS"
        }, {
            "key"  : "3732",
            "value": "OPRYLAND HOTEL"
        }, {
            "key"  : "3733",
            "value": "BOCA RATON RESORT"
        }, {
            "key"  : "3734",
            "value": "HARVEY BRISTOL HOTELS"
        }, {
            "key"  : "3735",
            "value": "MASTERS ECONOMY INNS"
        }, {
            "key"  : "3736",
            "value": "COLORADO BELLE EDGEWATER RESORT"
        }, {
            "key"  : "3737",
            "value": "RIVIERA HOTEL AND CASINO"
        }, {
            "key"  : "3738",
            "value": "TROPICANA RESORT AND CASINO"
        }, {
            "key"  : "3739",
            "value": "WOODSIDE HOTELS AND RESORTS"
        }, {
            "key"  : "3740",
            "value": "TOWNEPLACE SUITES"
        }, {
            "key"  : "3741",
            "value": "MILLENNIUM HOTELS"
        }, {
            "key"  : "3742",
            "value": "CLUB MED"
        }, {
            "key"  : "3743",
            "value": "BILTMORE HOTEL AND SUITES"
        }, {
            "key"  : "3744",
            "value": "CAREFREE RESORTS"
        }, {
            "key"  : "3745",
            "value": "ST. REGIS HOTEL"
        }, {
            "key"  : "3746",
            "value": "THE ELIOT HOTEL"
        }, {
            "key"  : "3747",
            "value": "CLUB CORP/CLUB RESORTS"
        }, {
            "key"  : "3748",
            "value": "WELLESLEY INNS"
        }, {
            "key"  : "3749",
            "value": "THE BEVERLY HILLS HOTEL"
        }, {
            "key"  : "3750",
            "value": "CROWNE PLAZA HOTELS"
        }, {
            "key"  : "3751",
            "value": "HOMEWOOD SUITES"
        }, {
            "key"  : "3752",
            "value": "PEABODY HOTELS"
        }, {
            "key"  : "3753",
            "value": "GREENBRIAR RESORTS"
        }, {
            "key"  : "3754",
            "value": "AMELIA ISLAND PLANTATION"
        }, {
            "key"  : "3755",
            "value": "THE HOMESTEAD"
        }, {
            "key"  : "3756",
            "value": "SOUTH SEAS RESORTS"
        }, {
            "key"  : "3757",
            "value": "CANYON RANCH"
        }, {
            "key"  : "3758",
            "value": "KAHALA MANDARIN ORIENTAL HOTEL"
        }, {
            "key"  : "3759",
            "value": "THE ORCHID AT MAUNA LAI"
        }, {
            "key"  : "3760",
            "value": "HALEKULANI HOTEL/WAIKIKI PARC"
        }, {
            "key"  : "3761",
            "value": "PRIMADONNA HOTEL AND CASINO"
        }, {
            "key"  : "3762",
            "value": "WHISKEY PETE'S HOTEL AND CASINO"
        }, {
            "key"  : "3763",
            "value": "CHATEAU ELAN WINERY AND RESORT"
        }, {
            "key"  : "3764",
            "value": "BEAU RIVAGE HOTEL AND CASINO"
        }, {
            "key"  : "3765",
            "value": "BELLAGIO"
        }, {
            "key"  : "3766",
            "value": "FREMONT HOTEL AND CASINO"
        }, {
            "key"  : "3767",
            "value": "MAIN STREET HOTEL AND CASINO"
        }, {
            "key"  : "3768",
            "value": "SILVER STAR HOTEL AND CASINO"
        }, {
            "key"  : "3769",
            "value": "STRATOSPHERE HOTEL AND CASINO"
        }, {
            "key"  : "3770",
            "value": "SPRINGHILL SUITES"
        }, {
            "key"  : "3771",
            "value": "CAESARS HOTEL AND CASINO"
        }, {
            "key"  : "3772",
            "value": "NEMACOLIN WOODLANDS"
        }, {
            "key"  : "3773",
            "value": "THE VENETIAN RESORT HOTEL AND CASINO"
        }, {
            "key"  : "3774",
            "value": "NEWYORK-NEWYORKHOTELANDCASINO"
        }, {
            "key"  : "3775",
            "value": "SANDS RESORT"
        }, {
            "key"  : "3776",
            "value": "NEVELE GRAND RESORT AND COUNTRY CLUB"
        }, {
            "key"  : "3777",
            "value": "MANDALAY BAY RESORT"
        }, {
            "key"  : "3778",
            "value": "FOUR POINTS HOTELS"
        }, {
            "key"  : "3779",
            "value": "W HOTELS"
        }, {
            "key"  : "3780",
            "value": "DISNEY RESORTS"
        }, {
            "key"  : "3781",
            "value": "PATRICIA GRAND RESORT HOTELS"
        }, {
            "key"  : "3782",
            "value": "ROSEN HOTELS AND RESORTS"
        }, {
            "key"  : "3783",
            "value": "TOWN AND COUNTRY RESORT & CONVENTION CENTER"
        }, {
            "key"  : "3784",
            "value": "FIRST HOSPITALITY HOTELS"
        }, {
            "key"  : "3785",
            "value": "OUTRIGGER HOTELS AND RESORTS"
        }, {
            "key"  : "3786",
            "value": "OHANA HOTELS OF HAWAII"
        }, {
            "key"  : "3787",
            "value": "CARIBE ROYAL RESORTS"
        }, {
            "key"  : "3788",
            "value": "ALA MOANA HOTEL"
        }, {
            "key"  : "3789",
            "value": "SMUGGLER'S NOTCH RESORT"
        }, {
            "key"  : "3790",
            "value": "RAFFLES HOTELS"
        }, {
            "key"  : "3791",
            "value": "STAYBRIDGE SUITES"
        }, {
            "key"  : "3792",
            "value": "CLARIDGE CASINO HOTEL"
        }, {
            "key"  : "3793",
            "value": "FLAMINGO HOTELS"
        }, {
            "key"  : "3794",
            "value": "GRAND CASINO HOTELS"
        }, {
            "key"  : "3795",
            "value": "PARIS LAS VEGAS HOTEL"
        }, {
            "key"  : "3796",
            "value": "PEPPERMILL HOTEL CASINO"
        }, {
            "key"  : "3797",
            "value": "ATLANTIC CITY HILTON RESORTS"
        }, {
            "key"  : "3798",
            "value": "EMBASSY VACATION RESORT"
        }, {
            "key"  : "3799",
            "value": "HALE KOA HOTEL"
        }, {
            "key"  : "3800",
            "value": "HOMESTEAD SUITES"
        }, {
            "key"  : "3801",
            "value": "WILDERNESS HOTEL & RESORT"
        }, {
            "key"  : "3802",
            "value": "THE PALACE HOTEL"
        }, {
            "key"  : "3803",
            "value": "THE WIGWAM GOLF RESORT AND SPA"
        }, {
            "key"  : "3804",
            "value": "THE DIPLOMAT COUNTRY CLUB AND SPA"
        }, {
            "key"  : "3805",
            "value": "THE ATLANTIC"
        }, {
            "key"  : "3806",
            "value": "PRINCEVILLE RESORT"
        }, {
            "key"  : "3807",
            "value": "ELEMENT"
        }, {
            "key"  : "3808",
            "value": "LXR"
        }, {
            "key"  : "3809",
            "value": "SETTLE INN"
        }, {
            "key"  : "3810",
            "value": "LA COSTA RESORT"
        }, {
            "key"  : "3811",
            "value": "PREMIER INN"
        }, {
            "key"  : "3812",
            "value": "HYATT PLACE"
        }, {
            "key"  : "3813",
            "value": "HOTEL INDIGO"
        }, {
            "key"  : "3814",
            "value": "THE ROOSEVELT HOTEL NY"
        }, {
            "key"  : "3815",
            "value": "NICKELODEON FAMILY SUITES BY HOLIDAY INN"
        }, {
            "key"  : "3816",
            "value": "HOME2SUITES"
        }, {
            "key"  : "3817",
            "value": "AFFINIA"
        }, {
            "key"  : "3818",
            "value": "MAINSTAY SUITES"
        }, {
            "key"  : "3819",
            "value": "OXFORD SUITES"
        }, {
            "key"  : "3820",
            "value": "JUMEIRAH ESSEX HOUSE"
        }, {
            "key"  : "3821",
            "value": "CARIBE ROYALE"
        }, {
            "key"  : "3822",
            "value": "CROSSLAND"
        }, {
            "key"  : "3823",
            "value": "GRAND SIERRA RESORT"
        }, {
            "key"  : "3824",
            "value": "ARIA"
        }, {
            "key"  : "3825",
            "value": "VDARA"
        }, {
            "key"  : "3826",
            "value": "AUTOGRAPH"
        }, {
            "key"  : "3827",
            "value": "GALT HOUSE"
        }, {
            "key"  : "3828",
            "value": "COSMOPOLITAN OF LAS VEGAS"
        }, {
            "key"  : "3829",
            "value": "COUNTRY INN BY CARLSON"
        }, {
            "key"  : "3830",
            "value": "PARK PLAZA HOTEL"
        }, {
            "key"  : "3831",
            "value": "WALDORF"
        }, {
            "key"  : "3832",
            "value": "CURIO HOTELS"
        }, {
            "key"  : "3833",
            "value": "CANOPY"
        }, {
            "key"  : "3834",
            "value": "BAYMONT INN & SUITES"
        }, {
            "key"  : "3835",
            "value": "DOLCE HOTELS AND RESORTS"
        }, {
            "key"  : "3836",
            "value": "HAWTHORNE BY WYNDHAM"
        }, {
            "key"  : "3837",
            "value": "HOSHINO RESORTS"
        }, {
            "key"  : "3838",
            "value": "KIMPTON HOTELS"
        }, {
            "key"  : "4011",
            "value": "Railroads: freight"
        }, {
            "key"  : "4111",
            "value": "Local and suburban commuter transportation"
        }, {
            "key"  : "4112",
            "value": "Passenger railways"
        }, {
            "key"  : "4119",
            "value": "Ambulance services"
        }, {
            "key"  : "4121",
            "value": "Taxicabs and limousines"
        }, {
            "key"  : "4131",
            "value": "Bus lines"
        }, {
            "key"  : "4214",
            "value": "Motor freight carriers and trucking"
        }, {
            "key"  : "4215",
            "value": "Courier services and freight forwarders"
        }, {
            "key"  : "4225",
            "value": "Public warehousing and storage"
        }, {
            "key"  : "4411",
            "value": "Steamship and cruise lines"
        }, {
            "key"  : "4457",
            "value": "Boat rentals and leasing"
        }, {
            "key"  : "4468",
            "value": "Marinas, marine services and supplies"
        }, {
            "key"  : "4511",
            "value": "Airlines and air carriers"
        }, {
            "key"  : "4582",
            "value": "Airports and airport terminals"
        }, {
            "key"  : "4722",
            "value": "Travel agencies and tour operators"
        }, {
            "key"  : "4723",
            "value": "Package tour operators "
        }, {
            "key"  : "4761",
            "value": "Travel arrangement services"
        }, {
            "key"  : "4784",
            "value": "Tolls and bridge fees"
        }, {
            "key"  : "4789",
            "value": "Transportation services "
        }, {
            "key"  : "4812",
            "value": "Telecommunication equipment and phone sales"
        }, {
            "key"  : "4813",
            "value": "Key-entry telecom merchant "
        }, {
            "key"  : "4814",
            "value": "Telecommunication services"
        }, {
            "key"  : "4815",
            "value": "Visa phone"
        }, {
            "key"  : "4816",
            "value": "Computer network and information services"
        }, {
            "key"  : "4821",
            "value": "Telegraph services"
        }, {
            "key"  : "4829",
            "value": "Money transfer"
        }, {
            "key"  : "4899",
            "value": "Cable and paid television services"
        }, {
            "key"  : "4900",
            "value": "Utilities: electric, gas, water and sanitation "
        }, {
            "key"  : "5013",
            "value": "Motor vehicle supplies and new parts"
        }, {
            "key"  : "5021",
            "value": "Office and commercial furniture"
        }, {
            "key"  : "5039",
            "value": "Construction materials"
        }, {
            "key"  : "5044",
            "value": "Photo, photocopy, microfilm equipment and supplies"
        }, {
            "key"  : "5045",
            "value": "Computers, equipment and software"
        }, {
            "key"  : "5046",
            "value": "Commercial equipment"
        }, {
            "key"  : "5047",
            "value": "Medical, dental, lab, ophthalmic and hospital equipment"
        }, {
            "key"  : "5051",
            "value": "Metal service centers and offices"
        }, {
            "key"  : "5065",
            "value": "Electrical parts and equipment"
        }, {
            "key"  : "5072",
            "value": "Hardware, equipment and supplies"
        }, {
            "key"  : "5074",
            "value": "Plumbing and heating equipment and supplies"
        }, {
            "key"  : "5085",
            "value": "Industrial supplies "
        }, {
            "key"  : "5094",
            "value": "Precious stones and metals, watches and jewelry"
        }, {
            "key"  : "5099",
            "value": "Durable goods"
        }, {
            "key"  : "5111",
            "value": "Stationery, office supplies, printing and writing paper"
        }, {
            "key"  : "5122",
            "value": "Drugs and druggist sundries"
        }, {
            "key"  : "5131",
            "value": "Piece goods, notions and other dry goods"
        }, {
            "key"  : "5137",
            "value": "Uniforms and commercial clothing"
        }, {
            "key"  : "5139",
            "value": "Commercial footwear"
        }, {
            "key"  : "5169",
            "value": "Chemicals and allied products "
        }, {
            "key"  : "5172",
            "value": "Petroleum products"
        }, {
            "key"  : "5192",
            "value": "Books, periodicals and newspapers"
        }, {
            "key"  : "5193",
            "value": "Florist supplies, nursery stock and flowers"
        }, {
            "key"  : "5198",
            "value": "Paints, varnishes and supplies"
        }, {
            "key"  : "5199",
            "value": "Nondurable goods "
        }, {
            "key"  : "5200",
            "value": "Home supply warehouses"
        }, {
            "key"  : "5211",
            "value": "Lumber and building materials stores"
        }, {
            "key"  : "5231",
            "value": "Glass, paint and wallpaper stores"
        }, {
            "key"  : "5251",
            "value": "Hardware stores"
        }, {
            "key"  : "5261",
            "value": "Nurseries, lawn and garden supply stores"
        }, {
            "key"  : "5262",
            "value": "Online marketplaces"
        }, {
            "key"  : "5271",
            "value": "Mobile home dealers"
        }, {
            "key"  : "5300",
            "value": "Wholesale clubs"
        }, {
            "key"  : "5309",
            "value": "Duty-free stores"
        }, {
            "key"  : "5310",
            "value": "Discount stores"
        }, {
            "key"  : "5311",
            "value": "Department stores"
        }, {
            "key"  : "5331",
            "value": "Variety stores"
        }, {
            "key"  : "5399",
            "value": "Miscellaneous general merchandise"
        }, {
            "key"  : "5411",
            "value": "Grocery stores and supermarkets"
        }, {
            "key"  : "5422",
            "value": "Freezer and locker meat provisioners"
        }, {
            "key"  : "5441",
            "value": "Candy, nut, and confectionary stores"
        }, {
            "key"  : "5451",
            "value": "Dairy products stores"
        }, {
            "key"  : "5462",
            "value": "Bakeries"
        }, {
            "key"  : "5499",
            "value": "Convenience stores and specialty markets"
        }, {
            "key"  : "5511",
            "value": "Car and truck dealers, service, repairs and parts (new & used)"
        }, {
            "key"  : "5521",
            "value": "Car and truck dealers, service, repairs and parts (used)"
        }, {
            "key"  : "5531",
            "value": "Auto and home supply stores"
        }, {
            "key"  : "5532",
            "value": "Automotive tire stores"
        }, {
            "key"  : "5533",
            "value": "Automotive parts and accessories stores"
        }, {
            "key"  : "5541",
            "value": "Service stations "
        }, {
            "key"  : "5542",
            "value": "Automated fuel dispensers"
        }, {
            "key"  : "5551",
            "value": "Boat dealers"
        }, {
            "key"  : "5552",
            "value": "Electric vehicle charging"
        }, {
            "key"  : "5561",
            "value": "Camper, recreational and utility trailer dealers"
        }, {
            "key"  : "5571",
            "value": "Motorcycle shops and dealers"
        }, {
            "key"  : "5592",
            "value": "Motor homes dealers"
        }, {
            "key"  : "5598",
            "value": "Snowmobile dealers"
        }, {
            "key"  : "5599",
            "value": "Automotive, aircraft and farm equipment dealers"
        }, {
            "key"  : "5611",
            "value": "Clothing and accessories stores"
        }, {
            "key"  : "5621",
            "value": "Ready-to-wear stores"
        }, {
            "key"  : "5631",
            "value": "Accessory and speciality shops"
        }, {
            "key"  : "5641",
            "value": "Children's clothing stores"
        }, {
            "key"  : "5651",
            "value": "Family clothing stores"
        }, {
            "key"  : "5655",
            "value": "Sports and riding apparel stores"
        }, {
            "key"  : "5661",
            "value": "Shoe stores"
        }, {
            "key"  : "5681",
            "value": "Furriers and fur shops"
        }, {
            "key"  : "5691",
            "value": "Clothing stores"
        }, {
            "key"  : "5697",
            "value": "Tailors, seamstresses, mending and alterations"
        }, {
            "key"  : "5698",
            "value": "Wig and toupee shops"
        }, {
            "key"  : "5699",
            "value": "Apparel and accessory stores"
        }, {
            "key"  : "5712",
            "value": "Home furnishings and equipment stores"
        }, {
            "key"  : "5713",
            "value": "Floor covering stores"
        }, {
            "key"  : "5714",
            "value": "Drapery, window covering and upholstery stores"
        }, {
            "key"  : "5718",
            "value": "Fireplace and accessories stores"
        }, {
            "key"  : "5719",
            "value": "Home furnishing specialty stores"
        }, {
            "key"  : "5722",
            "value": "Household appliance stores"
        }, {
            "key"  : "5732",
            "value": "Electronics stores"
        }, {
            "key"  : "5733",
            "value": "Music stores"
        }, {
            "key"  : "5734",
            "value": "Computer software stores"
        }, {
            "key"  : "5735",
            "value": "Record shops"
        }, {
            "key"  : "5811",
            "value": "Caterers"
        }, {
            "key"  : "5812",
            "value": "Restaurants"
        }, {
            "key"  : "5813",
            "value": "Bars, taverns, clubs"
        }, {
            "key"  : "5814",
            "value": "Fast food"
        }, {
            "key"  : "5815",
            "value": "Digital media, books, movies, music"
        }, {
            "key"  : "5816",
            "value": "Digital games"
        }, {
            "key"  : "5817",
            "value": "Digital apps"
        }, {
            "key"  : "5818",
            "value": "Digital goods: large merchant"
        }, {
            "key"  : "5912",
            "value": "Drug stores and pharmacies"
        }, {
            "key"  : "5921",
            "value": "Package stores: beer, wine and liquor"
        }, {
            "key"  : "5931",
            "value": "Secondhand stores"
        }, {
            "key"  : "5932",
            "value": "Antique shops"
        }, {
            "key"  : "5933",
            "value": "Pawn shops"
        }, {
            "key"  : "5935",
            "value": "Wrecking and salvage yards"
        }, {
            "key"  : "5937",
            "value": "Antique reproduction stores"
        }, {
            "key"  : "5940",
            "value": "Bicycle shops "
        }, {
            "key"  : "5941",
            "value": "Sporting goods stores"
        }, {
            "key"  : "5942",
            "value": "Bookstores"
        }, {
            "key"  : "5943",
            "value": "Stationary, office and school supply stores"
        }, {
            "key"  : "5944",
            "value": "Jewelry, watch, clock and silverware stores"
        }, {
            "key"  : "5945",
            "value": "Hobby, toy and game shops"
        }, {
            "key"  : "5946",
            "value": "Camera and photo supply stores"
        }, {
            "key"  : "5947",
            "value": "Gift, card, novelty and souvenir shops"
        }, {
            "key"  : "5948",
            "value": "Luggage and leather goods stores"
        }, {
            "key"  : "5949",
            "value": "Sewing, needlework and fabric stores"
        }, {
            "key"  : "5950",
            "value": "Glassware and crystal stores"
        }, {
            "key"  : "5960",
            "value": "Direct marketing: insurance services"
        }, {
            "key"  : "5961",
            "value": "Mail order houses "
        }, {
            "key"  : "5962",
            "value": "Direct marketing: travel services"
        }, {
            "key"  : "5963",
            "value": "Door-to-door sales"
        }, {
            "key"  : "5964",
            "value": "Catalog merchants"
        }, {
            "key"  : "5965",
            "value": "Catalog and retail merchants"
        }, {
            "key"  : "5966",
            "value": "Outbound telemarketing merchants"
        }, {
            "key"  : "5967",
            "value": "Inbound telemarketing merchants"
        }, {
            "key"  : "5968",
            "value": "Continuity/subscription merchants"
        }, {
            "key"  : "5969",
            "value": "Direct marketers "
        }, {
            "key"  : "5970",
            "value": "Art supply and craft stores"
        }, {
            "key"  : "5971",
            "value": "Art dealers and galleries"
        }, {
            "key"  : "5972",
            "value": "Stamp and coin stores"
        }, {
            "key"  : "5973",
            "value": "Religious goods stores"
        }, {
            "key"  : "5974",
            "value": "Rubber stamp stores"
        }, {
            "key"  : "5975",
            "value": "Hearing aids: sales, service and supplies"
        }, {
            "key"  : "5976",
            "value": "Orthopedic goods and prosthetic devices"
        }, {
            "key"  : "5977",
            "value": "Cosmetic stores"
        }, {
            "key"  : "5978",
            "value": "Typewriter stores: sales, rental and service"
        }, {
            "key"  : "5983",
            "value": "Fuel dealers"
        }, {
            "key"  : "5992",
            "value": "Florists"
        }, {
            "key"  : "5993",
            "value": "Cigar stores "
        }, {
            "key"  : "5994",
            "value": "News dealers and newsstands"
        }, {
            "key"  : "5995",
            "value": "Pet shops, pet foods and supply stores"
        }, {
            "key"  : "5996",
            "value": "Swimming pools: sales and service"
        }, {
            "key"  : "5997",
            "value": "Electric razor stores: sales and service"
        }, {
            "key"  : "5998",
            "value": "Tent and awning shops"
        }, {
            "key"  : "5999",
            "value": "Miscellaneous and specialty retail stores"
        }, {
            "key"  : "6010",
            "value": "Financial institutions: manual cash disbursements"
        }, {
            "key"  : "6011",
            "value": "Financial institutions: automated cash disbursements"
        }, {
            "key"  : "6012",
            "value": "Financial institutions: merchandise and services"
        }, {
            "key"  : "6050",
            "value": "Quasi cash: member financial"
        }, {
            "key"  : "6051",
            "value": "Foreign currency, money orders, debt repayment"
        }, {
            "key"  : "6211",
            "value": "Security brokers and dealers"
        }, {
            "key"  : "6300",
            "value": "Insurance sales, underwriting and premiums"
        }, {
            "key"  : "6381",
            "value": "Insurance: premiums"
        }, {
            "key"  : "6399",
            "value": "Insurance"
        }, {
            "key"  : "6513",
            "value": "Real estate agents and managers"
        }, {
            "key"  : "6529",
            "value": "Remote stored value load: member financial institution"
        }, {
            "key"  : "6530",
            "value": "Remote stored value load: merchant"
        }, {
            "key"  : "6531",
            "value": "Payment service provider"
        }, {
            "key"  : "6532",
            "value": "Payment transaction: member financial institution"
        }, {
            "key"  : "6533",
            "value": "Payment transaction: merchant"
        }, {
            "key"  : "6535",
            "value": "Value purchase: member financial institution"
        }, {
            "key"  : "6536",
            "value": "Moneysend intracountry"
        }, {
            "key"  : "6537",
            "value": "Moneysend intercountry"
        }, {
            "key"  : "6538",
            "value": "Moneysend funding"
        }, {
            "key"  : "6540",
            "value": "Stored value card purchase"
        }, {
            "key"  : "6555",
            "value": "Mastercard-initiated rebate/reward"
        }, {
            "key"  : "6611",
            "value": "Overpayments"
        }, {
            "key"  : "6760",
            "value": "Savings bonds"
        }, {
            "key"  : "7011",
            "value": "Lodging: hotels, motels and resorts"
        }, {
            "key"  : "7012",
            "value": "Timeshares"
        }, {
            "key"  : "7032",
            "value": "Sporting and recreational camps"
        }, {
            "key"  : "7033",
            "value": "Trailer parks and campgrounds"
        }, {
            "key"  : "7210",
            "value": "Laundry, cleaning and garment services"
        }, {
            "key"  : "7211",
            "value": "Laundries: family and commercial"
        }, {
            "key"  : "7216",
            "value": "Dry cleaners"
        }, {
            "key"  : "7217",
            "value": "Carpet and upholstery cleaning"
        }, {
            "key"  : "7221",
            "value": "Photo studios"
        }, {
            "key"  : "7230",
            "value": "Beauty shops and barber shops"
        }, {
            "key"  : "7251",
            "value": "Shoe repair, shoe shine and hat cleaning shops"
        }, {
            "key"  : "7261",
            "value": "Funeral services "
        }, {
            "key"  : "7273",
            "value": "Dating services"
        }, {
            "key"  : "7276",
            "value": "Tax preparation services"
        }, {
            "key"  : "7277",
            "value": "Counseling services"
        }, {
            "key"  : "7278",
            "value": "Buying and shopping services and clubs"
        }, {
            "key"  : "7280",
            "value": "Hospital patient personal funds withdrawl accounts"
        }, {
            "key"  : "7295",
            "value": "Babysitting services"
        }, {
            "key"  : "7296",
            "value": "Clothing rental "
        }, {
            "key"  : "7297",
            "value": "Massage and spa services"
        }, {
            "key"  : "7298",
            "value": "Health and beauty spas"
        }, {
            "key"  : "7299",
            "value": "Miscellaneous personal services"
        }, {
            "key"  : "7311",
            "value": "Advertising services"
        }, {
            "key"  : "7321",
            "value": "Consumer credit reporting agencies"
        }, {
            "key"  : "7322",
            "value": "Debt collection agencies"
        }, {
            "key"  : "7332",
            "value": "Blueprinting and photocopying services"
        }, {
            "key"  : "7333",
            "value": "Commercial photography, art and graphics"
        }, {
            "key"  : "7338",
            "value": "Quick-copy and reproduction services"
        }, {
            "key"  : "7339",
            "value": "Stenographic services"
        }, {
            "key"  : "7342",
            "value": "Exterminating and disenfecting services"
        }, {
            "key"  : "7349",
            "value": "Cleaning, maintenance and janitorial services"
        }, {
            "key"  : "7361",
            "value": "Employment agencies, temporary help services"
        }, {
            "key"  : "7372",
            "value": "Computer programming and design services"
        }, {
            "key"  : "7375",
            "value": "Information retrieval services"
        }, {
            "key"  : "7379",
            "value": "Computer maintenance, repair and services"
        }, {
            "key"  : "7392",
            "value": "Management, consulting, and public relations"
        }, {
            "key"  : "7393",
            "value": "Detective, protective and security services"
        }, {
            "key"  : "7394",
            "value": "Equipment, furniture and appliance rental and leasing"
        }, {
            "key"  : "7395",
            "value": "Photo developing"
        }, {
            "key"  : "7399",
            "value": "Business services"
        }, {
            "key"  : "742",
            "value": "Veterinary services"
        }, {
            "key"  : "7511",
            "value": "Truck stop transactions"
        }, {
            "key"  : "7512",
            "value": "Auto rental agency"
        }, {
            "key"  : "7513",
            "value": "Truck and utility trailer rental"
        }, {
            "key"  : "7519",
            "value": "Motor home and recreational vehicle rental"
        }, {
            "key"  : "7523",
            "value": "Parking lots and garages"
        }, {
            "key"  : "7524",
            "value": "Express payment services: parking/garages"
        }, {
            "key"  : "7531",
            "value": "Automotive and body repair shops"
        }, {
            "key"  : "7534",
            "value": "Tire retreading and repair shops"
        }, {
            "key"  : "7535",
            "value": "Automotive paint shops"
        }, {
            "key"  : "7538",
            "value": "Automotive repair shops"
        }, {
            "key"  : "7542",
            "value": "Car washes"
        }, {
            "key"  : "7549",
            "value": "Towing services"
        }, {
            "key"  : "7622",
            "value": "Electronics repair shops"
        }, {
            "key"  : "7623",
            "value": "Air conditioning and refrigeration repair shops"
        }, {
            "key"  : "7629",
            "value": "Electrical and small appliance repair shops"
        }, {
            "key"  : "763",
            "value": "Agricultural cooperatives"
        }, {
            "key"  : "7631",
            "value": "Watch, clock and jewelry repair "
        }, {
            "key"  : "7641",
            "value": "Furniture reupholstry, repair and refinishing"
        }, {
            "key"  : "7692",
            "value": "Welding services"
        }, {
            "key"  : "7699",
            "value": "Miscellaneous repair shops and related services"
        }, {
            "key"  : "780",
            "value": "Landscaping and horticultural services"
        }, {
            "key"  : "7800",
            "value": "Government-owned lotteries"
        }, {
            "key"  : "7801",
            "value": "Government-licensed online casinos "
        }, {
            "key"  : "7802",
            "value": "Government-licensed horse/dog racing"
        }, {
            "key"  : "7829",
            "value": "Movie and video: production and distribution"
        }, {
            "key"  : "7832",
            "value": "Movie theaters"
        }, {
            "key"  : "7833",
            "value": "Express payment service: movie theaters"
        }, {
            "key"  : "7841",
            "value": "DVD rental stores"
        }, {
            "key"  : "7911",
            "value": "Dance halls, studios and schools"
        }, {
            "key"  : "7922",
            "value": "Ticket agencies and theatrical producers "
        }, {
            "key"  : "7929",
            "value": "Bands, orchestras and entertainers"
        }, {
            "key"  : "7932",
            "value": "Billiard and pool halls"
        }, {
            "key"  : "7933",
            "value": "Bowling alleys"
        }, {
            "key"  : "7941",
            "value": "Commercial sports"
        }, {
            "key"  : "7991",
            "value": "Tourist attractions and exhibits"
        }, {
            "key"  : "7992",
            "value": "Public golf courses"
        }, {
            "key"  : "7993",
            "value": "Video amusement game supplies"
        }, {
            "key"  : "7994",
            "value": "Video game arcades "
        }, {
            "key"  : "7995",
            "value": "Gambling transactions"
        }, {
            "key"  : "7996",
            "value": "Amusement parks, circuses, carnivals, fortune tellers"
        }, {
            "key"  : "7997",
            "value": "Membership clubs, country clubs, private golf courses"
        }, {
            "key"  : "7998",
            "value": "Aquariums and zoos"
        }, {
            "key"  : "7999",
            "value": "Recreation services "
        }, {
            "key"  : "8011",
            "value": "Doctors and physicians "
        }, {
            "key"  : "8021",
            "value": "Dentists and orthodontists"
        }, {
            "key"  : "8031",
            "value": "Osteopaths"
        }, {
            "key"  : "8041",
            "value": "Chiropractors"
        }, {
            "key"  : "8042",
            "value": "Optometrists and opthamologists"
        }, {
            "key"  : "8043",
            "value": "Opticians"
        }, {
            "key"  : "8044",
            "value": "Optical goods and eyeglasses"
        }, {
            "key"  : "8049",
            "value": "Podiatrists and chiropodists"
        }, {
            "key"  : "8050",
            "value": "Nursing and personal care facilities"
        }, {
            "key"  : "8062",
            "value": "Hospitals"
        }, {
            "key"  : "8071",
            "value": "Medical and dental laboratories"
        }, {
            "key"  : "8099",
            "value": "Medical services and health practitioners "
        }, {
            "key"  : "8111",
            "value": "Legal services and attorneys"
        }, {
            "key"  : "8211",
            "value": "Elementary and secondary schools"
        }, {
            "key"  : "8220",
            "value": "Colleges, universities and professional schools"
        }, {
            "key"  : "8241",
            "value": "Correspondence schools"
        }, {
            "key"  : "8244",
            "value": "Business and secretarial schools"
        }, {
            "key"  : "8249",
            "value": "Trade and vocational schools"
        }, {
            "key"  : "8299",
            "value": "Schools and educational services"
        }, {
            "key"  : "8351",
            "value": "Child care services"
        }, {
            "key"  : "8398",
            "value": "Charitable and social service organizations"
        }, {
            "key"  : "8641",
            "value": "Civic, social and fraternal associations"
        }, {
            "key"  : "8651",
            "value": "Political organizations"
        }, {
            "key"  : "8661",
            "value": "Religious organizations"
        }, {
            "key"  : "8675",
            "value": "Automobile associations"
        }, {
            "key"  : "8699",
            "value": "Membership organizations"
        }, {
            "key"  : "8734",
            "value": "Testing laboratories"
        }, {
            "key"  : "8911",
            "value": "Engineering, architectural and surveying services"
        }, {
            "key"  : "8931",
            "value": "Accounting, auditing and bookkeeping services"
        }, {
            "key"  : "8999",
            "value": "Professional services"
        }, {
            "key"  : "9034",
            "value": "I-Purchasing "
        }, {
            "key"  : "9045",
            "value": "Intra-government purchases: government only"
        }, {
            "key"  : "9211",
            "value": "Court costs including alimony and child support"
        }, {
            "key"  : "9222",
            "value": "Fines"
        }, {
            "key"  : "9223",
            "value": "Bail and bond payments"
        }, {
            "key"  : "9311",
            "value": "Tax payments"
        }, {
            "key"  : "9399",
            "value": "Government services"
        }, {
            "key"  : "9401",
            "value": "I-Purchasing"
        }, {
            "key"  : "9402",
            "value": "Postal services"
        }, {
            "key"  : "9405",
            "value": "U.S. Federal Government agencies or departments"
        }, {
            "key"  : "9406",
            "value": "Government-owned lotteries"
        }, {
            "key"  : "9700",
            "value": "Automated referral service"
        }, {
            "key"  : "9701",
            "value": "Visa credential server"
        }, {
            "key"  : "9702",
            "value": "GCAS emergency services "
        }, {
            "key"  : "9751",
            "value": "U.K. supermarkets"
        }, {
            "key"  : "9752",
            "value": "U.K. petrol stations"
        }, {
            "key"  : "9754",
            "value": "Gambling, horse racing, dog racing, state lottery"
        }, {
            "key"  : "9950",
            "value": "Intra-company purchases"
        }];

        let value = null;
        try {
            value = mappings.find(item => item.key == code).value;
        } catch (e) {
            console.log('merchant for ' + code + ' not found');
        }

        return value;
    }
};
