var answerSent = false;
var retry = 0;
var pageUnavailable = false;
var errorText = null;
var cards = 0;
var subAccounts = [];
var detectedCards = [];
var parseThankYouPointsLink = null;

function start(){
    util.setNextStep(start2);
    util.ignoredUrls.push('https://chat.');
    util.ignoredUrls.push('https://ads.');
    util.ignoredUrls.push('https://at.amgdgt.com');
    util.ignoredUrls.push('https://at.amgdgt.com');
    util.ignoredUrls.push('https://metrics1.');
    util.ignoredUrls.push('https://pixel.mathtag.com');
    util.ignoredUrls.push('https://www.accountonline.com/cards/wv/svc/offer/');
    util.ignoredUrls.push('https://www.accountonline.com/cards/svc/offer/');
    util.ignoredUrls.push('https://mpsnare.iesnare.com/');
    util.ignoredUrls.push('https://view.atdmt.com');
    util.ignoredUrls.push('https://paper.citi.com/127893/CWrT.html');
//    page.open('https://www.citi.com/credit-cards/creditcards/CitiHome.do?layoutParam=BAU');
    // other login url
    // page.open('https://online.citi.com/US/JPS/portal/Index.do?userType=BB');
    page.open('https://accountonline.citi.com/cards/svc/LoginGet.do');
}

function start2(){
    util.setNextStep(scriptWait);
    page.open('https://accountonline.citi.com/cards/svc/LoginGet.do?');
}

function scriptWait(){
    util.waitFor(function () {
        return document.forms["secureSignOnForm"] != null || document.body.innerHTML.search('AccountOnline Temporarily Unavailable') != -1;
    }, function () {
        setTimeout(loginLoaded, 7000);
    }, 10000);
}

function loginLoaded() {
    console.log('--- loginLoaded');
    util.setNextStep(loginSent);
    // maintenance
    util.checkError(util.findRegExp(/AccountOnline Temporarily Unavailable/i), constants.errorCode.providerError);

    page.evaluate(function(login, pass){
       // var form = document.forms['logon'];
       // if (document.getElementById('cA-cardsUseridMasked')) {
       //     document.getElementById('cA-cardsUseridMasked').focus();
       //     form['USERNAME'].value = login;
       //     $('#cA-cardsUseridMasked').val(login);
       // }
       // if (document.getElementById('cA-cardsLoginPasswordInput')) {
       //     console.log('--- PASSWORD');
       //     document.forms['logon']['remember'].focus();
       //     form['PASSWORD'].value = pass;
       // }
       // if (typeof form['remember'] != 'undefined') {
       //     console.log('--- remember');
       //     document.forms['logon']['remember'].click();
       //     form['remember'].value = 'true';
       // }
       // if ($('input.cA-cardsLoginSubmit').length > 0) {
       //     console.log('--- button');
       //     $('input.cA-cardsLoginSubmit').click();
       // }

        var form = document.forms['secureSignOnForm'];
        if (document.getElementById('USERNAME-citiTextBlur')) {
            document.getElementById('USERNAME-citiTextBlur').focus();
            form['USERNAME'].value = login;
            $('#USERNAME-citiTextBlur').val(login);
        }
        if (document.getElementById('PASSWORD')) {
            console.log('--- PASSWORD');
            document.forms['secureSignOnForm']['RememberMe'].focus();
            form['PASSWORD'].value = pass;
        }
        if (typeof form['remember'] != 'undefined') {
            console.log('--- remember');
            document.forms['secureSignOnForm']['RememberMe'].click();
            form['remember'].value = 'true';
        }
        form.submit();
    }, input.login, input.password);
}

function loginSent(){
    console.log('>>> loginSent');
    util.checkError(util.querySelector('font.err-new'));
    util.checkError(util.querySelector('p.error'));
    //util.checkError(util.findRegExp(/Information not recognized/i));
    // At least one of your entries does not match our records.
    util.checkError(util.querySelector('h4.appError', false, /(At least one of your entries does not match our records\.)/i));
    // Incorrect Information Entered
    util.checkError(util.findRegExp(/h1 style="margin-left:0px;">(Incorrect Information Entered)<\/h1>/i));
    util.checkError(util.findRegExp(/Create Your Security\s*Questions/i), constants.errorCode.providerError);
    util.checkError(util.querySelector('td#migration-intro'), constants.errorCode.providerError);
    util.checkError(util.findRegExp(/We've had a problem processing your request/i), constants.errorCode.providerError);
    util.checkError(util.findRegExp(/We\'re sorry\.\s*We are having some temporary delays\.\s*Please try again later\./i), constants.errorCode.providerError);
    // We've encountered an issue
    util.checkError(util.findRegExp(/We are unable to process your request at this time\./i), constants.errorCode.providerError);
    // This page is temporarily unavailable. Please try again later.
    util.checkError(util.findRegExp(/This page is temporarily unavailable\.\s*Please try again later\./i), constants.errorCode.providerError);
    // AccountOnline Temporarily Unavailable
    util.checkError(util.findRegExp(/>(AccountOnline Temporarily Unavailable)</i), constants.errorCode.providerError);
    // For your protection, we have blocked online access to your account.
    util.checkError(util.findRegExp(/For your protection, we have blocked online access to your account\./i), constants.errorCode.lockout);
    util.checkError(util.findRegExp(/For your security, online access has been blocked\./i), constants.errorCode.lockout);
    util.checkError(util.findRegExp(/For security reasons, we cannot allow you to complete this transaction online\./i), constants.errorCode.lockout);
    if(util.findRegExp(/<title>Migration \- Introduction Page/i))
        util.checkError('Your account should be migrated', constants.errorCode.providerError); /*checked*/
    // Citibank Online - Sign On Help - Verify Your Identity
    if (util.findRegExp(/For your security, please verify your identity by providing the following information\./i)
        || util.findRegExp(/At this time, we need more information to verify your account/i)
        // User Agreement
        || util.findRegExp(/Because this service only operates electronically you will be unable to proceed if you don\'t agree/i))
        util.checkError('Citibank (Thank You Rewards) website is asking you to verify your identity, until you do so we would not be able to retrieve your account information.', constants.errorCode.providerError); /*checked*/
    // Citi® Credit Cards - Credit Protector Enrollment
    if (util.findRegExp(/title>(Citi® Credit Cards - Credit Protector Enrollment)\s*<\/title/i)
        // Continue to Limited Site
        || util.querySelector('a#cmlink_CLALoginButton', false, /(Continue to Limited Site)/i)
        // Action Requested
        || util.querySelector('h2.intro-text-interstitial', false, /(It\'s easy to update the delivery method for your statements and legal notices to paperless\.)/i)
        // need to choose User ID for ATM/Debit Card
        || (util.querySelector('a#link_lkMultipleUIDCont', false, /^(Continue)$/i)
            && util.findRegExp(/(To improve our ability to service your account, we need to ask you to maintain only one User ID per ATM\/Debit Card\.)/i)))
        util.checkError('Citibank (Thank You Rewards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.', constants.errorCode.providerError); /*checked*/
    // Looks like you are having trouble signing on. Please try again.
    util.checkError(util.findRegExp(/>(Looks like you are having trouble signing on. Please try again\.)<\/span/i), constants.errorCode.providerError);
    // Please contact our customer service department immediately at the number on the back of your card to avoid an interruption of your next credit card transaction.
    util.checkError(util.findRegExp(/(Please contact our customer service department immediately at the number on the back of your card to avoid an interruption of your next credit card transaction\.)/i), constants.errorCode.providerError);

    // WARNING! -> this code switch to paperless statements - it's wrong
//    if (util.querySelector('a.intNavNext')) {
//        util.setNextStep(parse);
//        util.click('a.intNavNext');
//        return;
//    }

    if (util.querySelector('a#cmlink_svc_rmnd_me_later')) {
        util.setNextStep(parse);
        util.click('a#cmlink_svc_rmnd_me_later');
        return;
    }

    if (util.querySelector('a#cmlink_InterstitialRemindMeLater')) {
        util.setNextStep(parse);
        util.click('a#cmlink_InterstitialRemindMeLater');
        return;
    }

//    if (util.findRegExp(/javascript:noThanks/i)) {
    if (util.findRegExp(/javascript:remindmelater/i)) {
        util.setNextStep(parse);
//        page.evaluate('nothanks()');
        page.evaluate('remindmelater()');
        return;
    }

    if (util.findRegExp(/document.CBOLForm.submit/i)) {
        page.evaluate(function(){
            var form = document.forms['csqForm'];
            form.submit();
        });
    }

    // Questions
    if (util.findRegExp(/<p>In order to provide you with extra security, we occasionally need to ask for additional information when you access your accounts online\.<\/p>/i)
        || util.findRegExp(/<p>To confirm your identity, please answer the security questions you have set up. This is part of our fraud protection program to help keep your accounts secure.<\/p>/i)) {
        if (parseQuestion())
            return;
    }

    // We've encountered an issue and are working to fix the issue
    util.checkError(util.querySelector('span.dashboardFailSpanMsg', false, /(We\'ve encountered an issue and are working to fix the issue\.)/i), constants.errorCode.providerError);
    // I am sorry ...Our Server generated some error while processing your request.
    util.checkError(util.findRegExp(/(I am sorry \.\.\.Our Server generated some error while processing your request\.)\s*<\/td>/i), constants.errorCode.providerError);


    if (input.login == 'zinghram') {
        util.setNextStep(parse);
        setTimeout(page.open('https://online.citibank.com/US/JPS/portal/Index.do?userType=tyLogin'), 7000);
    }// if (input.login == 'zinghram')
    else
        util.waitFor(function () {
            return document.querySelector('a.signOffBtn') != null || document.querySelector('a[href *= "accountsummary/flow.action?JFP_TOKEN="]') != null;
        }, function () {
            setTimeout(parse, 7000);
        }, 10000);
}

function parseQuestion(){
    console.log('>>> parseQuestion');
    var question1 = page.evaluate(function(){
        if (typeof($) != 'undefined')
            return $.trim($('label[for = challengeQuesId]').not(':empty').filter(':eq(0)').text());
        else
            return null;
    });
    //
    if (!question1)
        question1 = page.evaluate(function(){
            console.log('alternative question 1');
            if (typeof($) != 'undefined')
                return $.trim($('label[for = challengeAnswers0]').text());
            else
                return null;
        });
    var question2 = page.evaluate(function(){
        if (typeof($) != 'undefined')
            return $.trim($('label[for = challengeQuesId]').not(':empty').filter(':eq(1)').text());
        else
            return null;
    });
    var question3 = page.evaluate(function(){
        if (typeof($) != 'undefined')
            return $.trim($('label[for = challengeQuesId]:visible').not(':empty').filter(':eq(2)').text());
        else
            return null;
    });
    if (!question2)
        question2 = page.evaluate(function(){
            console.log('alternative question 2');
            if (typeof($) != 'undefined')
                return $.trim($('label[for = challengeAnswers1]').text());
            else
                return null;
        });

    var error = util.querySelector("div.appError");
    console.log('question 1: ' + question1 + ", " + error);
    console.log('question 2: ' + question2 + ", " + error);
    console.log('question 3: ' + question3 + ", " + error);

    if (question1 && question2) {
        if (!input.answers[question2])
            util.askQuestion(question2, error);
        if (!input.answers[question1])
            util.askQuestion(question1, error);

        // ATM/Debit Card # must be at least 14 digits.
        if (input.answers[question1] && question1 == 'ATM/Debit Card #' && input.answers[question1].length < 14)
            util.askQuestion(question1, "ATM/Debit Card # must be at least 14 digits.");

        if (question3 && !input.answers[question3])
            util.askQuestion(question3, error);
        if(input.answers[question1] && input.answers[question2] && !answerSent){
            console.log('sending answer');
            answerSent = true;

            var answer = null;
            if (question3 && input.answers[question3])
                answer = input.answers[question3];

            util.evaluate(function() {
                console.log('answer 1: ' + arguments[0]);
                console.log('answer 2: ' + arguments[1]);
                var form = document.forms['/JRS/jrsCqContext'];
                if (form) {
                    form['cin'].value = arguments[0];
                    if (form['pin'])
                        form['pin'].value = arguments[1];
                    else
                        form['challengeQuesId1'].value = arguments[1];
                    if (arguments[2] && form['accountNumber'])
                        form['accountNumber'].value = arguments[2];
                    form.submit();
                }
                else {
                    form = document.forms['csqForm'];
                    form['challengeAnswers0'].value = arguments[0];
                    form['challengeAnswers1'].value = arguments[1];
                    doSubmitCsq();
                }
            }, input.answers[question1], input.answers[question2], answer);
            return true;
        }
        else{
            console.log('ask questions');
            if (question1 && !input.answers[question1])
                util.askQuestion(question1, error);
            if (question2 && !input.answers[question2])
                util.askQuestion(question2, error);
            if (question3 && !input.answers[question3])
                util.askQuestion(question3, error);
            util.exit();
        }
    }
    return false;
}

var adSkipped = false;

function parse() {
    console.log('>>> parse / retry ' + retry);
    // v6 - multiple cards
    if (retry == 0) {
        cards = page.evaluate(function() {
            if (typeof($) == 'undefined')
                return null;
            var cards = $('li.cards_select a');
            console.log('>> Total cards found: ' + cards.length);
            console.log('>> URL: ' + document.location.href);

            return cards.length;
        });
        // detected cards
        detectedCards = page.evaluate(function() {
            if (typeof($) == 'undefined')
                return null;
            var result = [];
            $('div.cT-accountName a#cmlink_AccountNameLink').each(function (i, value) {
                console.log("displayName: " + $(this).text());
                var displayName = $.trim($(this).text());
                var cardDescription = 'Does not earn points';
                if (-1 < displayName.indexOf('AAdvantage') || -1 < displayName.indexOf('AA card'))
                    cardDescription = 'Should be tracked separately as a separate American Airlines account added to AwardWallet';
                if (-1 < displayName.indexOf('Hilton'))
                    cardDescription = 'Should be tracked separately as a separate HHonors account added to AwardWallet';
                var code = displayName.replace(/[^\d]/ig, '');
                if (!code)
                    code = displayName.replace(/\s*/ig, '');
                if (displayName && displayName.replace(/\s*/ig, '') != 'CreditCard')
                    result.push({
                        Code: 'citybank' + code,
                        DisplayName: displayName,
                        CardDescription: cardDescription
                    });
            });
            console.log('>> #1 Total detected cards found: ' + result.length);
            $('div.cT-accountName a[href *= "/buscards/USBAO/unbilledactivity/flow.action"]').each(function (i, value) {
                console.log("displayName: " + $(this).text());
                var displayName = $.trim($(this).text());
                var cardDescription = 'Does not earn points';
                if (-1 < displayName.indexOf('AAdvantage') || -1 < displayName.indexOf('AA card'))
                    cardDescription = 'Should be tracked separately as a separate American Airlines account added to AwardWallet';
                if (-1 < displayName.indexOf('Hilton'))
                    cardDescription = 'Should be tracked separately as a separate HHonors account added to AwardWallet';
                var code = displayName.replace(/[^\d]/ig, '');
                if (!code)
                    code = displayName.replace(/\s*/ig, '');
                if (displayName)
                    result.push({
                        Code: 'citybank' + code,
                        DisplayName: displayName,
                        CardDescription: cardDescription
                    });
            });
            console.log('>> #2 Total detected cards found: ' + result.length);
            //$('a[id *= "cA-spf-accountNameLink"]:contains("Card-"), a[id *= "cA-spf-accountNameLink"]:contains("Preferred-"), a[id *= "cA-spf-accountNameLink"]:contains("Card®-"), a[id *= "cA-spf-accountNameLink"]:contains("Executive-"), a[id *= "cA-spf-accountNameLink"]:contains("Select®-"), a[id *= "cA-spf-closedAcctLink"]:contains("Card-"), a[id *= "cA-spf-closedAcctLink"]:contains("Preferred-"), a[id *= "cA-spf-closedAcctLink"]:contains("Card®-"), a[id *= "cA-spf-closedAcctLink"]:contains("Executive-"), a[id *= "cA-spf-closedAcctLink"]:contains("Select®-")').each(function (i, value) {
            $('a[id *= "cA-spf-accountNameLink"], a[id *= "cA-spf-closedAcctLink"]').each(function (i, value) {
                console.log("displayName: " + $(this).text());
                var displayName = $.trim($(this).text());
                var cardDescription = 'Does not earn points';
                if (-1 < displayName.indexOf('AAdvantage') || -1 < displayName.indexOf('AA card'))
                    cardDescription = 'Should be tracked separately as a separate American Airlines account added to AwardWallet';
                if (-1 < displayName.indexOf('Hilton'))
                    cardDescription = 'Should be tracked separately as a separate HHonors account added to AwardWallet';
                var code = displayName.replace(/[^\d]/ig, '');
                if (!code)
                    code = displayName.replace(/\s*/ig, '');
                if (displayName)
                    result.push({
                        Code: 'citybank' + code,
                        DisplayName: displayName,
                        CardDescription: cardDescription
                    });
            });
            console.log('>> #3 Total detected cards found: ' + result.length);

            // searching balances. New design 2
            var nodes = $('div.cA-spf-rewardsWrapper');
            nodes.each(function(index, el) {
                el = $(el);
                console.log('found row: ' + el.text());
                var number = el.find('div.cA-spf-rewardsAccountName').text().replace(/[^\d]/ig, '');
                var displayName = $.trim($('a[id *= "cA-spf-accountNameLink"]:contains("-'+ number +'")').text());
                if (displayName == '')
                    displayName = $.trim($('a[id *= "cA-spf-closedAcctLink"]:contains("-'+ number +'")').text());
                if (displayName == '')
                    displayName =  $.trim(el.find('div.cA-spf-rewardsAccountName').text());
                var code = displayName.replace(/[^\d]/ig, '');
                if (!code)
                    code = displayName.replace(/\s*/ig, '');
                console.log('displayName: ' + displayName + ', code: ' + code );

                // $('div.cA-spf-rewardsWrapper') does not have card name, it has only balance
                if (displayName == "" && code == "" && nodes.length == 1) {
                    var cardName = $('span.cS-accountMenuAccount span');
                    console.log("fixed provider bug: " + cardName.length);
                    if (cardName.length == 1) {
                        displayName = cardName.text();
                        code = displayName.replace(/[^\d]/ig, '');
                    }
                    console.log('displayName: ' + name + ', code: ' + code);
                }

                var cardDescription = 'Does not earn points';
                if (-1 < displayName.indexOf('AAdvantage') || -1 < displayName.indexOf('AA card'))
                    cardDescription = 'Should be tracked separately as a separate American Airlines account added to AwardWallet';
                if (-1 < displayName.indexOf('Hilton'))
                    cardDescription = 'Should be tracked separately as a separate HHonors account added to AwardWallet';

                if (displayName && displayName != '' && displayName.replace(/\s*/ig, '') != 'CreditCard')
                    result.push({
                        Code: 'citybank' + code,
                        DisplayName: displayName,
                        CardDescription: cardDescription
                    });
            });
            console.log('>> Total detected cards found: ' + result.length);

            return result;
        });
    }// if (retry == 0)
    console.log(">> DetectedCards: " + JSON.stringify(detectedCards));

    // v1
    var v1result = page.evaluate(function(){
        if (typeof($) == 'undefined') {
            console.log('no jquery');
            return {subAccounts: [], nextRowsCount: 0};
        }
        var result = [];
        console.log('searching balances');
        var nodes = $('tr.cT-rewardsHeadRow');
        console.log('found rewards: ' + nodes.length);

        nodes.each(function(index, el){
            el = $(el);
            console.log(' -------1-------- ');
            console.log('row: ' + el.html());
            console.log(' -------1-------- ');
        });

        nodes = nodes.nextAll();
        var nextRowsCount = nodes.length;
        console.log('next rows: ' + nodes.length);

        nodes.each(function(index, el){
            el = $(el);
            console.log(' -------2-------- ');
            console.log('row: ' + el.html());
            console.log(' -------2-------- ');
        });

        nodes = nodes.filter('tr.cT-firstRow');
        console.log('rewards rows: ' + nodes.length);

        var newDesign = false;
        if (nodes.length == 0) {
            nodes = $('tr.cT-rewardsHeadRow:has(span.cT-balanceIndicator1)');
            nextRowsCount = nodes.length;
            newDesign = true;
            console.log('rewards rows (New design): ' + nodes.length);
        }

        nodes.each(function(index, el){
            el = $(el);
            console.log('found row: ' + el.text());
            var name;
            if (newDesign)
                name = $.trim(el.find('span.cT-rewardsHeaderText').text());
            else {
                var subCards = el.find('div.cT-accountName').length;
                console.log('total cards found: ' + subCards);
                name = $.trim(el.find('div.cT-accountName').eq(subCards - 1).text());
                // to do "Active" all cards linked with points
                if (subCards > 1) {
                    for (var i = 0; i < subCards - 1; i++) {
                        var subCardName = $.trim(el.find('div.cT-accountName').eq(i).text());
                        var code = subCardName.replace(/[^\d]/ig, '');
                        if (!code)
                            code = subCardName.replace(/\s*/ig, '');
                        console.log('subCardName: ' + subCardName);
                        console.log('name: ' + name + ', subCard code: ' + code);
                        result.push({Code: 'citybank' + code, DisplayName: subCardName, Balance: ''});
                    }// for (var i = 0; i < subCards - 1; i++)
                }// if (subCards > 1)
            }
            var balance = el.find('div.cT-valueItem span.cT-balanceIndicator1').text();
            code = name.replace(/[^\d]/ig, '');
            if (!code)
                code = name.replace(/\s*/ig, '');
            console.log('name: ' + name + ', code: ' + code + ', balance: ' + balance);
            if (name != '' && balance != '' && name.indexOf("Autopay Status") == -1 && name.indexOf('AAdvantage') == -1 && name.indexOf('Hilton') == -1)
                result.push({Code: 'citybank' + code, DisplayName: name, Balance: balance});
        });
        return {subAccounts: result, nextRowsCount: nextRowsCount};
    });
    if (v1result.subAccounts.length > 0)
        subAccounts = subAccounts.concat(v1result.subAccounts);

    var v7result = page.evaluate(function(){
        if (typeof($) == 'undefined') {
            console.log('no jquery');
            return {subAccounts: [], nextRowsCount: 0};
        }
        var result = [];
        console.log('searching balances. New design 2');
        var nodes = $('div.cA-spf-rewardsWrapper');
        console.log('found rewards: ' + nodes.length);
        var nextRowsCount = nodes.length;

        nodes.each(function(index, el){
            el = $(el);
            console.log(' -------' + index + '-------- ');
            console.log('row: ' + el.html());
            console.log(' -------' + index + '-------- ');
        });

        nodes.each(function(index, el){
            el = $(el);
            console.log('found row: ' + el.text());
            var number = el.find('div.cA-spf-rewardsAccountName').text().replace(/[^\d]/ig, '');
            var name = $.trim($('a[id *= "cA-spf-accountNameLink"]:contains("-'+ number +'")').text());
            //var name = $.trim($('a[id *= "cA-spf-accountNameLink"]:contains("Card-'+ number +'")').text());
            //if (name == '')
            //    name = $.trim($('a[id *= "cA-spf-accountNameLink"]:contains("Preferred-'+ number +'")').text());
            //if (name == '')
            //    name = $.trim($('a[id *= "cA-spf-accountNameLink"]:contains("Card®-'+ number +'")').text());
            //if (name == '')
            //    name = $.trim($('a[id *= "cA-spf-accountNameLink"]:contains("Executive-'+ number +'")').text());
            //if (name == '')
            //    name = $.trim($('a[id *= "cA-spf-accountNameLink"]:contains("Select®-'+ number +'")').text());
            if (name == '')
                name = $.trim($('a[id *= "cA-spf-closedAcctLink"]:contains("-'+ number +'")').text());
            if (name == '')
                name =  $.trim(el.find('div.cA-spf-rewardsAccountName').text());
            var balance = el.find('div.cA-spf-rewardsValue span').text();
            var code = name.replace(/[^\d]/ig, '');
            if (!code)
                code = name.replace(/\s*/ig, '');
            console.log('name: ' + name + ', code: ' + code + ', balance: ' + balance);

            // $('div.cA-spf-rewardsWrapper') does not have card name, it has only balance
            if (name == "" && code == "" && balance != '' && nodes.length == 1) {
                var cardName = $('span.cS-accountMenuAccount span');
                console.log("fixed provider bug: " + cardName.length);
                if (cardName.length == 1) {
                    name = cardName.text();
                    code = name.replace(/[^\d]/ig, '');
                }
                console.log('name: ' + name + ', code: ' + code + ', balance: ' + balance);
            }

            if (name != '' && balance != '' && name.indexOf("Autopay Status") == -1 && /*name*/el.text().indexOf('AAdvantage') == -1
                && /*name*/el.text().indexOf('Hilton') == -1
                // AccountID = 2190477
                && el.find('div[id *= "screenReaderContent"]').text().indexOf('Total available Points Available points reflect HHonors points earned on the Hilton HHonors account associated with this credit card') == -1
                && balance != 'Not Available')
                result.push({Code: 'citybank' + code, DisplayName: name, Balance: balance});
        });
        return {subAccounts: result, nextRowsCount: nextRowsCount};
    });
    if (v7result.subAccounts.length > 0)
        subAccounts = subAccounts.concat(v7result.subAccounts);

    // detected cards
    detectedCards = page.evaluate(function(detectedCards, subAccounts) {
        console.log("subAccount: " + JSON.stringify(subAccounts));
        if (typeof (subAccounts) == 'undefined') {
            console.log('>>> wrong  subAccounts');
            return detectedCards;
        }
        // Update detected cards
        for (var card in detectedCards) {
            for (var i = 0; i < subAccounts.length; i++) {
                if (detectedCards.hasOwnProperty(card)) {
                    if (detectedCards[card].Code == subAccounts[ i ].Code) {
                        console.log(">> card with the same code: " + JSON.stringify(detectedCards[card]));
                        detectedCards[card]['DisplayName'] = subAccounts[ i ].DisplayName;
                        detectedCards[card]['CardDescription'] = 'Active';
                        console.log(">> New DetectedCard: " + JSON.stringify(detectedCards[card]));
                    }// if (detectedCards[card].Code == subAccount[0].Code)
                }// if (detectedCards.hasOwnProperty(card))
            }// for (i = 0; i < subAccounts.length; i++)
        }// for (var card in detectedCards)

        return detectedCards;
    }, detectedCards, subAccounts);
    // set SubAccounts
    if (subAccounts.length > 0) {
        // filter subAccounts without balance
        subAccounts = subAccounts.filter(function (subAccount, i) {
            console.log('>>> filter subAccounts without balance');
            if (subAccount.Balance != '')
                return subAccount;
            else
                console.log('skip invalid subAccount #' + i +': ' + subAccount.DisplayName);
        });
        util.setProperty('SubAccounts', subAccounts);
        util.setBalanceNA();
    }
    // DetectedCards
    if (detectedCards.length > 0) {
        // remove duplicates
        detectedCards = page.evaluate(function(detectedCards) {
            console.log('>>> [DetectedCard]: remove duplicates');
            var arr = [];
            for (var i = 0; i < detectedCards.length; i++) {
                var unique = true;
                for (var k = 0; k < arr.length; k++) {
                    if (arr[k].Code == detectedCards[i].Code)
                        unique = false;
                }
                if (unique) {
                    console.log('#' + i + ' -> ' + JSON.stringify(detectedCards[i]));
                    arr.push(detectedCards[i]);
                }
                else
                    console.log('Skip duplicate #' + i +' -> ' + JSON.stringify(detectedCards[i]));
            }// for (var i = 0; i < detectedCards.length; i++)
            return arr;
        }, detectedCards);
        util.setProperty('DetectedCards', detectedCards);
    }
    // Name
    util.setProperty('Name', util.beautifulName(util.querySelector('strong#user_name', false, /Welcome\s*(.+)/i)));
    if (typeof(output.properties['Name']) == 'undefined')
        util.setProperty('Name', util.beautifulName(util.querySelector('td.text_bold', false, /Welcome\s*(.+)/i)));
    if (typeof(output.properties['Name']) == 'undefined')
        util.setProperty('Name', util.beautifulName(util.querySelector('#cA-spf-WelcomeBarHeadline', false, /Welcome\s*back\s*\,\s*(.+)/i)));
    if(typeof(output.properties['Name']) != 'undefined' && v1result.nextRowsCount > 0 && output.errorCode != constants.errorCode.checked)
        util.setBalanceNA();
    console.log('typeof name: ' + typeof(output.properties['Name']));

    // v2
    var balanceV2 = page.evaluate(function(){
        if(typeof($) != 'undefined')
            return $('div.reward_points').not(':empty').filter(':first').text();
        else
            return null;
    });
    // Balance - v2
    if (balanceV2) {
        console.log('>>> Balance - v2: ' + balanceV2);
        util.setBalance(balanceV2);
    }// if (balanceV2)
    if (typeof(output.properties['Name']) == 'undefined')
        util.setProperty('Name', util.beautifulName(util.querySelector('span.welcome_msg', false, /Welcome(.+)/i)));
    if (typeof(output.properties['Name']) != 'undefined'
        && output.errorCode != constants.errorCode.checked
        && (util.findRegExp(/account assigned to this User ID/i)
            || (util.findRegExp(/Enjoy special access to purchase tickets to the best in music, sports and dining with/i)
                && detectedCards.length > 0)))
        util.setBalanceNA();

    // v3 (at&t)
    if (output.errorCode != constants.errorCode.checked) {
        var link = page.evaluate(function(){
            if(typeof($) != 'undefined')
                return $('div.reward_link a').attr('href');
            else
                return null;
        });
        console.log('rewards link: ' + link);
        if(link && link != '' && link != '#'){
            util.setNextStep(parseRewards3);
            page.open('https://www.accountonline.com' + link);
            return;
        }
    }

    // v4 (without jq)
    if(output.errorCode != constants.errorCode.checked){
        var name = util.findRegExp(/Logged In As: ([^<]+)/i);
        if (name && util.findRegExp(/For You/i)) {
            util.setProperty('Name', name);
            util.setBalanceNA();
        }
    }

    // v5 - link to balance
    if (output.errorCode != constants.errorCode.checked) {
        var link = page.evaluate(function(){
            if(typeof($) == 'undefined')
                return null;
            var div = $('div#info_reward_def_point-content');
            console.log('rewards div: ' + div.length);
            var link = div.next('a:contains("See Details")');
            console.log('next link: ' + link.length);
            if(link.length > 0)
                return link.attr('href');
            else
                return null;
        });
        console.log('rewards link: ' + link);
        if(link != null && link != ''){
            util.setNextStep(parseRewards3);
            //page.open('https://www.accountonline.com' + link);
            page.open('https://accountonline.citi.com' + link);
            return;
        }
    }

    // v6 - multiple cards
    if (retry > 2) {
        var v6result = page.evaluate(function(){
            if (typeof($) == 'undefined') {
                console.log('no jquery');
                return {subAccounts: [], nextRowsCount: 0};
            }
            var result = [];
            console.log('multiple cards: searching balances');
            var name = $('td.cH-name').text().trim();
            var balance = $('tr.cT-rewardsHeadRow').find('div.cT-valueItem span.cT-balanceIndicator1').last().text();
            if (!balance)
                balance = $('tr.cT-rewardsHeadRow').find('div.cT-valueItem span.cT-balanceIndicator1').text();
            var code = name.replace(/[^\d]/ig, '');
            if (!code)
                code = name.replace(/\s*/ig, '');
            console.log('name: ' + name + ', code: ' + code + ', balance: ' + balance);
            if (name != '' && balance != '')
                result.push({Code: 'citybank' + code, DisplayName: name, Balance: balance});
            return {subAccounts: result};
        });
        if (v6result.subAccounts.length > 0)
            subAccounts = subAccounts.concat(v6result.subAccounts);
        // new design
        var newResult = page.evaluate(function(){
            if (typeof($) == 'undefined') {
                console.log('no jquery');
                return {subAccounts: [], nextRowsCount: 0};
            }
            var result = [];
            console.log('New design: searching balances');
            var code = $('span.card-no').text().trim();
            var name = $('span.card-type').text().trim() + ' ' + code;
            var balance = $('span.price').text().trim();
            if (!code)
                code = name.replace(/\s*/gi, '');
            console.log('name: ' + name + ', code: ' + code + ', balance: ' + balance);

            if (name != '' && balance != '' && name.indexOf('AAdvantage') == -1 && name.indexOf('Hilton') == -1)
                result.push({Code: 'citybank' + code, DisplayName: name, Balance: balance});
            return {subAccounts: result};
        });
        // account without balance
        if (newResult.subAccounts.length == 0
            && util.querySelector('div#ctl00_ctl00_cphMainContent_cphHeader_pnlNoPoints'))
            util.setBalanceNA();

        if (newResult.subAccounts.length > 0)
            subAccounts = subAccounts.concat(newResult.subAccounts);
        // detected cards
        detectedCards = page.evaluate(function(detectedCards, subAccounts) {
            console.log("subAccount: " + JSON.stringify(subAccounts));
            if (typeof (subAccounts) == 'undefined') {
                console.log('>>> wrong  subAccounts');
                return detectedCards;
            }
            // Update detected cards
            for (var card in detectedCards) {
                for (var i = 0; i < subAccounts.length; i++) {
                    if (detectedCards.hasOwnProperty(card)) {
                        if (detectedCards[card].Code == subAccounts[ i ].Code) {
                            console.log(">> card with the same code: " + JSON.stringify(detectedCards[card]));
                            detectedCards[card]['DisplayName'] = subAccounts[ i ].DisplayName;
                            detectedCards[card]['CardDescription'] = 'Active';
                            console.log(">> New DetectedCard: " + JSON.stringify(detectedCards[card]));
                        }// if (detectedCards[card].Code == subAccount[0].Code)
                    }// if (detectedCards.hasOwnProperty(card))
                }// for (i = 0; i < subAccounts.length; i++)
            }// for (var card in detectedCards)

            return detectedCards;
        }, detectedCards, subAccounts);
        // set SubAccounts
        if (subAccounts.length > 0) {
            util.setProperty('SubAccounts', subAccounts);
            util.setBalanceNA();
        }
        else if (output.errorCode != constants.errorCode.checked
            && util.querySelector('a.right-arrow')
            && util.querySelector('span.card-type')
            && util.querySelector('span.card-no')
            && util.findRegExp(/Explore Ways To Use ThankYou Points/i)) {
            console.log('New design: Explore Ways To Use ThankYou Points');
            parseThankYouPointsLink = page.evaluate(function(){
                var link = $('a.right-arrow').attr('href');
                console.log('Go to -> ' + link);
                return link;
            });
            page.open(parseThankYouPointsLink);
            util.setNextStep(parseThankYouPoints);
            return;
        }
        // n\a
        else if (output.errorCode != constants.errorCode.checked
            && util.querySelector('span.card-type')
            && util.querySelector('span.card-no')
            && util.findRegExp(/Your Savings Spotlight/i)
            && detectedCards.length > 0) {
            util.setBalanceNA();
            console.log('New design: Your Savings Spotlight');
            return;
        }
        // DetectedCards
        if (detectedCards.length > 0)
            util.setProperty('DetectedCards', detectedCards);
        // Name
        if (typeof(output.properties['Name']) == 'undefined')
            util.setProperty('Name', util.beautifulName(util.querySelector('#accountInformation td.text_bold', false, /Welcome(.+)/i)));
    }// if (retry > 2) - v6 - multiple cards

    if (output.errorCode != constants.errorCode.checked) {
        // skip some ads
        var url = page.evaluate(function(){ return document.location.href });
        if (url != 'https://www.accountonline.com/cards/svc/Dashboard.do'
            && !adSkipped
            && util.findRegExp(/Dashboard\.do/i)) {
            adSkipped = true;
            console.log('skipping ad');
            util.setNextStep(parse);
            page.open('https://www.accountonline.com/cards/svc/Dashboard.do');
        }

        // closed accounts or other providers
        if (typeof(output.properties['Name']) != 'undefined'
            && util.findRegExp(/This account is closed|CLOSED ACCOUNT|AAdvantage<sup>[^<]*<\/sup>[^<]*miles|We're sorry. We are having some temporary delays\.\s*Please try again later\./i) != null)
            util.setBalanceNA();

        // only 'Checking' cards
        var checking = page.evaluate(function() {
            console.log('Find "Checking" cards...');
            var text = $('a#cmlink_AccountNameLink:contains("Checking-")');
            if (!text)
                return null;
            console.log('Card -> ' + text.text());
            return text.text();
        });
        if (typeof(output.properties['Name']) != 'undefined' && checking != null) {
            // Rewards information temporarily unavailable. Please try again later.
            util.checkError(util.querySelector('span.cA-spf-rewardsErrorText', false, /(Rewards information temporarily unavailable\.\s*Please try again later\.)/i), constants.errorCode.providerError);
            util.setBalanceNA();
        }

        console.log('--- find error');
        util.checkError(util.querySelector('div.cT-alerts', false, /((?:This account is closed|This account has been converted to a new number or was closed at your request)\.)/i), constants.errorCode.providerError);
        // Account activation
        if (util.findRegExp(/<p>To activate your new card, just enter the following information, then click the Activate button\.<\/p>/i) != null
            || util.findRegExp(/<h\d>Please Update Your Contact Information<\/h\d>/i) != null
            || util.findRegExp(/<h\d>Activate Your New Card<\/h\d>/i) != null)
            util.checkError('Citibank (Thank You Rewards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.', constants.errorCode.providerError); /*checked*/
        // Citi® Credit Cards - Credit Protector Enrollment
        if (util.findRegExp(/<title>(Citi® Credit Cards - Credit Protector Enrollment)\s*<\/title>/i) != null)
            util.checkError('Citibank (Thank You Rewards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.', constants.errorCode.providerError); /*checked*/
        // Contact Customer Service immediately to avoid transaction interruptions
        if (util.findRegExp(/(Contact Customer Service immediately to avoid transaction interruptions\.)/i) != null)
            util.checkError("We can't find balance on your card(s). Please contact us, if you know how to find it.", constants.errorCode.providerError);
        // We’re sorry our site is not allowing you to complete your activities.
        util.checkError(util.findRegExp(/<td>(We\’re sorry our site is not allowing you to complete your activities\.)/i), constants.errorCode.providerError);
        // We've Detected Suspicious Activity
        util.checkError(util.findRegExp(/>\s*(We've Detected Suspicious Activity)\s*<\/h1>/i), constants.errorCode.providerError);
        // I am sorry ...Our Server generated some error while processing your request.
        util.checkError(util.findRegExp(/(I am sorry\s*\.\.\.Our Server generated some error while processing your request\.)/i), constants.errorCode.providerError);

        // This page is temporarily unavailable. Please try again later.
        if (util.findRegExp(/(This page is temporarily unavailable\.\s*Please try again later\.)\s*<\//i)) {
            errorText = util.findRegExp(/(This page is temporarily unavailable\.\s*Please try again later\.)\s*<\//i);
            console.log('Error -> ' + errorText);
            pageUnavailable = true;
        }
    }// if (output.errorCode != constants.errorCode.checked)

    // Balance is not found for AAdvantage® card
    if (output.errorCode != constants.errorCode.checked
        && util.findRegExp(/AAdvantage® miles earned on last statement|>New Citi<\/a>/i) != null) {
        util.setBalanceNA();
    }

    // retry
    console.log('Retry ' + retry);
    var manualRedirect;

    // prevent loop
    var exclude = page.evaluate(function(login) {
        var result = true;
        if ($.inArray(login, ["njramos", "aaronminorbest", "shuboshi", "gsaunds1", "sail0729", "41237BB", "dilly0989", "resore052281", "karl_wang86", "cmoore2424"]) !== -1)
            result = false;
        console.log('Skip "retry 2" ' + result);

        return result;
    }, input.login);
    if (retry == 2 && !exclude) {
        console.log('Next "retry 3"');
        retry++;
        if (output.errorCode != constants.errorCode.checked && typeof(output.properties['Name']) != 'undefined') {
            util.setBalanceNA();
        }
    }

    if (output.errorCode != constants.errorCode.checked && retry < 1) {
        setTimeout(function(){
            retry++;
            manualRedirect = 'https://www.accountonline.com/cards/svc/Dashboard.do';
            console.log('Go to ' + manualRedirect);
            util.setNextStep(parse);
            page.open(manualRedirect);
        }, 2000)
    }
    else if (output.errorCode != constants.errorCode.checked && retry == 1) {
        setTimeout(function(){
            retry++;
            manualRedirect = 'https://online.citibank.com/US/CBOL/ain/dashboard/flow.action';
            console.log('Go to ' + manualRedirect);
            util.setNextStep(parse);
            page.open(manualRedirect);
        }, 1000)
    }
    else if (output.errorCode != constants.errorCode.checked && retry == 2) {
        setTimeout(function(){
            retry++;
            manualRedirect = 'https://www.cardbenefits.citi.com/Dashboard.aspx';
            console.log('Go to ' + manualRedirect);
            util.setNextStep(parse);
            page.open(manualRedirect);
        }, 1000)
    }
    // v6 - multiple cards
    else if (output.errorCode != constants.errorCode.checked && retry == 3 || (retry > 3 && cards > 0)) {
        setTimeout(function(){
            retry++;
            manualRedirect = 'https://www.accountonline.com/buscards/USBAO/accountsummary/flow.action';
            console.log('Go to ' + manualRedirect);
            util.setNextStep(multipleCards);
            page.open(manualRedirect);
        }, 1000)
    }
    // This page is temporarily unavailable. Please try again later.
    else if (output.errorCode != constants.errorCode.checked) {
        console.log('>>> exit');
        util.checkError(util.findRegExp(/(This page is temporarily unavailable\.\s*Please try again later\.)\s*<\//i), constants.errorCode.providerError);
        if (pageUnavailable && errorText !== null) {
            console.log('>>> pageUnavailable = true');
            util.checkError(errorText, constants.errorCode.providerError);
        }
    }
    // refs #11308
    else if (util.findRegExp(/<a[^>]+alt=\"FICO\(r\) Credit Score\"/i)) {
        console.log('>>> FICO Score');
        manualRedirect = 'https://www.cardbenefits.citi.com/Products/Free-FICO-Score.aspx';
        console.log('Go to ' + manualRedirect);
        util.setNextStep(parseFICOScore);
        page.open(manualRedirect);
    }
    else
        util.exit('done');
}

function multipleCards() {
    console.log('>>> multipleCards');
    if (cards > 0) {
        util.setNextStep(parse);
        cards = page.evaluate(function(cards) {
            console.log('>>> cards.length ' + cards);
            cards--;
            console.log('>>> cards.length ' + cards);
            var card = $('li.cards_select a:eq('+ cards +')');
            console.log('Loading card: ' + card.text());
            // select card
            card.click();
            card.click();
            $('a#cards_go_button').click();
            return cards;
        }, cards);
    }// if (cards.length > 0)
    else {
        console.log('>>> multipleCards exit');
        if (output.errorCode != constants.errorCode.checked && pageUnavailable && errorText !== null) {
            console.log('>>> pageUnavailable = true');
            util.checkError(errorText, constants.errorCode.providerError);
        }// if (output.errorCode != constants.errorCode.checked && pageUnavailable && errorText !== null)

        util.exit('multipleCards done');
    }
}

function parseFICOScore() {
    // Your FICO® Score is ...
    util.setProperty('FICOScore', util.querySelector('div.credit-score-display div h2 strong'));
    // FICO Score updated on
    util.setProperty('FICOScoreUpdatedOn', util.querySelector('span.fico-score-lastDate', false, /as\s*of\s*([^<]+)/i));
    util.exit('parseFICOScore done');
}

function parseThankYouPoints() {
    util.setBalance(util.querySelector('span.points'));
    util.exit('parseThankYouPoints done');
}

function parseRewards3(){
    util.setBalance(page.evaluate(function(){
        if(typeof($) == 'undefined')
            return null;
        var cell = $('td:contains("Total ThankYou Points Transferred to Your ThankYou Member Account")');
        if(cell.length == 0)
            cell = $('td:contains("Total ThankYou Points Earned This Billing Period")');
        if(cell.length == 0)
            cell = $('td:contains("Total ThankYou Points Earned")');
        return cell.next().text();
    }));
    util.exit('parseRewards3 done');
}