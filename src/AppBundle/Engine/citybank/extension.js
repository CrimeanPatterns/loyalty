var plugin = {

    hosts: {
        // USA
        'www.citi.com': true,
        'creditcards.citi.com': true,
        'www.accountonline.com': true,
        'accountonline.citi.com': true,
        'online.citibank.com': true,
        'online.citi.com': true,
        // India
        'www.citibank.co.in': true,
        'www.online.citibank.co.in': true,
        // Australia
        'citibank.com.au': true,
        'www.citibank.com.au': true,
        // Brazil
        'www.citibank.com.br': true,
        // Thailand
        'www.citibank.co.th': true,
        // Singapore
        'www.citibank.com.sg': true,
        // Mexico
        'bancanet.banamex.com': true,
        // Malaysia
        'www.citibank.com.my': true,
        // Hong Kong
        'www.citibank.com.hk': true,
        // Taiwan
        'www.citibank.com.tw': true
    },

    getStartingUrl: function(params){
        switch (params.account.login2) {
            case 'Australia':
                return 'https://citibank.com.au/AUGCB/JSO/signon/DisplayUsernameSignon.do';
                break;
            case 'Brazil':
                return 'https://www.citibank.com.br/BRGCB/JPS/portal/Index.do';
                break;
            case 'India':
                return 'http://www.citibank.com/india';
                break;
            case 'HongKong':
                return 'https://www.citibank.com.hk/HKGCB/JSO/signon/DisplayUsernameSignon.do';
                break;
            case 'Thailand':
                return 'https://www.citibank.co.th/THGCB/JSO/signon/DisplayUsernameSignon.do?locale=en_TH';
                break;
            case 'Taiwan':
                return 'https://www.citibank.com.tw/TWGCB/JSO/signon/DisplayUsernameSignon.do?locale=en_TW';
                break;
            case 'Singapore':
                return 'https://www.citibank.com.sg/SGGCB/JSO/signon/DisplayUsernameSignon.do';
                break;
            case 'Mexico':
                return 'https://bancanet.banamex.com/MXGCB/JPS/portal/LocaleSwitch.do?locale=es_MX';
                break;
            case 'Malaysia':
                return 'https://www.citibank.com.my/MYGCB/JSO/signon/DisplayUsernameSignon.do?';
                break;
            case 'USA': default:
                return 'https://online.citi.com/US/ag/mrc/dashboard';
                // return 'https://online.citi.com/US/login.do';
            break;
        }
    },

    start: function (params) {
        browserAPI.log("start -> " + params.account.login2);

        if (params.account.login2 === 'India') {
            provider.complete();
            return;
        }

        setTimeout(function () {
            if (plugin.isLoggedIn(params)) {
                if (plugin.isSameAccount(params.account))
                    provider.complete();
                else
                    plugin.logout(params);
            }
            else {
                if ($.inArray(params.account.login2, ['USA', '']) !== -1) {
                    plugin.preLogin(params);
                    return;
                }

                plugin.login(params);
            }
        }, 2000);
    },

    isLoggedIn: function (params) {
        browserAPI.log("isLoggedIn -> " + params.account.login2);
        var form;
        switch (params.account.login2) {
            case 'Australia':
                form = $('form#SignonForm');
                if (form.length == 0)
                    form = $('form[name = SignonForm]');
                if (form.length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                if ($('a[href *= signoff]').attr('href')) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                break;
            case 'India':
                break;
            case 'Mexico':
                if ($('form[name = "preSignonForm"]').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                // if ($('a#link_lkLogoffWithSummaryRecord')) {
                //     browserAPI.log("LoggedIn");
                //     return true;
                // }
                break;
            case 'Brazil':
            case 'Thailand':
            case 'Taiwan':
            case 'Singapore':
            case 'Malaysia':
            case 'HongKong':
                if ($('form#SignonForm').length > 0 || $('div[id="cbol-login-form"]:visible').length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                if ($('a#link_lkLogoffWithSummaryRecord')) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                break;
            case 'USA': default:
                // gag for IE
                if ($.browser.msie) {
                    provider.complete();
                    throw "Browser IE is not supported";
                }
                form = $('form[id = "logInForm"], form[name = "partnerLoginForm"]');
                if (form.length > 0) {
                    browserAPI.log("not LoggedIn");
                    return false;
                }
                if ($('a[href *= Logout]').text()) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                if ($('a[href *= signoff]').attr('href')) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                if ($('a.signOffBtn:visible, a#signOffmainAnchor:visible').length > 0) {
                    browserAPI.log("LoggedIn");
                    return true;
                }
                break;
        }// switch (params.account.login2)
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount -> " + params.account.login2);
        var name;
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        switch (params.account.login2) {
            case 'Australia':
            case 'Thailand':
            case 'Taiwan':
            case 'Singapore':
            case 'Malaysia':
            case 'HongKong':
                name = $('div#welcome_msg').text();
                browserAPI.log("name: " + name);
                return ((typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.Name) != 'undefined')
                    && (account.properties.Name !== '')
                    && (-1 < name.toLowerCase().indexOf(account.properties.Name.toLowerCase())) );
                break;
            case 'India':
            case 'Mexico':
                break;
            case 'Brazil':
                name = $('span.strong').text();
                browserAPI.log("name: " + name);
                return ((typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.Name) != 'undefined')
                    && (account.properties.Name != '')
                    && (name.toLowerCase() == account.properties.Name.toLowerCase()));
                break;
            case 'USA': default:
                name = util.findRegExp( $('#user_name, div.bgwelcome, div#welcomeBarHeadline, div.cA-ada-welcomeBarTitleWrapper').text(), /Welcome\s*\,?\s*([^<]+)/i);
                if (name == null)
                    name = '';
                name = name.replace(/\s\s/g, ' ');
                browserAPI.log("name: " + name);
                return ((typeof(account.properties) != 'undefined')
                    && (typeof(account.properties.Name) != 'undefined')
                    && (account.properties.Name != '')
                    && (name.toLowerCase() == account.properties.Name.toLowerCase()));

                break;
        }
        return false;
    },

    logout: function (params) {
        browserAPI.log("logout -> " + params.account.login2);
        provider.setNextStep('loadLoginForm', function () {
            switch (params.account.login2) {
                case 'Australia':
                    document.location.href = 'https://citibank.com.au/AUGCB/JSO/signoff/SummaryRecord.do?logOff=true';
                    break;
                case 'India':
                    document.location.href = 'https://mobile.citibank.co.in/mweb/redirect.do?method=redirect';
                    break;
                case 'Thailand':
                    document.location.href = 'https://www.citibank.co.th/THGCB/JSO/signoff/SummaryRecord.do?logOff=true';
                    break;
                case 'Taiwan':
                    document.location.href = 'https://www.citibank.com.tw/TWGCB/JSO/signoff/SummaryRecord.do?logOff=true';
                    break;
                case 'Singapore':
                    document.location.href = 'https://www.citibank.com.sg/SGGCB/JSO/signoff/SummaryRecord.do?logOff=true';
                    break;
                case 'Mexico':
                    document.location.href = 'https://bancanet.banamex.com/MXGCB/apps/logout/flow.action?logOutType=manual&source=singleTab';
                    break;
                case 'Malaysia':
                    document.location.href = 'https://www.citibank.com.my/MYGCB/JSO/signoff/SummaryRecord.do?logOff=true';
                    break;
                case 'HongKong':
                    document.location.href = 'https://www.citibank.com.hk/HKGCB/JSO/signoff/SummaryRecord.do?logOff=true';
                    break;
                case 'Brazil':
                    provider.eval("displayLogoffOverlay(); logoutCrediCard();");
                    break;
                case 'USA': default:
    //                document.location.href = 'https://online.citibank.com/US/JSO/signoff/Signoff.do';
    //                document.location.href = 'https://www.accountonline.com/cards/svc/Logout.do';
                    var logout = $('a[href *= Logout]');
                    if (logout.length == 0)
                        logout = $('a[href *= signoff]');
                    if (logout.length == 0)
                        logout = $('a.signOffBtn:visible, a#signOffmainAnchor:visible');
                    logout.get(0).click();
                break;
            }
        });
    },

    preLogin: function (params) {
        browserAPI.log("preLogin -> " + params.account.login2);

        let username = $.cookie("username", { path:'/', domain: '.citi.com', secure: true });
        browserAPI.log("username -> " + username);

        if (/\|/.test(username)) {
            browserAPI.log("do not keep username in cookies");
            $.removeCookie("username", { path:'/', domain: '.citi.com', secure: true });
            plugin.loadLoginForm(params);

            return;
        }

        plugin.login(params);
    },

    login: function (params) {
        browserAPI.log("login -> " + params.account.login2);
        var form;
        switch (params.account.login2) {
            case 'Australia':
            case 'Brazil':
            case 'Thailand':
            case 'Taiwan':
            case 'Singapore':
            case 'Malaysia':
            case 'HongKong':
                form = $('form[name = "SignonForm"], div[id="cbol-login-form"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "username"], input[aria-placeholder="User ID"]').val(params.account.login);
                    form.find('input[name = "password"], input[aria-placeholder="Password"]').val(params.account.password);
                    provider.setNextStep('checkLoginErrors', function () {
                        form.submit();
                    });
                }
                else
                    provider.setError(['Login form not found [Code: ' + params.account.login2 + ']', util.errorCodes.providerError]);
                break;
            case 'Mexico':
                form = $('form[name = "preSignonForm"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    form.find('input[name = "username"]').val(params.account.login);
                    form.find('input[name = "username1"]').val(params.account.login);
                    provider.setNextStep('loginMexico', function () {
                        provider.eval('validarUserNumber();');
                    });
                }
                else
                    provider.setError(['Login form not found [Code: MX]', util.errorCodes.providerError]);
                break;
            case 'India':
                break;
            case 'USA': default:
                form = $('form[name = "partnerLoginForm"]');

                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");

                    function triggerInput(selector, enteredValue) {
                        let input = document.querySelector(selector);
                        input.dispatchEvent(new Event('focus'));
                        input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
                        let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                        nativeInputValueSetter.call(input, enteredValue);
                        let inputEvent = new Event("input", { bubbles: true });
                        input.dispatchEvent(inputEvent);
                    }

                    // if (form.find('input[name = "citi-dropdown2-0HiddenInput"]').length) {
                    //     browserAPI.log("set hidden username");
                    //     form.find('input[name = "citi-dropdown2-0HiddenInput"]').val(params.account.login);
                    // } else {
                    //     browserAPI.log("set username");
                        triggerInput('#username', '' + params.account.login);
                    // }
                    triggerInput('#password, input[name = "password"]', '' + params.account.password);

                    // firefox bug fix
                    // provider.eval('$(\'input[name = "remember"]\').click();');
                    // provider.eval('$(\'input[name = "remember"]\').val(\'true\')');

                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('#signInBtn').get(0).click();

                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 7000);
                    });

                    return;
                }

                form = $('form[id = "logInForm"]');
                if (form.length > 0) {
                    browserAPI.log("submitting saved credentials");
                    // A different User ID  // refs #12903
                    // IE not working properly
                    // if (!!navigator.userAgent.match(/Trident\/\d\./)) {
                        $('option[value = "AddUser"]').attr('selected','selected');
                        // document.getElementById("cookiedList").selectedIndex = $('option[value = "AddUser"]').index();
                        provider.eval('onSelectUser(document.getElementById("cookiedList"));');
                    // }
                    form.find('input[name = "username"]').val(params.account.login);
                    form.find('input[name = "remember"]').focus();
                    form.find('input[name = "password"]').val(params.account.password);
                    // firefox bug fix
                    provider.eval('$(\'input[name = "remember"]\').click();');
                    provider.eval('$(\'input[name = "remember"]\').val(\'true\')');

                    provider.setNextStep('checkLoginErrors', function () {
                        form.find('#signInBtn').get(0).click();

                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 7000);
                    });
                }
                else
                    provider.setError(util.errorMessages.loginFormNotFound);
                break;
        }
    },

    loginMexico: function (params) {
        browserAPI.log("loginMexico");
        var form = $('form[name = "preSignonForm2"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "password1"]').val(params.account.password);
            form.find('input[name = "password"]').val(params.account.password);
            provider.setNextStep('checkLoginErrors', function () {
                provider.eval('validarUserKey();');
            });
        }
        else
            provider.setError(['Login2 form not found [Code: MX]', util.errorCodes.providerError]);
    },

    checkLoginErrors: function (params) {
        browserAPI.log("checkLoginErrors -> " + params.account.login2);
        var errors;
        setTimeout(function () {
            switch (params.account.login2) {
                case 'Australia':
                case 'Thailand':
                case 'Taiwan':
                case 'Singapore':
                case 'Malaysia':
                case 'HongKong':
                    errors = $('div#errorPage span[class *= "Error"]:visible');
                    break;
                case 'India':
                    break;
                case 'Brazil':
                    errors = $("div.sAdmiracion + p.sinMarginTop:visible");
                    break;
                case 'Mexico':
                    errors = $("div#errorMsg:visible");
                    if (errors.length == 0)
                        errors = $('p:contains("Esta información no esta disponible en este momento."):visible');
                    break;
                case 'USA': default:
                errors = $("font.err-new:visible");
                if (errors.length === 0) {
                    errors = $('span#signOnLoginError:visible');
                }
                if (errors.length === 0) {
                    errors = $('div.critical:visible div.message > div');
                }
                break;
            }

            if (errors.length > 0 && util.filter(errors.text()) !== '') {
                provider.setError(util.filter(errors.text()));
                return;
            }

            provider.complete();
        }, 2000);
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            window.location.href = plugin.getStartingUrl(params);
        });
    }

};