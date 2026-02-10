<?php

function autoRegistrationEmiles($properties)
{
    $checker = new TAccountChecker();
    $checker->InitBrowser();
    $checker->http->GetURL("https://www.e-miles.com/awenroll");

    if (!$checker->http->ParseForm("enrollmentForm")) {
        return ['success' => false, 'errorMessage' => 'Register form not found.'];
    }
    // First Name
    $checker->http->SetInputValue("person.first", $properties['firstName']);
    // Last Name
    $checker->http->SetInputValue("person.last", $properties['lastName']);
    // E-mail Address
    $checker->http->SetInputValue("person.email", $properties['login']);
    // Zip/Postal Code
    $checker->http->SetInputValue("address.primary_zip", $properties['zip']);
    // Gender
    $checker->http->SetInputValue("person.gender", $properties['gender']);
    // Birthday
    $date = explode("-", $properties['birthday']);

    if (isset($date[2])) {
        // Month
        $checker->http->SetInputValue("person.birthMonth", preg_replace('/^0/i', '', $date[1]));
        // Day
        $checker->http->SetInputValue("person.birthDay", preg_replace('/^0/i', '', $date[2]));
        // Year
        $checker->http->SetInputValue("person.birthYear", $date[0]);
    }// if (isset($date[2]))
    // Password
    $checker->http->SetInputValue("person.password", $properties['password']);
    // Confirm Password
    $checker->http->SetInputValue("verifyPassword", $properties['password']);

    // Terms and Conditions
    $checker->http->SetInputValue("person.termsAndConditions", "Y");
    // other fields
    $checker->http->SetInputValue("registration", "true");

    $checker->http->PostForm();
    // failed registration
    if ($error = $checker->http->FindSingleNode("//div[@id = 'errorBox']")) {
        return ['success' => false, 'errorMessage' => $error];
    }
    // Registration is successful
    $checker->http->GetURL("https://www.e-miles.com/reviewAccount.do");

    if ($checker->http->FindSingleNode("//div[@id = 'dashboardText']/a[contains(@href, 'logout')]")) {
        return ['success' => true, 'errorMessage' => ''];
    }

    return ['success' => false, 'errorMessage' => 'Unknown error.'];
}
