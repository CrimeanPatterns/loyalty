var plugin = {

    hosts: {
        'www.china-airlines.com'    : true,
        'calec.china-airlines.com'  : true,
        'members.china-airlines.com': true,
    },
    mobileUserAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML like Gecko) Chrome/68.0.3440.75 Safari/537.36',

    getStartingUrl: function (params) {
        return 'https://members.china-airlines.com/dynasty-flyer/overview.aspx';
    },

    start: function (params) {
        browserAPI.log("start");
        let counter = 0;
        let start = setInterval(function () {
            browserAPI.log("waiting... " + counter);
            let isLoggedIn = plugin.isLoggedIn();
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
            if (isLoggedIn === null && counter > 20) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 20)
            counter++;
        }, 1000);
    },

    isLoggedIn: function() {
        browserAPI.log("isLoggedIn");
        if ($('#ContentPlaceHolder1_lblDfpCard:visible, span[id *= "lblHCardNo"]').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('#ContentPlaceHolder1_lblCardno:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function(account){
        browserAPI.log("isSameAccount");
        const number = util.findRegExp($('span[id *= "lblHCardNo"]').text(), /:([A-Z\d]+)/ )+1;
        browserAPI.log("number: " + number);
        return ((typeof(account.login) != 'undefined')
        && (number === account.login));
    },

    logout: function(params){
        browserAPI.log("Logout");
        provider.setNextStep('start', function () {
            const logout = $('a[href*="logout.aspx"]:contains("Logout")');
            if (logout.length)
                logout.get(0).click();
        });
    },

    login: function (params) {
        browserAPI.log("login");
        browserAPI.log("login waiting... ");
        const form = $('div.dfp-form-title:has(label:contains("Member Login")):visible').parent();
        const cardno = document.querySelector("div.dfp-form-title label").parentNode.parentNode.querySelector("input[id*='txtCardno']");
        const pwd = document.querySelector("div.dfp-form-title label").parentNode.parentNode.querySelector("input[id*='txtPwd']");
        if ((form.length > 0) && (typeof(cardno) !== 'undefined') && (typeof(pwd) !== 'undefined')) {
            browserAPI.log("submitting saved credentials");
            cardno.value = params.account.login;
            pwd.value = params.account.password;
            form.find("input[id*='txtNum']").attr('autocomplete', 'off');
            provider.setNextStep('checkLoginErrors', function () {
                setTimeout(function () {
                    var captcha = document.querySelector("img[id*='imgValidate']");
                    provider.captchaMessageDesktop();
                    if (typeof(captcha) !== 'undefined') {
                        browserAPI.log("waiting...");
                        if (provider.isMobile) {
                            provider.reCaptchaMessage();
                            let counter = 0;
                            let login = setInterval(function () {
                                browserAPI.log("waiting... " + counter);
                                if (counter > 120) {
                                    clearInterval(login);
                                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                                }
                                counter++;
                            }, 500);
                        } else
                            plugin.saveImage(captcha.src, form);
                    } else
                        form.find("button[id*='btnLogin']").click();
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
            var dataURL = canvas.toDataURL("image/png");
            browserAPI.log("dataURL: " + dataURL);
            // recognize captcha
            browserAPI.send("awardwallet", "recognizeCaptcha", {
                captcha: dataURL,
                "extension": "png"
            }, function (response) {
                console.log(JSON.stringify(response));
                if (response.success === true) {
                    browserAPI.log("Success: " + response.success);
                    form.find("input[id*='txtNum']").val(response.recognized);
                    form.find("button[id*='btnLogin']").click();
                }// if (response.success === true))
                if (response.success === false) {
                    browserAPI.log("Success: " + response.success);
                    provider.setError(util.errorMessages.captchaErrorMessage, true);
                }// if (response.success === false)
            });
        }
    },

    checkLoginErrors: function(params) {
        browserAPI.log("checkLoginErrors");
        const error = $('span[id$="Validator1"]:visible,[id$="Validator2"]:visible');

        if (error.length > 0) {
            provider.setError(error.text());
            return;
        }

        provider.complete();
    }

};