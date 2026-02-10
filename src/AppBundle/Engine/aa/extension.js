var plugin = {

	hosts: {'www.aa.com': true, 'hub.aa.com': true},

	getStartingUrl: function(params) {
        return 'https://www.aa.com/homePage.do?locale=en_US';
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
            let isLoggedIn = plugin.isLoggedIn(params);
            if (isLoggedIn !== null) {
                clearInterval(start);
                if (isLoggedIn) {
                    if (plugin.isSameAccount(params.account))
                        plugin.loginComplete(params);
                    else
                        plugin.logout(params);
                }
                else {
                    provider.setNextStep('login');
                    $('li#headerCustomerInfo > a#log-in-button:visible').get(0).click();
                    // plugin.login(params);
                }
            }// if (isLoggedIn !== null)
            if (isLoggedIn === null && counter > 10) {
                clearInterval(start);
                provider.setError(util.errorMessages.unknownLoginState);
                return;
            }// if (isLoggedIn === null && counter > 10)
            counter++;
        }, 500);
    },

    isLoggedIn: function () {
        browserAPI.log("isLoggedIn");
        if ($('a#homePageLoginWidgetLogoutLink:visible').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        if ($('li#headerCustomerInfo > a#log-in-button:visible').length > 0) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        return null;
    },

    isSameAccount: function (account) {
        browserAPI.log("isSameAccount");
        let name = util.trim($('p.cardmember-name').text());
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function () {
        browserAPI.log("logout");
        provider.setNextStep('loadLoginForm', function () {
            document.location.href = 'https://www.aa.com/login/logoutAccess.do';
        });
    },

    login: function (params) {
        browserAPI.log("login");
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary');
            document.location.href = "https://www.aa.com/reservation/findReservationAccess.do";
            return;
        }

        // through aw
        // plugin.throughAw(params.account);
        //return;

        var form = $('form#loginFormId');
        if (form.length > 0) {
            //provider.eval('jQuery.noConflict();');
            browserAPI.log("submitting saved credentials");
            form.find('input[name = "loginId"]').val(params.account.login);
            form.find('input[name = "lastName"]').val(params.account.login2);
            form.find('input[name = "password"]').val(params.account.password);
            // refs #11326
            provider.setNextStep('loginComplete', function () {
                /*
                provider.showFader('Please click the "Sign in" button to get logged in to your account');
                $('#awFader').remove();
                */
                form.find('button[name = _button_login]').click();
                /*
                form.find('button[name = "_button_login"]').bind('click', function (event) {
                    provider.hideFader();
                });
                */
                setTimeout(function () {
                    plugin.loginComplete(params);
                }, 30000);
            });
            return;

            //provider.setNextStep('checkLoginErrors');
            //plugin.submitFakeForm(params.account);
            //util.sendEvent(form.find('input[name = "password"]').get(0), 'mousedown');
            //setTimeout(function(){
            //    plugin.sendEvent('input[name = _button_go]', 'click');
            //}, 4000);
            //provider.setNextStep('checkLoginErrors');
            //setTimeout(function() {
            //    plugin.checkLoginErrors(params);
            //    //form.find('input[name = "_button_go"]').get(0).click();
            //}, 10000)
        }
        else
            provider.setError(util.errorMessages.loginFormNotFound);
	},

    /*getStartingUrl: function(params) {
        return 'https://www.aa.com/homePage.do';
    },

    start: function(params) {
        provider.setNextStep('processLogin');
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0)
            document.location.href = "https://www.aa.com/login/logoutAccess.do";
        else
            document.location.href = "http://hub.aa.com/en/nr/news";
    },

    processLogin: function(params) {
        plugin.login(params);
    },

    login: function(params){
        if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
            provider.setNextStep('getConfNoItinerary');
            document.location.href = "https://www.aa.com/reservation/findReservationAccess.do";
            return;
        }
        browserAPI.log("login");
        //var form = $('form#loginForm');
        //if (form.length > 0) {
        browserAPI.log("submitting saved credentials");
        //form.find('input[name = "loginId"]').val(params.account.login);
        //form.find('input[name = "lastName"]').val(params.account.login2);
        //form.find('input[name = "password"]').val(params.account.password);
        // refs #11326
        //util.sendEvent(form.find('input[name = "password"]').get(0), 'mousedown');
        provider.setNextStep('checkLoginErrors');
        plugin.submitFakeForm(params.account);
        //setTimeout(function(){
        //	plugin.sendEvent('input[name = loginId]', 'mousedown');
        //	plugin.sendEvent('input[name = lastName]', 'mousedown');
        //	plugin.sendEvent('input[name = password]', 'mousedown');
        //}, 3000);
        //setTimeout(function(){
        //	plugin.sendEvent('input[name = _button_login]', 'mousedown');
        //}, 6000);
        //setTimeout(function(){
        //	plugin.sendEvent('input[name = _button_login]', 'click');
        //}, 8000);
        //setTimeout(function(){
        //	util.sendEvent(form.find('input[name = "_button_login"]').get(0), 'mousedown');
        //	//plugin.sendEvent('input[name = _button_go]', 'click');
        //}, 3000);
//			setTimeout(function(){form.submit();}, 3000);
//		}
//		else {
//            browserAPI.log("Login form not found");
//            provider.setError('Login form not found');
//            throw 'Login form not found';
//		}
    },*/

	// we will submit form from another domain
	// aa will not fire checks, if there is outer referer
	submitFakeForm: function(account){
		var body = document.getElementsByTagName('body')[0];
		var form = document.createElement('form');
		form.action = 'https://www.aa.com/login/loginSubmit.do';
		form.method = 'post';
		body.appendChild(form);
		//var data = 'requestContextId=&previousPage=%2Floyalty%2Fprofile%2Fsummary&CurrentProcessId=&bookingPathStateId=&marketId=&discountCode=&uri=%2Flogin%2FloginAccess.do&seatPayment=&vpayment=&loginId='+encodeURIComponent(account.login)+'&lastName='+encodeURIComponent(account.login2)+'&password='+encodeURIComponent(account.password)+'&_button_login=Log+in';
		var data = 'requestContextId=&previousPage=%2FhomePage.do%3FselectedTab%3Daa-hp-myAccount%26locale%3D&CurrentProcessId=&bookingPathStateId=&marketId=&discountCode=&uri=%2Flogin%2FloginAccess.do&seatPayment=&vpayment=&loginId='+encodeURIComponent(account.login)+'&lastName='+encodeURIComponent(account.login2)+'&password='+encodeURIComponent(account.password)+'&_button_login=Log+in';
		var pairs = data.split('&');
		for (n = 0; n < pairs.length; n++) {
			pair = pairs[n].split('=');
			console.log(pair[0] + '=' + pair[1]);
			var input = document.createElement('input');
			input.type = 'hidden';
			input.name = pair[0];
			input.value = decodeURIComponent(pair[1]);
			//form[pair[0]] = decodeURIComponent(pair[1]);
			form.appendChild(input);
		}
		form.submit();
	},

	throughAw: function(account){
		var body = document.getElementsByTagName('body')[0];
		var form = document.createElement('form');
		form.action = 'https://awardwallet.com/aa/get-form';
		form.method = 'post';
		body.appendChild(form);

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'loginId';
        input.value = account.login;
        form.appendChild(input);

        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'lastName';
        input.value = account.login2;
        form.appendChild(input);

        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'password';
        input.value = account.password;
        form.appendChild(input);

        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'autosubmit';
        input.value = 'true';
        form.appendChild(input);

        provider.setNextStep('checkLoginErrors', function() {
            var f = $('form#loginForm');
            if (f.length > 0) {
                browserAPI.log("submitting saved credentials");
                f.find('input[name = "loginId"]').val(params.account.login);
                f.find('input[name = "lastName"]').val(params.account.login2);
                f.find('input[name = "password"]').val(params.account.password);
            }
            form.submit();
        });
	},

	sendEvent: function(elementSelector, event){
		var code = '\
		var element = jQuery.find("' + elementSelector + '")[0]; \
		if (document.createEvent) { \
			event = document.createEvent("HTMLEvents"); \
			event.initEvent("' + event + '", true, true); \
		} else { \
			event = document.createEventObject(); \
			event.eventType = "' + event + '"; \
		} \
		event.eventName = "' + event + '"; \
		if (document.createEvent) { \
			element.dispatchEvent(event); \
		} else { \
			element.fireEvent("on" + event.eventType, event); \
		}';
		//util.executeScript(code);
		provider.eval(code);
	},

    checkLoginErrors: function (params) {
		var errors = $("fieldset.aa-form-fieldset-label-error span.aa-msg-content");
		if (errors.length > 0) {
			provider.setError(errors.text());
		}
		else
			plugin.loginComplete(params);
	},

	loginComplete: function(params) {
        browserAPI.log('loginComplete');
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			provider.setNextStep('toItineraries', function () {
                document.location.href = 'https://www.aa.com/reservation/viewReservationsAccess.do';
            });
			return;
		}
		provider.complete();
	},

	toItineraries: function(params) {
        browserAPI.log('toItineraries');
		var links = $('a[href*="recordLocator=' + params.account.properties.confirmationNumber + '"]');
		if (links.length > 0) {
			provider.setNextStep("itLoginComplete");
			document.location.href = 'https://www.aa.com' + links.attr('href');
		}
		else
			provider.complete();
	},

	getConfNoItinerary: function(params) {
        browserAPI.log('getConfNoItinerary');
		var properties = params.account.properties.confFields;
		var form = $('form#findReservationForm');
		if (form.length > 0) {
			form.find("input[name='firstName']").val(properties.FirstName);
			form.find("input[name='lastName']").val(properties.LastName);
			form.find("input[name='recordLocator']").val(properties.ConfNo);
			provider.setNextStep('itLoginComplete', function () {
                form[0].submit();
            });
		}
        else
            provider.setError(util.errorMessages.itineraryFormNotFound);
	},

	itLoginComplete: function(params) {
		provider.complete();
	}

}