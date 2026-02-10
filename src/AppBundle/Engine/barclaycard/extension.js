var plugin = {

    hosts: {
        'www.barclaycardus.com': true,
        'home.barclaycardus.com': true,
        'bcol.barclaycard.co.uk': true,
        'as2r-bcc1b-bcol.barclaycard.co.uk': true,
        '/[\\w\\-]+\\.barclaycard\\.co\\.uk/': true,
        // rbx
        'www.findmybarclaycard.com': true,
        'awardwallet.com': true
    },
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function(params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    getStartingUrl: function (params) {
        if (params.account.login2 == 'UK')
            return "https://as2r-cla-bcc1-bcol.barclaycard.co.uk/ecom/as2/UI/#/login/";

        return 'https://www.barclaycardus.com/servicing/home?secureLogin=';
    },

    start: function (params) {
        browserAPI.log("start");
        var counter = 0;
        var start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var isLoggedIn = plugin.isLoggedIn(params);
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
            if (isLoggedIn === null && (document.location.host == 'www.findmybarclaycard.com' || document.location.host == 'awardwallet.com')) {
                clearInterval(start);
                document.location.href = plugin.getStartingUrl(params);
                return;
            }// if (isLoggedIn === null && document.location.host == 'www.findmybarclaycard.com')
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn");
        if (params.account.login2 == 'UK') {
            if ($('form[id = "login"]').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('a[href *=Logout]').text()) {
                browserAPI.log("LoggedIn");
                return true;
            }
        }
        else {
            if ($('form[id = loginSecureLoginForm]').length > 0) {
                browserAPI.log("not LoggedIn");
                return false;
            }
            if ($('a[href *=Logout]').text()) {
                browserAPI.log("LoggedIn");
                return true;
            }
        }
        return null;
    },

    isSameAccount: function (account) {
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('p.accountname').text();
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name.toLowerCase() == account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("Logout");
        provider.setNextStep('start', function() {
            document.location.href = 'https://www.barclaycardus.com/servicing/logout';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        var form;
        if (params.account.login2 == 'UK') {
            form = $('form[id = "login"]');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                $('label[for="idNumber"]').click();
                form.find('input[name = "idNumber"]').val(params.account.login);
                // reactjs
                provider.eval(
                    "var FindReact = function (dom) {" +
                    "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                    "        return dom[key];" +
                    "    }" +
                    "    return null;" +
                    "};" +
                    // "FindReact(document.querySelector('input[name = \"lastName\"]')).onChange('" + params.account.login3 + "');" +
                    "FindReact(document.querySelector('input[name = \"idNumber\"]')).onChange('" + params.account.login + "');"
                );
                provider.setNextStep('enterPassword', function () {
                    form.find('button[type = "submit"]').get(0).click();

                    setTimeout(function () {

                        var errors = $('span[class *= "__warningText___"]:visible, div[class *= "red message"]');
                        if (errors.length > 0 && util.filter(errors.text()) != '') {
                            provider.setError(util.filter(errors.text()));
                            return;
                        }

                        if (provider.isMobile) {
                            plugin.enterPassword(params);
                        }
                    }, 3000);
                });
            }
            else
                provider.setError(util.errorMessages.loginFormNotFound);
        }// if (params.account.login2 == 'UK')
        else {// if (params.account.login2 == 'USA')
            form = $('form[id = loginSecureLoginForm]');
            if (form.length > 0) {
                browserAPI.log("submitting saved credentials");
                form.find('input[name = "uxLoginForm.username"]').val(params.account.login);
                form.find('input[name = "uxLoginForm.password"]').val(params.account.password);
                provider.setNextStep('checkLoginErrors', function() {
                    form.find('#loginButton').get(0).click();
                });
            }
            else {
                provider.setError(util.errorMessages.loginFormNotFound);
            }
        }// if (params.account.login2 == 'USA')
    },

    // enterSQ: function (params) {
    //    browserAPI.log("enterSQ");
    //    if ((typeof(params) != 'undefined'))
    //        browserAPI.log("answers: " + JSON.stringify(params));
    //    var form = $('form[id = login]');
    //    if (form.length > 0) {
    //        browserAPI.log("submitting saved credentials");
    //        var firstIndex = util.findRegExp( $('label[for = "memorableWord_0_letter1"]').text(), /^(\d+)/ );
    //        browserAPI.log("Letter #1 -> " + firstIndex);
    //        var secondIndex = util.findRegExp( $('label[for = "memorableWord_1_letter2"]').text(), /^(\d+)/ );
    //        browserAPI.log("Letter #2 -> " + secondIndex);
    //
    //        var question = 'Please enter your memorable word';
    //        if ((typeof(params.account.answers) !== 'undefined')
    //            && (typeof(params.account.answers[question]) !== 'undefined')) {
    //            var answer = params.account.answers[question];
    //            browserAPI.log("answer: " + answer);
    //            browserAPI.log("Letter #1 -> " + answer[firstIndex-1]);
    //            browserAPI.log("Letter #2 -> " + answer[secondIndex-1]);
    //            form.find('input[name = "memorableWord_0_letter1"]').val(answer[firstIndex-1].toUpperCase());
    //            form.find('input[name = "memorableWord_1_letter2"]').val(answer[secondIndex-1].toUpperCase());
    //
    //            // util.sendEvent(form.find('input[name = "memorableWord_0_letter1"]').get(0), 'change');
    //            // util.sendEvent(form.find('input[name = "memorableWord_1_letter2"]').get(0), 'change');
    //
    //            // reactjs
    //            provider.eval(
    //                "var FindReact = function (dom) {" +
    //                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
    //                "        return dom[key];" +
    //                "    }" +
    //                "    return null;" +
    //                "};" +
    //                "FindReact(document.querySelector('input[name = \"memorableWord_0_letter1\"]')).onChange('" + answer[firstIndex-1].toUpperCase() + "');" +
    //                "FindReact(document.querySelector('input[name = \"memorableWord_1_letter2\"]')).onChange('" + answer[secondIndex-1].toUpperCase() + "');"
    //            );
    //
    //            provider.setNextStep('checkLoginErrors', function() {
    //                form.find('button[type = "submit"]').get(0).click();
    //                if (provider.isMobile) {
    //                    setTimeout(function () {
    //                        plugin.checkLoginErrors(params);
    //                    }, 5000);
    //                }
    //            });
    //            return;
    //        }
    //        plugin.checkLoginErrors(params);
    //    }
    //    else {
    //        provider.setError(["Security questions form not found", util.errorCodes.engineError]);
    //    }
    // },

    enterPassword: function (params) {
        browserAPI.log("enterPassword");
        var form = $('form[id = "login"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            // form.find('input[name = "passcode"]').val(params.account.password);
            // reactjs
            provider.eval(
                "var FindReact = function (dom) {" +
                "    for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {" +
                "        return dom[key];" +
                "    }" +
                "    return null;" +
                "};" +
                "FindReact(document.querySelector('input[name = \"passcode\"]')).onChange('" + params.account.password + "');"
            );
            var step = 'checkLoginErrors';
            // if (provider.isMobile) {
            //     browserAPI.log("Mobile");
            //     step = 'enterSQ';
            // }
            provider.setNextStep(step, function () {
                form.find('button[type = "submit"]').get(0).click();

                setTimeout(function () {
                    var errors = $('span[class *= "__warningText___"]:visible, div[class *= "red message"]');
                    if (errors.length > 0 && util.filter(errors.text()) != '') {
                        provider.setError(util.filter(errors.text()));
                        return;
                    }

                    if (provider.isMobile) {
                        // plugin.enterSQ(params);
                        plugin.checkLoginErrors(params);
                    }
                }, 3000);
            });
        }
        else
             provider.setError(util.errorMessages.passwordFormNotFound);
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        // USA
        var errors = $('span.error');
        // UK
        if (errors.length == 0)
            errors = $('span[class *= "__warningText___"]:visible, div[class *= "red message"]');
        if (errors.length > 0 && util.filter(errors.text()) != '')
            provider.setError(util.filter(errors.text()));
        else
            provider.complete();
    }
};