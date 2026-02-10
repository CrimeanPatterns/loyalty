var plugin = {

	hosts:{'membership.usairways.com':true, 'reservations.usairways.com':true},

	getStartingUrl:function (params) {
		return 'https://membership.usairways.com/Manage/AccountSummary.aspx';
//        return 'https://membership.usairways.com/Manage/YourMiles.aspx';
	},

	start:function (params) {
		if (plugin.isLoggedIn()) {
			if (plugin.isSameAccount(params.account))
				plugin.loginComplete(params);
			//provider.complete();
//                plugin.loadAccount();
			else
				plugin.logout();
		}
		else
			plugin.login(params);
	},

	isLoggedIn:function () {
		browserAPI.log("isLoggedIn");
		if ($('a#ctl00_loginView_lnkLogOut').text() == 'Log out') {
			browserAPI.log("LoggedIn");
			return true;
		}
		if ($('#ctl00_phMain_loginModule_ctl00_loginForm_LoginBtnMiddle a').text() == 'Log in') {
			browserAPI.log("not LoggedIn");
			return false;
		}
		browserAPI.log("can't determine");
		provider.setError("Can't determine login state");
		throw "Can't determine login state";
	},

	isSameAccount:function (account) {
		// for debug only
		//browserAPI.log("account: " + JSON.stringify(account));
		var number = $('td:contains("AAdvantage number") + td').text();
		browserAPI.log("number: " + number);
		return ((typeof(account.properties) != 'undefined')
			&& (typeof(account.properties.Number) != 'undefined')
			&& (account.properties.Number != '')
			&& (number == account.properties.Number));
	},

	logout:function () {
		provider.setNextStep('login');
		document.location.href = 'https://membership.usairways.com/Logout.aspx?ReturnUrl=http%3a%2f%2fmembership.usairways.com%2fManage%2fAccountSummary.aspx';
	},

	login:function (params) {
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId == 0) {
			provider.setNextStep('getConfNoItinerary');
			document.location.href = "http://reservations.usairways.com/Default.aspx";
			return;
		}
		browserAPI.log("login");
		var form = $('form[name = "aspnetForm"]');
		if (form.length == 0)
			form = $('#aspnetForm');
		if (form.length > 0) {
			browserAPI.log("submitting saved credentials");
			form.find('input[name = "ctl00$phMain$loginModule$ctl00$loginForm$UserName"]').val(params.account.login);
			form.find('input[name = "ctl00$phMain$loginModule$ctl00$loginForm$txtLastName"]').val(params.account.login2);
			form.find('input[name = "ctl00$phMain$loginModule$ctl00$loginForm$Password"]').val(params.account.password);

			provider.setNextStep('checkLoginErrors');
			var script = document.createElement('script');
			script.type = 'text/javascript';
			script.innerHTML = 'WebForm_DoPostBackWithOptions(new WebForm_PostBackOptions("ctl00$phMain$loginModule$ctl00$loginForm$Login", "", true, "", "", false, true))';
			document.head.appendChild(script);
		}
		else
			provider.setError('code 1');
	},

	checkLoginErrors:function (params) {
		var errors = $('table.tblsserror');
		if (errors.length > 0)
			provider.setError(errors.text());
		else {
			//provider.complete();
			plugin.loginComplete(params);
		}
//            plugin.loadAccount();
	},

	loginComplete:function (params) {
		if (typeof(params.account.itineraryAutologin) == "boolean" && params.account.itineraryAutologin && params.account.accountId > 0) {
			var flights = $('table.passsummary');
			if (flights.length == 0) {
				provider.setNextStep('toItineraries');
				document.location.href = 'https://membership.usairways.com/Manage/AccountSummary.aspx';
			}
			else {
				plugin.toItineraries(params);
			}
		}
		else
			provider.complete();
	},

	toItineraries:function (params) {
		var link = $('a[href*="pnr=' + params.account.properties.confirmationNumber + '"]');
		if (link.length > 0) {
			provider.setNextStep('itLoginComplete');
			document.location.href = link.attr('href');
		}
		else {
			if (typeof(params.account.properties.confFields) != "object")
				provider.setError('Itinerary not found');
			else {
				provider.setNextStep('getConfNoItinerary');
				document.location.href = "http://reservations.usairways.com/Default.aspx";
			}
		}
	},

	getConfNoItinerary:function (params) {
		var properties = params.account.properties.confFields;
		browserAPI.log(JSON.stringify(properties));
		var form = $('form#aspnetForm');
		if (form.length > 0) {
			form.find('select[name="ctl00$phMain$ManageReservationModule$ctl00$rezLookup$LookupByDropdown"]').val("ConfirmationCodeOrTicketNumber");
			form.find('input[name="ctl00$phMain$ManageReservationModule$ctl00$rezLookup$ConfirmationCodeOrTicketNoTextBox"]').val(properties.ConfNo);
			form.find('input[name="ctl00$phMain$ManageReservationModule$ctl00$rezLookup$DepartureDateTextBox$SelectedDate"]').val(properties.Depart);
			provider.setNextStep('itLoginComplete');
			$('#ctl00_phMain_ManageReservationModule_ctl00_rezLookup_SubmitButton').click();
			provider.eval('WebForm_DoPostBackWithOptions(new WebForm_PostBackOptions("ctl00$phMain$ManageReservationModule$ctl00$rezLookup$SubmitButton", "", true, "ctl00_phMain_ManageReservationModule_ctl00_rezLookup_vg", "", false, true))');
		}
		else
			provider.setError('Itienrary not found');
	},

	itLoginComplete:function (params) {
		provider.complete();
	},

	findRegExp:function (elem, regExp, required) {
		var matches = regExp.exec(elem);
		if (matches) {
			browserAPI.log('matched regexp: ' + regExp);
			result = matches[1];
		}
		else {
			browserAPI.log('failed regexp: ' + regExp);
			if (required)
				browserAPI.log('regexp not found');
			else
				result = null;
		}

		return util.trim(result);
	},

	loadAccount:function () {
		browserAPI.log("loadAccount");
		if (params.autologin) {
			provider.complete();
			return;
		}

//        if (document.location.href != 'https://membership.usairways.com/Manage/YourMiles.aspx'){
//            document.location.href = 'https://membership.usairways.com/Manage/YourMiles.aspx';
//            provider.setNextStep('parse');
//        }
//        else
//            plugin.parse(params);
	},

	parse:function (params) {
		browserAPI.log("parse");
		var data = {};
		// Balance
		var balance = $('h3:contains("Balance:")').text();
		balance = plugin.findRegExp(balance, /:\s*([\d\.\,]+)/i);
		if (balance.length > 0) {
			browserAPI.log("Balance: " + balance);
			data.Balance = util.trim(balance);
		} else
			browserAPI.log("Balance not found");
		// Name
		var name = $('#ctl00_phMain_yourMileModule_ctl00_lblName').text();
		if (name.length > 0) {
			browserAPI.log("Name: " + util.trim(name));
			data.Name = util.trim(name);
		} else
			browserAPI.log("Name not found");
		// Dividend Miles number
		var Number = $('td:contains("Dividend Miles number")').next('td').text();
		if (Number.length > 0) {
			browserAPI.log("Dividend Miles number: " + util.trim(Number));
			data.Number = util.trim(Number);
		} else
			browserAPI.log("Dividend Miles number not found");
		// Preferred status
		var memberStatus = $('a:contains("Preferred status")').parent('td').next('td').text();
		if (memberStatus.length > 0) {
			browserAPI.log("Preferred status: " + util.trim(memberStatus));
			data.Status = util.trim(memberStatus);
		} else
			browserAPI.log("Preferred status not found");
		// Future Status
		var futureStatus = $('#ctl00_phMain_yourAccountModule_ctl00_dmStatu').text();
		if (futureStatus.length > 0) {
			browserAPI.log("Future status: " + util.trim(futureStatus));
			data.FutureStatus = util.trim(futureStatus);
		} else
			browserAPI.log("Future status not found");
		// Preferred qualifying miles
		var qualifyingMiles = $('a:contains("Preferred qualifying miles")').parent('td').next('td').text();
		if (qualifyingMiles.length > 0) {
			browserAPI.log("Preferred qualifying miles: " + util.trim(qualifyingMiles));
			data.QualifyingMiles = util.trim(qualifyingMiles);
		} else
			browserAPI.log("Preferred qualifying miles not found");


		// Last Activity
		var expiration = $('table.viewmiles').find('tr:last').prev('tr').children('td:eq(1)').text();
		browserAPI.log("expiration: " + util.trim(expiration));
		expiration = util.trim(expiration);
		if (expiration.length > 0) {
			browserAPI.log("Last Activity: " + expiration);
			data.LastActivity = expiration;
			// Expiration Date
			var date = new Date(expiration + ' UTC');
			if (!isNaN(date)) {
				// ExpirationDate = lastActivity" + "18 months"
				date.setMonth(date.getMonth() + 18);
				var unixtime = date / 1000;
				if (date != 'NaN') {
					browserAPI.log("ExpirationDate = lastActivity + 18 months");
					browserAPI.log("Expiration Date: " + date + " Unixtime: " + unixtime);
					data.AccountExpirationDate = unixtime;
				}
			} else
				browserAPI.log("Invalid Expiration Date");
		} else
			browserAPI.log("Last Activity not found");

		provider.saveProperties(data);

		// if clicked "retrieve reservations"
		if (typeof(params.account.parseItineraries) == 'boolean' && params.account.parseItineraries) {
			// Parsing itineraries
			provider.setNextStep('parseItineraries');
			document.location.href = 'https://membership.usairways.com/Manage/AccountSummary.aspx';
		}
		// else just retrieve balance
		else
			provider.complete();
	},

	parseItineraries:function (params) {
		browserAPI.log("parseItineraries");

		if (plugin.findRegExp($('#ctl00_ContentInfo_trNoCurrentReservations').text(), /(No Current Reservations)/i)) {
			browserAPI.log("No Current Reservations");
			params.account.properties.Itineraries = [
				{ NoItineraries:true }
			];
			provider.saveProperties(params.account.properties);
			provider.complete();
			return;
		}

		params.data.Itineraries = [];
		params.data.links = [];
		params.data.TripSegments = [];
		params.data.Rentals = [];

		var links = $('#ctl00_phMain_yourFlightsModule_ctl00_resSummaryGrid_upFlightSummary').find('a:contains("View")');
		browserAPI.log('Total links ' + links.length);
		for (var i = 0; i < links.length; i++) {
			var link = links.eq(i).attr('href');
			params.data.links.push(link);
		}

		provider.setNextStep('parseItinerary');
		document.location.href = params.data.links.shift();
	},

	parseItinerary:function (params) {
		browserAPI.log("parseItinerary");

		var result = {};
		var depCode = null;
		var depart = null;
		var depTime = null;
		var DT = null;
		var arrCode = null;
		var arrive = null;
		var arrTime = null;
		var unixtime = null;
		var duration = null;
		var flightNumber = null;
		var aircraft = null;
		var bookingClass = null;
		var meal = null;

		// ConfirmationNumber
		result.RecordLocator = $('h2:contains("Confirmation code:")').text();
		result.RecordLocator = plugin.findRegExp(result.RecordLocator, /Confirmation code:([^<]+)/i);
		if (result.RecordLocator == '' && $('p:contains("You can change your whole trip before you travel")').text()) {
			provider.setNextStep('parseItinerary');
			document.location.href = params.data.links.shift();
			return;
		}
		browserAPI.log("RecordLocator: " + result.RecordLocator);

		// Passengers
		var passanger = plugin.unionArray($('span[id $= lblPassengerName]'), ', ', true);
		browserAPI.log("Passengers: " + passanger);
		result.Passengers = passanger.replace(/, $/, '');
		if (result.Passengers == '') delete result.Passengers;

		// AccountNumbers
		var accountNumbers = plugin.unionArray($('span[id $= blFrequentFlyerAirlineCombo]'), ', ', true);
//        browserAPI.log("AccountNumbers: " + accountNumbers);
		result.AccountNumbers = accountNumbers.replace(/[^\d\,]/g, '');
		browserAPI.log("AccountNumbers: " + result.AccountNumbers);
		if (result.AccountNumbers == '') delete result.AccountNumbers;

		// ReservationDate
		var reservationDate = $('h4:contains("Original date issued:")').parent('div').next('div').text();
		reservationDate = new Date(reservationDate + ' UTC');
		unixtime = reservationDate / 1000;
		if (!isNaN(unixtime)) {
			browserAPI.log("ReservationDate: " + reservationDate + " Unixtime: " + unixtime);
			result.ReservationDate = unixtime;
		} else
			browserAPI.log(">>> Invalid ReservationDate");

		// Total
		var total = $('span[id $= lblTotalFare]').text();
		browserAPI.log("TotalCharge: " + total);
		result.TotalCharge = total;
		// Currency
		if (plugin.findRegExp(result.TotalCharge, /(\$)/))
			result.Currency = 'USD';
		browserAPI.log("Currency: " + result.Currency);

		// Segments
		var i = 0;
		$('div[class = "spaceleftsm citypair"]').each(function () {
			var node = $(this);
			var details = node.next('div.padtopxsm');
//            console.log(details);
			browserAPI.log(details);
			browserAPI.log(">>> Trip " + i);
			var k = 0;

			// Status
			var status = node.find('span[id $= lblStatus]').text();
			browserAPI.log("Status: " + status);
			if (status == 'Canceled' || status == 'Used ' || status == 'Refunded') {
				browserAPI.log("<<< Trip " + i);
				i++;
				provider.setNextStep('parseItinerary');
				document.location.href = params.data.links.shift();
				return;
			}
			node.Status = status;

			// FlightNumber
			flightNumber = details.find('td $= tdFlightNbr').text();
			browserAPI.log("FlightNumber: " + flightNumber);
			node.FlightNumber = flightNumber;

			// DepDate
			depTime = details.find('td[id $= departColumn]').text();
			browserAPI.log("time: " + depTime);
			depart = node.find('span[id $= departDateValueLabel]').text();
			browserAPI.log("depart: " + depart);
//            var departDay = plugin.findRegExp(depart, /\.m\.\s*\+?([\d]+)\s*Day/i);
			DT = depart + ' ' + depTime;
//            DT = util.trim(DT.replace(/\.m\.([^<]+)/g, 'm'));
////                browserAPI.log("DT: " + DT);
//            DT = util.trim(DT.replace(/\./g, ''));
////                browserAPI.log("DT: " + DT);
			DT = new Date(DT + ' UTC');
//            if (departDay) {
//                browserAPI.log("day: +" + departDay);
//                DT.setDate(DT.getDate() + parseFloat(departDay));
//                browserAPI.log("Date: " + DT);
//            }
			unixtime = DT / 1000;
			if (!isNaN(unixtime)) {
				browserAPI.log("DepDate: " + depart + ' ' + depTime + " Unixtime: " + unixtime);
				node.DepDate = unixtime;
			} else
				browserAPI.log(">>> Invalid DepDate");
			// DepName
			node.DepName = plugin.unionArray(node.find('span[id $= departCityLabel]').text(), ', ', true);
			browserAPI.log("DepName: " + node.DepName);
			// DepCode
			node.DepCode = details.find('span[id $= departAirportHoverLink]').text();
			browserAPI.log("DepCode: " + node.DepCode);

			// ArrDate
			arrTime = details.find('td[id $= arrivalColumn]').text();
			browserAPI.log("arrive time: " + arrTime);
			arrive = node.find('span[id $= departDateValueLabel]').text();
			browserAPI.log("arrive: " + arrive);
//            var arriveDay = plugin.findRegExp(arrTime, /\.m\.\s*\+?([\d]+)\s*Day/i);
			DT = arrive + ' ' + arrTime;
//            DT = util.trim(DT.replace(/\.m\.([^<]+)/, 'm'));
////                browserAPI.log("DT: " + DT);
//            DT = util.trim(DT.replace(/\./g, ''));
////                browserAPI.log("DT: " + DT);
			DT = new Date(DT + ' UTC');
//            if (arriveDay) {
//                browserAPI.log("day: +" + arriveDay);
//                DT.setDate(DT.getDate() + parseFloat(arriveDay));
//                browserAPI.log("Date: " + DT);
//            }
			unixtime = DT / 1000;
			if (!isNaN(unixtime)) {
				browserAPI.log("ArrDate: " + arrive + ' ' + arrTime + " Unixtime: " + unixtime);
				node.ArrDate = unixtime;
			} else
				browserAPI.log(">>> Invalid ArrDate");
			// ArrName
			node.ArrName = node.find('span[id $= arrivalCityLabel]').text();
			browserAPI.log("ArrName: " + node.ArrName);
			// ArrCode
			node.ArrCode = arrCode = node.find('span[id $= arriveAirportHoverLink]').text();
			browserAPI.log("ArrCode: " + node.ArrCode);

			// Duration
			node.Duration = details.find('td[id $= durationColumn]').text();
			browserAPI.log("duration: " + node.Duration);

			// Seats
			var seats = plugin.unionArray(details.find('a[id $= chooseSeatsLink]'), ', ', false);
			node.Seats = seats.replace(/, $/, '');
			browserAPI.log("Seats: " + node.Seats);

			// Aircraft
			node.Aircraft = details.find('td[id $= aircraftColumn]').text();
			browserAPI.log("Aircraft: " + node.Aircraft);

			// BookingClass
			bookingClass = details.find('td[id $= cabinColumn]').text();
			node.Cabin = plugin.findRegExp(bookingClass, /([^\(]+)/i);
			browserAPI.log("BookingClass: " + node.Cabin);
			node.BookingClass = plugin.findRegExp(bookingClass, /\(([^\)]+)/i);
			browserAPI.log("BookingClass: " + node.BookingClass);

			// Meal
			meal = plugin.unionArray(details.find('td[id $= mealColumn]').text());
			node.Meal = meal.replace(/, $/, '');
			browserAPI.log("Meal: " + node.Meal);


			browserAPI.log("<<< Trip " + i);
			i++;
		});

		result.TripSegments = params.data.TripSegments;
		params.data.Itineraries.push(result);
		params.data.TripSegments = [];

//        if (params.data.links.length == 0) {
//            params.account.properties.Itineraries = params.data.Itineraries;
//            params.account.properties.Rentals = params.data.Rentals;
//            provider.saveProperties(params.account.properties);
//            provider.complete();
//        }
//        else{
//            provider.setNextStep('parseItinerary');
//            document.location.href = params.data.links.shift();
//        }
	},

	unionArray:function (elem, separator, unique) {
		// $.map not working in IE 8, so iterating through items
		var result = [];
		for (var i = 0; i < elem.length; i++) {
			var text = util.trim(elem.eq(i).text());
			if (text != "" && (!unique || result.indexOf(text) == -1))
				result.push(text);
		}
		return result.join(separator);
	}

}