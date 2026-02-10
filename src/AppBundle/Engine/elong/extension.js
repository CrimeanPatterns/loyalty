var plugin = {

    hosts: {'my.elong.com': true, 'secure.elong.com': true},

    getStartingUrl: function (params) {
        return 'http://my.elong.com/index_en.html';
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
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function(){
        browserAPI.log("isLoggedIn");
        if ($('#UserName').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('a[href *=logout]').text()) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount: function(account){
        // for debug only
        //browserAPI.log("account: " + JSON.stringify(account));
        var number = $('input#hidden_memberid_user').val();
        browserAPI.log("number: " + number);
        return typeof(account.properties) !== 'undefined'
            && typeof(account.properties.Number) !== 'undefined'
            && account.properties.Number !== ''
            && number == account.properties.Number;
    },

    logout: function(params){
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            $('a[href *= "logout"]').get(0).click();
        });
    },

    loadLoginForm: function(params){
        browserAPI.log("loadLoginForm");
        setTimeout(function() {
            plugin.start(params);
        }, 3000);
    },

    login: function(params){
        browserAPI.log("login");
        var form = $('div#ElongLogin');
        if (form.length > 0) {
            browserAPI.log("submitting saved credentials");
            form.find('input#UserName').val(params.account.login);
            form.find('input[method = ValidatePassword]').val(params.account.password);
            form.find('input#PassWord').val(params.account.password);
            // refs #11326
            util.sendEvent(form.find('input[method = ValidatePassword]').get(0), 'click');

            provider.setNextStep('checkLoginErrors', function () {
                // captcha recognize
                setTimeout(function () {
                    var captcha = form.find('div#ValidateCodeDiv:visible > img');
                    //browserAPI.log("waiting captcha -> " + captcha.attr('src'));
                    if (captcha.length > 0) {
                        provider.captchaMessageDesktop();
                        browserAPI.log("waiting...");
                        plugin.saveImage('https://secure.elong.com' + captcha.attr('src'), form);
                    }// if (captcha.length > 0)
                    else {
                        browserAPI.log("captcha is not found");
                        form.find('a.loginbtn').get(0).click();
                        setTimeout(function () {
                            plugin.checkLoginErrors(params);
                        }, 10000);
                    }
                }, 2000);
            });
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
    },

    saveImage: function (url, form) {
        var img = document.createElement("img");
        img.src = url;
        img.onload = function () {
            var key = encodeURIComponent(url),
                canvas = document.createElement("canvas");

            canvas.width = img.width;
            canvas.height = img.height;
            var ctx = canvas.getContext("2d");
            ctx.drawImage(img, 0, 0);
            //localStorage.setItem(key, canvas.toDataURL("image/png"));
            var dataURL= canvas.toDataURL("image/png");
            browserAPI.log("dataURL: " + dataURL);
            // recognize captcha
            browserAPI.send("awardwallet", "recognizeCaptcha", {
                captcha: dataURL,
                "extension": "jpg"
            }, function (response) {
                console.log(JSON.stringify(response));
                if (response.success === true) {
                    console.log("Success: " + response.success);
                    form.find('input#ValidateCode').val(response.recognized);
                    form.find('a.loginbtn').get(0).click();
                    plugin.checkLoginErrors();
                }// if (response.success === true))
                if (response.success === false) {
                    console.log("Success: " + response.success);
                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                }// if (response.success === false)
            });
        }
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        var counter = 0;
        var checkLoginErrors = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            var error = $('div[name="input_error_tip"]:visible');
            if (error.length > 0 && util.trim(error.text()) !== '') {
                clearInterval(checkLoginErrors);
                provider.setError(util.trim(error.text()));
            }
            if (counter > 30) {
                clearInterval(checkLoginErrors);
                provider.complete();
            }
            counter++;
        }, 500);
    }
}