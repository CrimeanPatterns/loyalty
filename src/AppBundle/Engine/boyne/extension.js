var plugin = {

    hosts: {
        'www.boynerewards.com': true,
        'id.boyneresorts.com' : true,
    },

    getStartingUrl: function (params) {
        return 'https://www.boynerewards.com/account/history';
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
        if ($('form:has(input[name = "Username"])').length) {
            browserAPI.log("not LoggedIn");
            return false;
        }
        if ($('button.btn-logOut').length > 0) {
            browserAPI.log("LoggedIn");
            return true;
        }
        return null;
    },

    isSameAccount:function (account) {
        const name = util.filter($('span.name').text());
        browserAPI.log("name: " + name);
        return ((typeof(account.properties) != 'undefined')
            && (typeof(account.properties.Name) != 'undefined')
            && (account.properties.Name !== '')
            && (name.toLowerCase() === account.properties.Name.toLowerCase()));
    },

    logout: function (params) {
        browserAPI.log("Logout");
        $('button.btn-logOut').click();
        setTimeout(function () {
            plugin.loadLoginForm(params);
        }, 7000)
    },

    loadLoginForm: function (params) {
        browserAPI.log("loadLoginForm");
        provider.setNextStep('start', function () {
            document.location.href = plugin.getStartingUrl(params);
        });
    },

    login: function (params) {
        browserAPI.log("login");
        const formLogin = $('form:has(input[name = "Username"])');

        if (formLogin.length === 0) {
            provider.setError(util.errorMessages.loginFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        formLogin.find('input[name = "Username"]').val(params.account.login);

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
            'var email = document.querySelector(\'input[name = "Username"]\');' +
            'email.dispatchEvent(createNewEvent(\'input\')); email.dispatchEvent(createNewEvent(\'change\'));'
        );

        formLogin.find('button[value = "Username continue"]').click();

        provider.setNextStep('enterPassword');
        setTimeout(function() {
            plugin.enterPassword(params);
        }, 3000)
    },

    enterPassword: function (params) {
        browserAPI.log("enterPassword");
        const form = $('form:has(input[autocomplete = "password"])');

        if (form.length === 0) {
            if ($('span.InlineError:visible').length) {
                plugin.checkLoginErrors(params);
                return;
            }

            provider.setError(util.errorMessages.passwordFormNotFound);
            return;
        }

        browserAPI.log("submitting saved credentials");
        form.find('input[autocomplete = "password"]').val(params.account.password);

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
            'var pass = document.querySelector(\'input[autocomplete = "password"]\');' +
            'pass.dispatchEvent(createNewEvent(\'input\')); pass.dispatchEvent(createNewEvent(\'change\'));'
        );

        form.find('button[value = "Username continue"]').click();

        provider.setNextStep('checkLoginErrors');

        setTimeout(function() {
            plugin.checkLoginErrors(params);
        }, 5000)
    },

    checkLoginErrors: function () {
        browserAPI.log("checkLoginErrors");
        const errors = $('span.InlineError:visible');

        if (errors.length > 0) {
            provider.setError(errors.text());
            return;
        }

        provider.complete();
    }
};