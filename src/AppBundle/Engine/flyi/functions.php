<?php

//Alaska Airlines       MyAlaskaAir Mileage Plan        http://www.alaskaair.com/mileageplan/MPtoc.asp  veresch or 18951295     banana  Mileage Plan # or User ID       5 to 50 Characters      Total Miles                     No      https://www.alaskaair.com/mileageplan/ssl/activity/detail.asp
//function CheckBalanceFlyi( $sLogin, $sPassword, &$nBalance, &$nErrorCode, &$sErrorMessage, $IsRedirect, &$arProperties)
//{
//  $http=new TCurlBrowser();
//
//  $http->redirect = false;
//  $arg = array(
//        "URL"=>"http://www.flyi.com/iclub/default.aspx",
//        "RequestMethod"=>"GET"
//  );
//  $http->open( $arg );
//
//  $foo = implode( "; ", $http->cookies );
//  unset( $http->cookies );
//  $http->cookies[] = $foo;
//
//  $arg = array(
//        "URL"=>"https://www.flyi.com/iclub/default.aspx",
//        "RequestMethod"=>"GET"
//  );
//  $http->open( $arg );
//
//  $ff = get_form_field( $http->body, "frmFlyi" );
//
//  $foo = implode( "; ", $http->cookies );
//  unset( $http->cookies );
//  $foo.="; s_cc=true; s_sq=flyicom=%26pid%3Diclub%2520default.aspx%26pidt%3D1%26oid%3Dhttps%253A//www.flyi.com/images/iclub/entry_login_btn.gif%26ot%3DIMAGE%26oi%3D116";
//  $http->cookies[] = $foo;
//
//  $arg = array(
//    "URL"=>"http://www.flyi.com/iclub/default.aspx",
//    "RequestMethod"=>"POST",
//    "PostValues"=>array(
//			"__VIEWSTATE"=>$ff[ 0 ][ "__VIEWSTATE" ],
//			"IClubLogin1:txtUserName"=>$sLogin,
//			"IClubLogin1:txtPassword"=>$sPassword,
//			"IClubLogin1:btnLogin.x"=>46,
//			"IClubLogin1:btnLogin.y"=>7
//    )
//  );
//
//  if (isset($IsRedirect) and $IsRedirect)
// 	 return $arg;
//
//  $http->open( $arg );
//
//    $arg = array(
//    "URL"=>"https://www.flyi.com/skylights/cgi-bin/skylights.cgi",
//    "RequestMethod"=>"POST",
//    "PostValues"=>array(
//			"email_addr"=>$sLogin,
//			"password"=>$sPassword,
//			"pwd_authen"=>$sPassword,
//			"password_ver"=>$sPassword,
//			"sid"=>"",
//			"module"=>"MP",
//			"mode"=>"",
//			"language"=>"EN",
//			"log_in"=>"1",
//			"page"=>"FLYI_VALIDATE_LOGIN",
//			"postpage"=>"https://www.flyi.com/skylights_login.aspx"
//    )
//  );
//  $http->open( $arg );
//
//    $ff = get_form_field( $http->body, "mp_status" );
//
//    $arg = array(
//    "URL"=>"https://www.flyi.com/skylights_login.aspx",
//    "RequestMethod"=>"POST",
//    "PostValues"=>array(
//			"status"=>"USER_EXISTS",
//			"sid"=>$ff[ 0 ][ "sid" ]
//    )
//  );
//
//  $http->open( $arg );
//
//
//    $arg = array(
//    "URL"=>"https://www.flyi.com/iclub/online/default.aspx",
//    "RequestMethod"=>"GET",
//  );
//  $http->open( $arg );
//
//  if( preg_match("/Please\s+make\s+sure\s+you\s+entered\s+the\s+right\s+password\s+or\s+be\s+sure\s+that\s+your\s+account\s+is\s+activated./ims", $http->body, $match) ) {
//    $nErrorCode = ACCOUNT_INVALID_PASSWORD;
//    $sErrorMessage = "An incorrect username or password.";
//    return;
//  }
//
//  if(preg_match("/header_current.*(\d+[\.\,]?\d*)</ims", $http->body, $match) ) {
//    //var_dump( $match );
//    $nBalance = $match[1];
//    $nErrorCode = ACCOUNT_CHECKED;
//    return;
//  }
//
//
//}
