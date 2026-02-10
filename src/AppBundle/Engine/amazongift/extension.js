var plugin = {
    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML like Gecko) Chrome/68.0.3440.75 Safari/537.36',

    hosts: {
        'www.amazon.co.uk': true,
        'www.amazon.com': true, 'affiliate-program.amazon.com': true, 'www.mturk.com': true,
        'www.amazon.fr': true,
        'www.amazon.ca': true,
        'www.amazon.de': true,
        'www.amazon.co.jp': true,
    },
    cashbackLink: '', // Dynamically filled by extension controller

    startFromCashback: function (params) {
        browserAPI.log('startFromCashback');
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    loadIndex:function (params) {
        browserAPI.log("loadIndex");
        provider.setNextStep('delay');
        var link = $('table a.styledButton').get(0).attributes.onclick.value;
        link = util.findRegExp(link, /\(\'([^\'\)]+)/i, true);
        document.location.href = link;
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    delay: function (params) {
        browserAPI.log("delay");
        setTimeout(function() {
            plugin.loadLoginForm(params);
        }, 3000);
    },

    getStartingUrl: function (params) {
        browserAPI.log(">> getStartingUrl");
        if (["UK", "France", "Canada", "Germany", "Japan"].indexOf(params.account.login2) !== -1) {
            if (typeof(device) == 'undefined' || typeof(device.platform) == 'undefined') {
                browserAPI.log("Region => " + params.account.login2);
            }
            switch (params.account.login2) {
                case "France":
                    url = "https://www.amazon.fr/gp/flex/sign-out.html/ref=nav_youraccount_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1";
                    break;
                case "Canada":
                    url = 'https://www.amazon.ca/gp/navigation/redirector.html/ref=sign-in-redirect?ie=UTF8&associationHandle=caflex&currentPageURL=https%3A%2F%2Fwww.amazon.ca%2Fref%3Dnav_custrec_signin&pageType=Gateway&yshURL=https%3A%2F%2Fwww.amazon.ca%2Fgp%2Fyourstore%2Fhome%3Fie%3DUTF8%26ref_%3Dnav_custrec_signin';
                    break;
                case "Germany":
                    url = 'https://www.amazon.de/gp/navigation/redirector.html/ref=sign-in-redirect?ie=UTF8&associationHandle=deflex&currentPageURL=https%3A%2F%2Fwww.amazon.de%2Fref%3Dnav_signin&pageType=Gateway&switchAccount=&yshURL=https%3A%2F%2Fwww.amazon.de%2Fgp%2Fyourstore%2Fhome%3Fie%3DUTF8%26ref_%3Dnav_signin';
                    break;
                case "Japan":
                    url = 'https://www.amazon.co.jp/-/en/ap/signin?openid.pape.max_auth_age=0&openid.return_to=https%3A%2F%2Fwww.amazon.co.jp%2F%3Fref_%3Dnav_custrec_signin&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=jpflex&openid.mode=checkid_setup&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&';
                    break;
                default:
                    url = "https://www.amazon.co.uk/gp/yourstore/home?ie=UTF8&path=%2Fgp%2Fyourstore%2Fhome&ref_=gno_signout&signIn=1&useRedirectOnSuccess=1&action=sign-out&";
                    break;
            }// switch (params.account.login2)
            return url;
        }// if ($.inArray(params.account.login2, ["UK", "France", "Canada"]) !== -1)
        else{
            if (typeof(device) == 'undefined' || typeof(device.platform) == 'undefined') {
                browserAPI.log("Region => " + params.account.login2);
                browserAPI.log("Auto-login to => " + params.account.login3);
            }
            var url;
            switch (params.account.login3) {
                case "amazonaff":
                    url = 'https://www.amazon.com/ap/signin?openid.return_to=https%3A%2F%2Faffiliate-program.amazon.com%2F&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=amzn_associates_us&openid.mode=checkid_setup&marketPlaceId=ATVPDKIKX0DER&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&openid.pape.max_auth_age=0';
                    break;
                case "amazonturk":
                    url = 'https://www.mturk.com/mturk/beginsignin';
                    break;
                default:
                    url = 'https://www.amazon.com/ap/signin?_encoding=UTF8&openid.assoc_handle=usflex&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.mode=checkid_setup&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&openid.ns.pape=http%3A%2F%2Fspecs.openid.net%2Fextensions%2Fpape%2F1.0&openid.pape.max_auth_age=0&openid.return_to=https%3A%2F%2Fwww.amazon.com%2Fgp%2Fyourstore%2Fhome%3Fie%3DUTF8%26ref_%3Dnav_custrec_signin';
                    break;
            }// switch (params.account.login3)
            return url;
        }// elseif (params.account.login2 != 'UK')
    },

    start: function(params){
        browserAPI.log(">> start");
        // cash back
        if (document.location.href.indexOf('ascsubtag=FW') > 0) {
            provider.setNextStep('start');
            var login = $('li[id = "nav-ya-btn-signin"] a[href *= signin]');
            //if (login.length == 0)
            //    login = $('#nav-link-yourAccount');
            if (login.length > 0)
                login.get(0).click();
            else {
                params.account.login3 = 'amazongift';
                plugin.logout(params);
            }
            return;
        }
        if (plugin.isLoggedIn(params)) {
            if (plugin.isSameAccount(params.account)) {
                provider.complete();
                setTimeout(function(){
                    browserAPI.log("start: Redirect");
                    plugin.redirect(params);
                }, 2000)
            }
            else
                plugin.logout();
        }
        else
            plugin.login(params);
    },

    isLoggedIn: function(params){
        browserAPI.log("isLoggedIn");
        browserAPI.log("Region => " + params.account.login2);
        browserAPI.log("Auto-login to => " + params.account.login3);
        if($('a:contains("sign out")').text() || $('a:contains("Sign Out")').text()
            || $('a:contains("Not ")').text() || $('#nav-item-signout:contains("Not ")').text()
            || $('a:contains("Vous n\'êtes pas ")').text() || $('#nav-item-signout:contains("Vous n\'êtes pas ")').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#nav-signin-text').text() == 'Sign in' || $('form[name = "signIn"]').length > 0
            || $('#btnsignin').attr('value') == 'Sign In' || $('#nav-link-yourAccount').text() == 'Sign in'){
            browserAPI.log("not LoggedIn");
            return false;
        }
        // mobile
        if (params.account.login2 == 'UK' && provider.isMobile && $('#nav-signin-text').text() != 'Sign in') {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#ap_signin_form, form[name = "signIn"], #sign_in, form[name = "sign_in"]').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        provider.setError(util.errorMessages.unknownLoginState);
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var name = $('#nav-signin-text').text();
        browserAPI.log("number: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name != '')
            && (name == account.properties.Name));
    },

    logout: function(){
        browserAPI.log("logout");
        if ($.inArray(params.account.login2, ["UK", "France", "Canada"]) !== -1) {
            browserAPI.log("Region => " + params.account.login2);

            switch (params.account.login2) {
                case "France":
                    document.location.href = "https://www.amazon.fr/gp/flex/sign-out.html/ref=nav_youraccount_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1";
                    break;
                case "Canada":
                    url = 'https://www.amazon.ca/gp/flex/sign-out.html/ref=gno_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1';
                    break;
                case "Germany":
                    url = 'https://www.amazon.de/gp/flex/sign-out.html/ref=gno_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1';//todo
                    break;
                case "Japan":
                    url = 'https://www.amazon.co.jp/-/en/gp/flex/sign-out.html?path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1&action=sign-out&ref_=nav_AccountFlyout_signout';
                    break;
                default:
                    document.location.href = "https://www.amazon.co.uk/gp/flex/sign-out.html/ref=gno_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1";
                    break;
            }// switch (params.account.login2)
        }// if (params.account.login2 == 'UK' || params.account.login2 == 'France')
        else{
            browserAPI.log("Region => " + params.account.login2);
            browserAPI.log("Auto-login to => " + params.account.login3);
            var url;
            var nextStep = 'start';
            switch (params.account.login3) {
                case "amazonaff":
                    url = "http://affiliate-program.amazon.com/gp/flex/associates/sign-out.html?ie=UTF8&action=sign-out";
                    break;
                case "amazonturk":
                    url = 'https://www.mturk.com/mturk/beginsignout';
                    nextStep = 'loadLoginForm';
                    break;
                default:
                    url = "http://www.amazon.com/gp/flex/sign-out.html/ref=gno_signout?ie=UTF8&action=sign-out&path=%2Fgp%2Fyourstore%2Fhome&signIn=1&useRedirectOnSuccess=1";
                    break;
            }// switch (params.account.login3)
            provider.setNextStep(nextStep, function () {
                document.location.href = url;
            });
        }// elseif (params.account.login2 != 'UK')
    },

    login: function (params) {
        browserAPI.log("login");
        browserAPI.log("Region => " + params.account.login2);
        var form = $('#ap_signin_form');
        if (form.length == 0)
            form = $('form[name = "signIn"]');
        if (form.length == 0)
            form = $('#sign_in');
        if (form.length == 0)
            form = $('form[name = "sign_in"]');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);

            provider.setNextStep('checkLoginErrors', function () {
                var captcha = $('#auth-captcha-image-container:visible');
                if (captcha.length > 0) {
                    $('#auth-captcha-guess').focus();
                    provider.reCaptchaMessage();
                    var counter = 0;
                    var captchaInterval = setInterval(function () {
                        browserAPI.log("waiting... " + counter);
                        if (counter > 60) {
                            clearInterval(captchaInterval);
                            provider.setError(util.errorMessages.captchaErrorMessage, true);
                        }
                        counter++;
                    }, 1000);
                } else {
                    form.submit();
                }

            });
        }// if (form.length > 0)
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    checkLoginErrors: function(params){
        if ($('span:contains("re-enter your password and then enter the characters"):visible').length
            || $('span:contains("puis saisissez les caractères affichés dans"):visible').length) {
            return plugin.login(params);
        }
        var errors = $("#message_error");
        if (errors.length == 0)
            errors = $('div.message');
        if (errors.length == 0)
            errors = $('#auth-error-message-box li .a-list-item');
        if (errors.length > 0)
            provider.setError(errors.text());
        else {
            var form = $('form[name="claimspicker"][action="verify"]:visible');
            if (form.length) {
                provider.setNextStep('processStep', function () {
                    var questions = form.find('input[name="option"][type="radio"]');
                    $.each(questions, function (index, value) {
                        var question = util.trim($(this).next('i').next('span').text());
                        browserAPI.log("submitting question: " + question);
                        if (typeof(params.account.answers) !== 'undefined' && typeof(params.account.answers[question]) !== 'undefined') {
                            $(this).prop("checked", true);
                            params.data.question = question;
                            provider.saveTemp(params.data);
                            form.submit();
                            return false;
                        }
                    });
                    browserAPI.log("not submitting question");
                    plugin.complete(params, false);
                });
            } else {
                plugin.complete(params);
            }
        }
    },

    processStep: function (params) {
        browserAPI.log("security questions");
        provider.setNextStep('complete', function () {
            if (typeof params.data.question !== 'undefined' && typeof(params.account.answers[params.data.question]) !== 'undefined') {
                var form = $('form[action="verify"]:visible');
                if (form.length) {
                    form.find('input[name = "code"]').val(params.account.answers[params.data.question]);
                    form.submit();
                }
            }
            else {
                browserAPI.log("no answers were found");
                provider.complete();
            }
        });
    },

    complete: function (params, redirect) {
        browserAPI.log("complete");
        provider.complete();
        if (redirect) {
            setTimeout(function () {
                browserAPI.log("checkLoginErrors: Redirect");
                plugin.redirect(params);
            }, 2000);
        }
    },

    redirect: function (params) {
        browserAPI.log("redirect");
        // refs #13158
        var form = $('#ap_signin_form, form[name = "signIn"], #sign_in, form[name = "sign_in"]');
        if (form.find('input[name = "email"]:visible').length > 0) {
            form.find('input[name = "email"]').val(params.account.login);
            form.find('input[name = "username"]').val(params.account.login);
            form.find('input[name = "password"]').val(params.account.password);
            return;
        }// if (form.find('input[name = "email"]:visible').length > 0)

        if (params.account.login2 === 'UK'
            && document.location.href !== 'https://www.amazon.co.uk/gp/css/gc/balance/ref=ya__34'
        ) {
            browserAPI.log("UK");
            if (provider.isMobile) {
                browserAPI.log("Mobile");
                provider.setNextStep('complete', function () {
                    document.location.href = 'http://www.amazon.co.uk/ref=nav_logo';
                });
            }
            else
                document.location.href = 'https://www.amazon.co.uk/gp/css/gc/balance/ref=ya__34';
            return;
        }

        if (
            params.account.login2 === 'France'
            && document.location.href !== 'https://www.amazon.fr/gp/css/gc/balance/ref=ya__34'
        ) {
            browserAPI.log("France");
            if (provider.isMobile) {
                browserAPI.log("Mobile");
                provider.setNextStep('complete', function () {
                    document.location.href = 'http://www.amazon.fr/ref=nav_logo';
                });
            }
            else
                document.location.href = 'https://www.amazon.fr/gp/css/gc/balance/ref=ya__34';
            return;
        }

        if (
            params.account.login2 === 'Japan'
            && document.location.href !== 'https://www.amazon.co.jp/gc/balance/ref=gc_balance_legacy_to_newgc'
        ) {
            browserAPI.log("Japan");
            if (provider.isMobile)
            {
                browserAPI.log("Mobile");
                provider.setNextStep('complete', function () {
                    document.location.href = 'http://www.amazon.co.jp/ref=nav_logo';
                });
            }
            else
                document.location.href = 'https://www.amazon.co.jp/gc/balance/ref=gc_balance_legacy_to_newgc';

            return;
        }

        if (
            params.account.login2 === 'Canada'
            && document.location.href !== 'https://www.amazon.ca/gp/css/gc/balance/ref=ya__34'
        ) {
            browserAPI.log("France");
            if (provider.isMobile) {
                browserAPI.log("Mobile");
                provider.setNextStep('complete', function () {
                    document.location.href = 'https://www.amazon.ca/ref=nav_logo';
                });
            }
            else
                document.location.href = 'https://www.amazon.ca/gp/css/gc/balance/ref=ya__34';
            return;
        }

        if (
            (params.account.login3 === 'amazongift' || (typeof(params.account.login3) == 'undefined'))
            && document.location.href !== 'https://www.amazon.com/gp/css/gc/balance/'
            && params.account.login2 !== 'UK'
            && params.account.login2 !== 'France'
        ) {
            browserAPI.log("USA");
            if (provider.isMobile) {
                browserAPI.log("Mobile");
                provider.setNextStep('complete', function () {
                    document.location.href = 'http://www.amazon.com/ref=mw_hm';
                });
            }
            else
                document.location.href = 'https://www.amazon.com/gp/css/gc/balance/';
            return;
        }

        if (
            params.account.login3 === 'amazonturk'
            && document.location.href !== 'https://www.mturk.com/mturk/dashboard'
        ) {
            document.location.href = 'https://www.mturk.com/mturk/dashboard';
        }
    }

};