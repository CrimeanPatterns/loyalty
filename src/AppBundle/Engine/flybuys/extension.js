var plugin = {

    hideOnStart: true,//todo
    // keepTabOpen: true,//todo
    clearCache: true,
    blockImages: /Chrome/.test(navigator.userAgent) && /Google Inc/.test(navigator.vendor),
    hosts: {
        'www.flybuys.com.au': true,
        'www.flybuys.co.nz': true,
        'migration.flybuys.com.au': true,
        'my.flybuys.com.au': true,
        'id.flybuys.com.au': true,
        '.flybuys.com.au': true,
        'flybuys.com.au': true,
    },

    getStartingUrl: function (params) {
        if (params.account.login2 === 'New Zealand')
            return 'https://www.flybuys.co.nz/myflybuys';
        else
            return 'https://www.flybuys.com.au/my-account';
    },

    start: function (params) {
        browserAPI.log("start");
        browserAPI.log('Current URL: ' + document.location.href);
        browserAPI.log("UserAgent -> " + navigator.userAgent);
        browserAPI.log("UserAgent -> " + JSON.stringify(util.detectBrowser()));
        // login failed
        if (
            $('h1:contains("Your session has expired due to inactivity"):visible').length > 0
        ) {
            provider.setNextStep('login', function () {
                document.location.href = plugin.getStartingUrl(params);
            });
            return;
        }
        if (
            // Your session has expired due to inactivity // mobile
            document.location.href === 'https://my.flybuys.com.au/error'
        ) {
            provider.setNextStep('start', function () {
                document.location.href = 'https://www.flybuys.com.au/sign-in';
            });
            return;
        }

        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else
                    plugin.login(params);
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 15) {
                clearInterval(start);
                provider.logBody("lastPage");

                let message = $('p:contains("We\'re busy working through some scheduled maintenance for the website"):visible');
                // We could not complete this request right now, please try again or call us at 13 11 16 for support.
                if (message.length === 0) {
                    message = $('p:contains("We could not complete this"):visible');
                }
                if (message.length === 0) {
                    message = $('h1:contains("Looks like something went wrong"):visible');
                }
                if (message.length > 0) {
                    provider.setError([message, util.errorCodes.providerError], true);
                    return;
                }

                provider.setError(util.errorMessages.unknownLoginState, true);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 1000);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if (params.account.login2 === 'New Zealand') {
            if ($('form#login-form').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('#greeting_name:visible, #greeting_btn:visible').length > 0) {
                browserAPI.log("LoggedIn");
                return true;
            }
        } else {
            if ($('form[name = "defaultLoginForm"] input[name = "fullCardNumber"], a:contains("Not you?"):visible, span:contains("Sign in "):visible').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('form:has(input[id = "username"])').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if (
                $('a[href *= "/logout"], .MobilePrimaryNav-link:contains("Sign out"), .HeaderDesktopAccountName-logout').length > 0
                || $('button:contains("Sign out"), strong.HeaderDesktopAccountNettSummary-pointsValue:visible, h1.fb-me-account-summary-title:visible, span.fb-uikit-header-menu-customer-name:visible').length > 0
            ) {
                browserAPI.log("LoggedIn");
                return true;
            }
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        if (account.login2 === 'New Zealand') {
            return false;
        } else {
            // for debug only
            //browserAPI.log("account: " + JSON.stringify(account));

            var notYou = $('a[href="/full_logout"]:contains("Not you?")');
            if (notYou.length) {
                return false;
            }

            var number = null;
            $.ajax({
                url: "https://www.flybuys.com.au/flybuys-web/api/member/cardholder/",
                async: false,
                success: function(profileInfo) {
                    profileInfo = $(profileInfo);
                    // MembershipNo
                    if (typeof (profileInfo[0]) != 'undefined' && typeof (profileInfo[0].cardNumber) != 'undefined') {
                        number = profileInfo[0].cardNumber;
                        browserAPI.log("MembershipNo: " + number);
                    }
                    else
                        browserAPI.log("MembershipNo is not found");
                }// success: function (profileInfo)
            });
            return ((typeof(account.properties) != 'undefined')
                && (typeof(account.properties.MembershipNo) != 'undefined')
                && (account.properties.MembershipNo != '')
                && number
                && (number == account.properties.MembershipNo));
        }
    },

    logout: function (params) {
        browserAPI.log("logout");
        browserAPI.log("[Current URL]: " + document.location.href);
        if (params.account.login2 === 'New Zealand') {
            provider.setNextStep('loadLoginForm', function () {
                document.location.href = 'https://www.flybuys.co.nz/sign_out';
            });
        } else {
            provider.setNextStep('preLogin', function () {
                var notYou = $('a[href="/full_logout"]:contains("Not you?")');
                if (notYou.length) {
                    document.location.href = 'https://www.flybuys.com.au/full_logout';
                    return;
                }

                var menu = $('a[aria-controls="HeaderDesktoPanelNav"], button[aria-label="Expand menu"]');
                if (menu.length) {
                    menu.get(0).click();
                }

                var logout = $('.HeaderAccount a:contains("Sign out"), .MobilePrimaryNav-link:contains("Sign out")');
                if (logout.length == 0) {
                    logout = $('button:contains("Sign out")');
                }
                if (logout.length == 0) {
                    logout = $('span:contains("Sign out")');
                }
                if (logout.length) {
                    logout.get(0).click();
                }

                // Close Popup
                setTimeout(function () {
                    var close = $('.SimpleModalCloseButton, .fb-modal-footer-button');
                    if (close.length)
                        close.get(0).click();
                }, 1000);
            });
        }
    },

    preLogin: function (params) {
        browserAPI.log("preLogin");
        browserAPI.log("[Current URL]: " + document.location.href);
        if (params.account.login2 === 'New Zealand') {
            plugin.login(params);
        } else {
            if ($('a[href="/full_logout"]:contains("Not you?")').length) {
                provider.setNextStep('loadLoginForm', function () {
                    document.location.href = 'https://www.flybuys.com.au/full_logout';
                });
                return;
            }

            provider.setNextStep('start', function () {
                // provider.eval("angular.reloadWithDebugInfo();");
                // provider.eval("window.name = 'NG_ENABLE_DEBUG_INFO!' + window.name;");
                document.location.href = 'https://www.flybuys.com.au/sign-in';
            });
        }
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        browserAPI.log("[Current URL]: " + document.location.href);
        provider.setNextStep('start', function () {
            if (params.account.login2 === 'New Zealand') {
                document.location.href = plugin.getStartingUrl(params);
            } else {
                document.location.href = 'https://www.flybuys.com.au/sign-in#/';
            }
        });
    },

    login: function (params) {
        browserAPI.log("login");
        browserAPI.log("[Current URL]: " + document.location.href);
        if (params.account.login2 === 'New Zealand') {
            plugin.loginNZ(params);
        } else {
            util.waitFor({
                selector: 'form[name = "defaultLoginForm"]:visible, form:has(input[id = "username"]):visible',
                success: function (form) {
                    plugin.loginAustralia(params);
                }, fail: function () {
                    provider.logBody("lastPage");
                    provider.setError(util.errorMessages.loginFormNotFound);
                }
            });
        }
    },

    loginNZ: function (params) {
        browserAPI.log("loginNewZealand");
        var form = $('form#login-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved login");
            form.find('input[name = "user[username]"]').val(params.account.login);

            provider.setNextStep('loginNZPass', function () {
                var btn = form.find('#user-submit-action:contains("Next")');
                btn.prop('disabled', false);
                btn.get(0).click();
            });
        }
        else
            provider.setError(['Login form not found [Code: NZ]', util.errorCodes.engineError]);
    },

    loginNZPass: function (params) {
        browserAPI.log("loginNewZealandPass");
        var form = $('form#login-form');
        if (form.length > 0) {
            browserAPI.log("submitting saved password");
            form.find('input[name = "user[password]"]').val(params.account.password);
            form.find('input[name = "user[password]"]').click();
            provider.setNextStep('checkLoginErrorsNZ', function () {
                var btn = form.find('#user-submit-action:contains("Sign in")');
                btn.prop('disabled', false);
                btn.get(0).click();
            });
        }
        else
            provider.setError(util.errorMessages.passwordFormNotFound);
    },

    loginAustralia: function (params) {
        browserAPI.log("loginAustralia");
        var form = $('form[name = "defaultLoginForm"]');
        if (provider.isMobile) {
            form = $('form:has(input[id = "username"])');
        }
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // form.find('input[id = "username"]').val(params.account.login);
            // form.find('input[id = "default-pass"]').val(params.account.password);
            params.account.login = params.account.login.replace(/\s+/g, '').substring(0, 16);

            if (provider.isMobile) {
                provider.eval(
                    "var FindReact = function (dom) {" +
                    "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                    "        return dom[key];" +
                    "    }" +
                    "    return null;" +
                    "};" +
                    "FindReact(document.querySelector('input[id = \"username\"]')).onChange({target:{value:'" + params.account.login + "'}});"
                );
                provider.eval("document.querySelector('button[aria-label=\"Go to password page\"]').click()");

                setTimeout(function () {
                    browserAPI.log("submitting password");
                    // reactjs
                    provider.eval(
                        "var FindReact = function (dom) {" +
                        "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                        "        return dom[key];" +
                        "    }" +
                        "    return null;" +
                        "};" +
                        "FindReact(document.querySelector('input[id = \"pf.pass\"]')).onChange({target:{value:'" + params.account.password + "'}});"
                    );

                    submitForm();
                }, 1000);
            }
            else {
                // reactjs
                provider.eval(
                    "var FindReact = function (dom) {" +
                    "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                    "        return dom[key];" +
                    "    }" +
                    "    return null;" +
                    "};" +
                    "FindReact(document.querySelector('input[id = \"username\"]')).onChange({target:{value:'" + params.account.login + "'}});" +
                    "FindReact(document.querySelector('input[id = \"default-pass\"]')).onChange({target:{value:'" + params.account.password + "'}});"
                );

                submitForm();
            }

            function submitForm() {
                provider.setNextStep('preloadCheckLoginErrors', function () {
                    provider.eval("document.querySelector('button[aria-label=\"Sign in\"]').click()");
                    setTimeout(function () {
                        browserAPI.log("go to checkLoginErrors");
                        plugin.checkLoginErrors(params);
                    }, 10000);
                });
            }

        }// if (form.length > 0)
        else {
            provider.logBody("lastPage");
            provider.setError(util.errorMessages.loginFormNotFound);
        }
    },

    checkLoginErrorsNZ: function (params) {
        browserAPI.log("checkLoginErrorsNZ");
        var errors = $('.alert.alert-warning:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '')
            provider.setError(errors.text());
        else
            provider.complete();
    },

    preloadCheckLoginErrors: function (params) {
        browserAPI.log("preloadCheckLoginErrors");
        provider.setNextStep('checkLoginErrors', function () {
            setTimeout(function () {
                browserAPI.log("go to checkLoginErrors");
                plugin.checkLoginErrors(params);
            }, 5000);
        });
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors");
        browserAPI.log("[Current URL]: " + document.location.href);
        provider.logBody("checkLoginErrorsPage");
        var errors = $('span.text-fb-rustyRed:visible');
        if (errors.length > 0 && util.filter(errors.text()) !== '') {
            var message = util.filter(errors.text());
            browserAPI.log("[Error] -> " + message);
            // if (/Your account is locked\./.test(message))
            //     provider.setError([message, util.errorCodes.lockout], true);
            // if (/The last name or date of birth you entered is incorrect\./.test(message))
            //     provider.setError([message, util.errorCodes.providerError], true);
            if (
                /is invalid\./.test(message)
                || /Incorrect email or password. Please try your flybuys number\./.test(message)
            ) {
                provider.setError(message, true);
            }

            provider.complete();

            return;
        }

        if ($('p:contains("You must pair your mobile application."):visible').length > 0) {
            provider.setError(['You must pair your mobile application.', util.errorCodes.providerError], true);
            return;
        }

        if (
            $('h1:contains("Let\'s make your account more secure"):visible').length > 0
            || $('p:contains("It looks like you\'re logging in from a new device. For your security we\'ve sent a verification code to "):visible').length > 0
        ) {
            if (!provider.isMobile) {
                if (params.autologin)
                    provider.setError(['It seems that FlyBuys needs to identify this computer before you can log in. Please follow the instructions on the new tab (the one that shows your FlyBuys authentication options) to get this computer authorized and then please try to auto-login again.', util.errorCodes.providerError], true);
                else
                    provider.setError(['It seems that FlyBuys needs to identify this computer before you can update this account. Please follow the instructions on the new tab (the one that shows your FlyBuys authentication options) to get this computer authorized and then please try to update this account again.', util.errorCodes.providerError], true);
                return;
            }
            showMessageInMobile();

            function showMessageInMobile() {
                provider.command('show', function () {
                    provider.showFader('Message from AwardWallet: In order to log in into this account please answer the question below and click the “Verify” button. Once logged in, sit back and relax, we will do the rest.');/*review*/
                    provider.setNextStep('loginComplete', function () {
                        browserAPI.log("waiting answers...");
                        var counter = 0;
                        var login = setInterval(function () {
                            browserAPI.log("waiting... " + counter);
                            var error = $('span.text-fb-rustyRed:visible');
                            if (error.length > 0 && util.filter(error.text()) !== '') {
                                clearInterval(login);
                                plugin.checkLoginErrors(params);
                            }// if (error.length > 0 && error.text().trim() != '')
                            if (counter > 100) {
                                clearInterval(login);
                                provider.complete();
                            }
                            counter++;
                        }, 500);
                    });
                });
            }

            return;
        }

        plugin.loginComplete(params);
    },

    loginComplete: function (params) {
        browserAPI.log("loginComplete");
        browserAPI.log("[Current URL]: " + document.location.href);
        if (params.autologin) {
            browserAPI.log("autologin only");
            provider.complete();
            return;
        }
        if (provider.isMobile) {
            provider.command('hide', function () {
            });
        }
        browserAPI.log("Loading account");
        browserAPI.log("[Current URL]: " + document.location.href);
        provider.setNextStep('parse', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    parse: function (params) {
        provider.updateAccountMessage();
        browserAPI.log("parse");
        var data = {};
        // Balance
        $.ajax({
            url: "https://www.flybuys.com.au/flybuys-web/api/member/session",
            async: false,
            success: function(response) {
                browserAPI.log("parse Balance");
                response = $(response);
                // console.log("---------------- profileInfo ----------------");
                // console.log(response[0]);
                // console.log("---------------- profileInfo ----------------");
                if (typeof (response[0]) != 'undefined' && typeof (response[0].nettPoints) != 'undefined') {
                    data.Balance = response[0].nettPoints;
                    browserAPI.log("Balance: " + data.Balance);
                }
                else {
                    browserAPI.log("Balance not found");
                    provider.logBody("balancePage");
                }
            },// success: function (profileInfo)
            error: function (response) {
                browserAPI.log("fail: Balance not found");
                browserAPI.log("---------------- fail data ----------------");
                browserAPI.log(JSON.stringify(response));
                browserAPI.log("---------------- fail data ----------------");

                var balance = $('strong.HeaderDesktopAccountNettSummary-pointsValue, span.HeaderMobileAccountNettSummary-pointsValue');
                if (balance.length > 0) {
                    balance = util.findRegExp( balance.text(), /([\d\.\,\-]+)\s*point/i);
                    browserAPI.log("Balance: " + balance);
                    data.Balance = balance;
                }
                else {
                    browserAPI.log("Balance not found");
                    provider.logBody("failBalancePage");
                }
            }
        });

        $.ajax({
            url: "https://www.flybuys.com.au/flybuys-web/api/member/cardholder/",
            async: false,
            success: function(profileInfo) {
                browserAPI.log("parse profile info");
                profileInfo = $(profileInfo);
                // console.log("---------------- profileInfo ----------------");
                // console.log(profileInfo[0]);
                // console.log("---------------- profileInfo ----------------");
                if (typeof (profileInfo[0]) != 'undefined'
                    && typeof (profileInfo[0].firstName) != 'undefined' && typeof (profileInfo[0].lastName) != 'undefined') {
                    data.Name = util.beautifulName(profileInfo[0].firstName + ' ' + profileInfo[0].lastName);
                    browserAPI.log("Name: " + data.Name);
                }
                else
                    browserAPI.log("Name not found");
                // MembershipNo
                if (typeof (profileInfo[0]) != 'undefined' && typeof (profileInfo[0].cardNumber) != 'undefined') {
                    data.MembershipNo = profileInfo[0].cardNumber;
                    browserAPI.log("MembershipNo: " + data.MembershipNo);
                }
                else
                    browserAPI.log("MembershipNo is not found");
            }// success: function (profileInfo)
        });

        $.ajax({
            url: 'https://www.flybuys.com.au/flybuys-web/api/member/transaction',
            async: false,
            success: function (response) {
                response = $(response);
                // console.log("---------------- statement ----------------");
                // console.log(response);
                // console.log("---------------- statement ----------------");
                if (typeof (response) != 'undefined') {
                    for (var row in response) {
                        if (!response.hasOwnProperty(row))
                            continue;
                        // console.log("---------------- row ----------------");
                        // console.log(response[row]);
                        // console.log("---------------- row ----------------");
                        if (
                            typeof (response[row].processingDate) != 'undefined'
                            && typeof (response[row].points) != 'undefined'
                            && (response[row].points.standard > 0 || response[row].points.bonus > 0)
                        ) {
                            // Last Activity
                            data.LastActivity = response[row].processingDate;
                            browserAPI.log('lastActivity: ' + data.LastActivity);
                            var date = plugin.dateFormatUTC(data.LastActivity, true);
                            if (date !== null && date.getTime()) {
                                date.setMonth(date.getMonth() + 12);
                                var unixtime = date / 1000;
                                if (!isNaN(unixtime)) {
                                    browserAPI.log("ExpirationDate = lastActivity + 12 months");
                                    browserAPI.log("Expiration Date: " + date + " UnixTime: " + unixtime);
                                    data.AccountExpirationDate = unixtime;
                                }
                            }
                            else
                                browserAPI.log("Invalid Expiration Date");
                            break;
                        }
                    }
                }// if (typeof (response[0]) != 'undefined') {
            }// success: function (response)
        });// $.ajax({

        params.account.properties = data;
        provider.saveProperties(params.account.properties);
        provider.complete();
    },

    dateFormatUTC: function (stringDate, isObject) {
        browserAPI.log('dateFormatUTC');
        // 2019-01-20
        var date = stringDate.match(/(\d+)\-(\d+)\-(\d+)/);
        var year = date[1], month = date[2], day = date[3];
        var dateObject = new Date(Date.UTC(year, month - 1, day, 0, 0, 0, 0));
        var unixTime = dateObject.getTime() / 1000;
        if (!isNaN(unixTime)) {
            browserAPI.log('Date Format UTC: ' + dateObject + ' UnixTime: ' + unixTime);
            return isObject ? dateObject : unixTime;
        }
        return null;
    }

};
