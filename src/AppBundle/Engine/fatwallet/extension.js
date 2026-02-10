var plugin = {

	hosts: {'www.fatwallet.com': true},
	registerMessage: 'Please wait while we are registering you for FatWallet, this takes about 1 - 2 minutes',

	getStartingUrl: function(params){
        return 'https://www.fatwallet.com/login?targetUrl=%2Faccount%2Fcashback%2F';
	},

    dump: function (obj) {
        var props = '';
        for (var i in obj)
            props += i + ':' + obj[i] + '\n';
        return props;
    },

	start: function(params) {
        browserAPI.log("start");
        if (typeof(params.account.afterLogin) == 'string') {
            provider.showFader('Please wait, we are logging you in to ' + params.account.nextAccount.providerName +
                ' via your personal FatWallet account so that you can save ' + params.account.price + ' on your purchases.');
            // gag for IE
            if ($.browser.msie && $.browser.version < 11) {
                provider.setError('Unfortunately, your browser is not supported.');/*review*/
                throw "Unfortunately, your browser is not supported.";/*review*/
            }
        }// if (typeof(params.account.afterLogin) == 'string')
        if (plugin.isLoggedIn())
            plugin.logout(params);
//                plugin.checkLoginErrors(params);
        else
            plugin.loadLoginForm(params);
	},

	startRegistration: function(params){
		provider.showFader(plugin.registerMessage);
		if(plugin.isLoggedIn())
			provider.setError('You are already signed in on FatWallet. Please add your FatWallet account to your AwardWallet profile');/*review*/
		else{
			provider.setNextStep('register');
			document.location.href = 'http://www.fatwallet.com/?referral=veresch';
		}
	},

    register: function (params) {
        var form = $('form#signUpForm');
        if (form.length > 0) {
            form.find('input[name = email]').val(params.account.login);
            form.find('input[name = password]').val(params.account.password);
            provider.setNextStep('checkRegistrationErrors');
            form.find('#signupsubmitbtn').click();
            setTimeout(function() {
                plugin.checkRegistrationErrors(params);
            }, 2000)
        }
        else
            provider.setError('register form not found');
	},

	checkRegistrationErrors: function(params){
        browserAPI.log("checkRegistrationErrors");
        var errors = $('div[class = "authenticationFormError generic"]');
        if (errors.length > 0 && errors.text().length > 0) {
            provider.setError(errors.text());
        }
        else {
            setTimeout(function() {
                browserAPI.log("Registration completed");
                if (plugin.isLoggedIn())
                    provider.complete();
                else
                    provider.setError('Registration failed');
            }, 3000)
        }
    },

	isLoggedIn: function(){
        console.log("isLoggedIn");
        if ($('a[href *= "logout"]').length > 0) {
			browserAPI.log("LoggedIn");
			return true;
		}
        if ($('#loginForm').length > 0) {
			browserAPI.log("not LoggedIn");
			return false;
		}
        browserAPI.log("Can't determine login state");
        provider.setError("Can't determine login state");
        throw "Can't determine login state";
	},

    logout: function (params) {
        console.log("logout");
        if (plugin.isMobile()) {
            browserAPI.log("Mobile");
            api.setNextStep('start', function () {
                document.location.href = 'http://www.fatwallet.com/logout?targetUrl=%2F';
            });
        } else {
            provider.setNextStep('loadLoginForm');
            document.location.href = 'http://www.fatwallet.com/logout?targetUrl=%2F';
        }
	},

	loadLoginForm: function(params){
        console.log("loadLoginForm");
        if (plugin.isMobile()) {
            browserAPI.log("Mobile");
            api.setNextStep('login', function () {
                document.location.href = "https://www.fatwallet.com/join.php";
            });
        } else {
            provider.setNextStep('login');
            document.location.href = "https://www.fatwallet.com/join.php";
        }
	},

	login: function(params){
        console.log("login");
        var form = $('#loginForm');
        if (form.length == 0)
            throw 'Login form not found';
        $('#loginEmailAddress').val(params.account.login);
        $('#loginPassword').val(params.account.password);

        // remove captcha trash
        //form.find('input[name = "captchamyheart"]').remove();
        //form.find('div:has(input[name = "captcha"])').prev('div.authenticationFormLabel').remove();
        //form.find('div:has(input[name = "captcha"])').remove();
        //form.find('div:has(img.captchaImage)').remove();
        // captcha recognize
        /*setTimeout(function() {
            var captcha = $('#loginForm').find('img.captcha');
            //browserAPI.log("waiting captcha -> " + captcha.attr('src'));
            if (captcha.length > 0) {
                browserAPI.log("waiting...");
                // http://stackoverflow.com/questions/20424279/canvas-todataurl-securityerror
                // http://stackoverflow.com/questions/6150289/how-to-convert-image-into-base64-string-using-javascript

                //function getBase64FromImageUrl(URL) {
                //    var img = new Image();
                //    img.setAttribute('crossOrigin', 'anonymous');
                //    img.src = URL;
                //    img.onload = function () {
                //        var canvas = document.createElement("canvas");
                //        canvas.width = this.width;
                //        canvas.height = this.height;
                //
                //        var ctx = canvas.getContext("2d");
                //        ctx.drawImage(this, 0, 0);
                //
                //        var dataURL = canvas.toDataURL("image/png");
                //    }
                //}

                //getBase64FromImageUrl('https://www.fatwallet.com/captcha');


                var captchaDiv = document.createElement('div');
                captchaDiv.id = 'captchaDiv';
                document.body.appendChild(captchaDiv);

                var canvas = document.createElement('CANVAS'),
                    ctx = canvas.getContext('2d'),
                    img = document.forms['loginForm'].getElementsByClassName('img.captcha');

                canvas.height = img.height;
                canvas.width = img.width;
                ctx.drawImage(img, 0, 0);
                var dataURL = canvas.toDataURL('image/png');
                console.log("dataURL: " + dataURL);
                browserAPI.send("awardwallet", "recognizeCaptcha", { captcha: dataURL, "extension": "jpg" }, function(response){
                    console.log(JSON.stringify(response));
                    if (response.success === true) {
                        console.log("Success: " + response.success);
                        form.find('input[name = "captcha"]').val(response.recognized);*/
                        if (plugin.isMobile()) {
                            browserAPI.log("Mobile");
                            api.setNextStep('checkLoginErrors', function () {
                                form.submit();
                            });
                        } else {
                            provider.setNextStep('checkLoginErrors');
                            form.submit();
                            HTMLFormElement.prototype.submit.call(form);
                        }
                    /*}// if (response.success === true))
                    if (response.success === false) {
                        console.log("Success: " + response.success);
                        provider.setError(['We could not recognize captcha. Please try again later.', util.errorCodes.providerError], true);
                    }// if (response.success === false)
                });
            }// if (captcha.length > 0)
            else
                browserAPI.log("captcha is not found");
        }, 2000);*/
	},

	checkLoginErrors: function(params){
        console.log("checkLoginErrors");
		var errors = $('div.authenticationFormError');
        if (errors.length > 0)
			provider.setError(errors.text());
		else
            if (plugin.isLoggedIn()) {
                if (typeof(params.account.afterLogin) == 'string')
					plugin.search(params.account.afterLogin);
				else
					provider.complete();
			}
			else
				provider.setError('unknown login error');
	},

	search: function(text){
        console.log("search");
		provider.setNextStep('searchComplete');
        var searchField = $('#pageHeaderSearchBar');
        if (searchField.length > 0) {
			// us version
            searchField.val(text);
			$('input[value = "Go"]').click();
		}
		else
            throw "Search form not found";
	},

    /*
     * allowed formats: "US:1;CA:0", "US:1", "1", null
     */
    getLinkNumber: function (account) {
        browserAPI.log("getLinkNumber");
        if (account.userData != null && /:/.test(account.userData)) {
            var data = account.userData.split(';');
            var match = null;
            for (var i = 0; i < data.length; i++) {
                var el = data[i].split(':');
                browserAPI.log(' -------' + i + '-------- ');
                browserAPI.log( el[0] + ': ' + el[1]);
                if (account.nextAccount.login2 == el[0]) {
                    match = el[1];
                    break;
                }
                browserAPI.log(' -------' + i + '-------- ');
            }// for (var i = 0; i < data.length; i++)
            if (match)
                return match;
            else
                provider.setError('No offers for this provider');
        }// if (account.userData != null && /;/.test(account.userData))
        else if (account.userData != null)
            return account.userData;

        return 0;
    },

    searchComplete: function (params) {
        browserAPI.log("searchComplete");
        setTimeout(function () {
            provider.setNextStep('linkClicked');
            var linkIdx = plugin.getLinkNumber(params.account);

            //console.log( params );
            browserAPI.log('Search by text');
            var links = $('a:contains("' + plugin.escapeHtml(params.account.afterLogin) + '")[href *= "coupon"]');
            browserAPI.log('found links: ' + links.length);
            //if (links.length == 0) {
            //    browserAPI.log('Search by code');
            //    links = $('a:contains("' + params.account.nextAccount.providerCode + '")');
            //}
            //if (links.length == 0) {
            //    browserAPI.log('Search by Name');
            //    links = $('a:contains("' + params.account.nextAccount.providerName + '")');
            //}
            if (links.length > 0) {
                browserAPI.log('trying index -> ' + linkIdx);
                console.log(links.eq(linkIdx).get(0));
                links.eq(linkIdx).get(0).click();
            }
            else
                provider.setError('No offers for this provider');
        }, 2000);
    },

    escapeHtml: function (text) {
        return text
            .replace(/\&amp;/ig, "&")
            .replace(/\&lt;/ig, "<")
            .replace(/\&gt;/ig, ">")
            .replace(/\&quot;/ig, '"')
            .replace(/\&\#039;/ig, "'");
    },

    linkClicked: function (params) {
        browserAPI.log("linkClicked");
        provider.setNextStep('openProvider');
        provider.setNextAccount();
    },

    openProvider: function(params) {
        browserAPI.log("openProvider");
        var link = $('span.btn, span.new-shop-now-button').attr('onclick');
        // hotels.com, priceline
        if (!link) {
            browserAPI.log("try hotels.com button");
            link = $('a.book-now-button').attr('onclick');
        }
        link = util.findRegExp(link, /\(\'([^\'\)]+)/i);
        provider.setNextStep('loadLoginForm');
        if (link && link.length > 0)
            document.location.href = link;
        else
            provider.setError('No offers for this provider');
    },

    isMobile: function(){
        return (typeof(api) !== 'undefined') && (typeof(api.getDepDate) === 'function') && (api.getDepDate() instanceof Date);
    }
}
