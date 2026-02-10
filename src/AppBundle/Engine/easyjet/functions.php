<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerEasyjet extends TAccountChecker
{
    use PriceTools;
    use SeleniumCheckerHelper;

    protected const ABCK_CACHE_KEY = 'easyjet_abck';
    protected const BMSZ_CACHE_KEY = 'easyjet_bmsz';

    private $airCodes = [];
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.easyjet.com/en/");
        $this->http->GetURL("https://www.easyjet.com/EN/secure/MyEasyJet.mvc/AllBookings");

//        if (!$this->http->ParseForm("sign-in-form")) {
        if (!$this->http->FindPreg('/<form class="help-search/')) {
            return $this->checkErrors();
        }
        /*
        // prevent error 404
        $this->http->FormURL = 'https://www.easyjet.com/mylogin/en-GB/LogOnAsMember?ReturnUrl=%2FEN%2Fsecure%2FMyEasyJet.mvc%2FAllBookings';
        $this->http->SetInputValue("emailaddress", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("keepMeSignedIn", "true");
        */

//        $this->sendSensorData();

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.easyjet.com/api/account/v4/antiforgerytoken");
        $antiforger = $this->http->getCookieByName("DA-Antiforgery-C");

        if (!$antiforger) {
            return $this->checkErrors();
        }

        $abck = Cache::getInstance()->get(self::ABCK_CACHE_KEY);
        $bmsz = Cache::getInstance()->get(self::BMSZ_CACHE_KEY);
        $this->logger->debug("_abck from cache: {$abck}");
        $this->logger->debug("bm_sz from cache: {$bmsz}");

        if (!$abck || !$bmsz || $this->attempt > 0) {
            $this->getSensorDataFromSelenium();
            $abck = Cache::getInstance()->get(self::ABCK_CACHE_KEY);
            $bmsz = Cache::getInstance()->get(self::BMSZ_CACHE_KEY);
        }

        $this->http->setCookie('_abck', $abck, ".easyjet.com");
        $this->http->setCookie('bm_sz', $bmsz, ".easyjet.com");

        $data = [
            "emailAddress"   => $this->AccountFields['Login'],
            "password"       => $this->AccountFields['Pass'],
            "keepMeSignedIn" => true,
            "cultureCode"    => "en-GB",
            "languageCode"   => "en",
        ];
        $headers = [
            "Accept"           => "application/json, text/plain, */*",
            "Accept-Language"  => "en-US,en;q=0.5",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/json",
            "X-DA-Antiforgery" => $antiforger,
            "Referer"          => "https://www.easyjet.com/en/?accntmdl=2",
        ];
        $this->http->PostURL("https://www.easyjet.com/api/account/v4/Authenticate/Member", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] == 403 && $this->http->FindPreg("/Access Denied/")) {
            $this->DebugInfo = "need to upd sensor_data";

            throw new CheckRetryNeededException(2, 0);
        }

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(1);

        /*
        if (!$this->http->PostForm() && $this->http->Response['code'] != 301) {
            return $this->checkErrors();
        }
        */

        $this->http->RetryCount = 2;
        $this->http->setMaxRedirects(5);

        if ($this->http->ParseForm("hiddenform")) {
            $this->http->PostForm();
        }

        if ($this->http->getCookieByName("eJ2Session", ".easyjet.com", "/", true)) {
            return true;
        }
        // Your details are invalid.
        if ($message =
            $this->http->FindSingleNode("//p[contains(@data-bind, 'FailureMessage')]")
            ?? $this->http->FindPreg("/\"FailureMessage\":\"([^\"]+)/")
            ?? $this->http->JsonLog()->errorDescription
            ?? null
        ) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Invalid Email Address or Password.'
                || $message == 'Your member account has expired.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Your member account has been locked.') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if (strstr($this->http->currentUrl(), 'FailureReason=')) {
            parse_str(parse_url($this->http->currentUrl(), PHP_URL_QUERY), $output);
            $message = $output['FailureReason'];
            $this->logger->error("[Error]: {$message}");

            if ($message == 'MemberDetailsInvalid') {
                throw new CheckException("Failed to sign in, please check your details and try again", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'MemberAccountLocked') {
                throw new CheckException("Failed to sign in, please reset your password by using the “Forgotten your details” link", ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // AccountID: 6322823, may be "(&#$)" symbols issue
        if (
            $this->http->Response['code'] == 301
            && $this->http->currentUrl() == 'http://www.easyjet.com/managebookings/error.htm'
        ) {
            throw new CheckException("Failed to sign in, please check your details and try again", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // for itineraries
//        $this->itinerariesURL = $this->http->FindPreg("/var allBookingsAjaxUrl = \'([^\']+)/");
//        $this->logger->debug("[Itineraries URL]: {$this->itinerariesURL}");

//        $this->http->GetURL("https://www.easyjet.com/en/secure/UpdateAccount.mvc/EditAccount");
        $this->http->GetURL("https://www.easyjet.com/api/account/v4/getmemberdetails");
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName("{$response->firstName} {$response->lastName}"));

        if (isset($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $headers = [
            'Accept'           => 'application/json, text/plain, */*',
            'ADRUM'            => 'isAjax:true',
            'X-DA-Antiforgery' => $this->http->getCookieByName('DA-Antiforgery-C'),
        ];
        $page = 1;

        do {
            $this->logger->debug("[Page: $page]");

            $this->http->GetURL("https://www.easyjet.com/api/booking/v5/bookings?Sort=1&From=$page&Size=10");
            $response = $this->http->JsonLog();

            if ($page == 1) {
                $this->logger->debug("Total " . count($response->bookings) . " itineraries were found");

                if ($this->http->FindPreg("/\{\"totals\":\{\"bookings\":0\},\"bookings\":\[\],\"success\":true,\"/")) {
                    return $this->noItinerariesArr();
                }
            } elseif ($this->http->FindPreg("/\{\"totals\":\{\"bookings\":[1-9]+\},\"bookings\":\[\],\"success\":true,\"/")) {
                return [];
            }
            $page++;

            foreach ($response->bookings as $i => $itinerary) {
                $this->logger->debug("Open trip #{$i} -> {$itinerary->bookingReference}");
                $this->http->GetURL("https://www.easyjet.com/api/booking/v5/booking/$itinerary->bookingReference", $headers);
                $booking = $this->http->JsonLog(null, 2);

                if (isset($booking->flights[0]->departureTime)) {
                    if (!$this->ParsePastIts) {
                        $isPast = true;

                        foreach ($booking->flights as $flightDepart) {
                            if (strtotime($flightDepart->departureTime) >= time()) {
                                $isPast = false;
                            }
                        }

                        if ($isPast) {
                            $this->logger->debug("Skip past itinerary: $itinerary->bookingReference");

                            continue;
                        }
                    }
                    $this->parseItinerary($booking);
                }
            }
        } while ($page < 10);

        return [];
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            return false;
        }
        $this->http->NormalizeURL($sensorDataUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];

        $sensorData = [
            '2;3421506;3490886;15,0,0,1,1,0;IuC7%z9:>n,Kv3st>_YRweNxO (R #F7LU&2(0>kADo1XQThK?C9m5e0u$f0f3T,G4fdM!N%N~U7Fi>Q-iUy:b>sl=:O=i6>B|^RfM<]2n7[qF<0 U`|KeQd0_U*oDK)(lQWO|9{G~G}Hdvp:%VY-gX2#qgJ*h=W^<%<F#CwP3_9]];Ck_dP ML-8D&WP/aX%zh%.cwd3&gOm9Yz_Hkx_=,V!xx]U4k9TD(I^<kY( )PtKa,S]0@ L?iiRsg`0!LaTRs8gD-}hF7mVq*|$OKYAB2-CQGV1)Eyq`,T6h`FJ/&>8m8) X radYvl*5<#i:syY4]C/,zoa^D!C{))M!&z_r:=RekjM-6lm:gcD;$8z%ga3bbuY.xv.0Nbx3<05?PFU:neGD<PBO<9]:KR<~mp&7gT0=_ac0|`JxLK<REi9eTS l|=PZYb ;JpGRxx{iIcoXE</B!5-!geB>G]R4JAhvWzDS}/9 k8B(uml4&Q,tkH`rfUqXs$Gb<*8Q!,viWIdVjt!kIdImgw)Gw<ZK1Iw7tFvHI O37+zk.9ndEt(|^W`K}90.E^>g)oQ*_q2($T#,n 8_~~a#8KM:l68@^z_]v;*X4Pyr~]*}&pRw7c$_a+t+(015:!X<UjF}a2;N>|J&z4YSTxEo/UZ;5mw2Vsk>1O[B*R (@o^_fp?(H3Q3Pxu*cNht lZ8,&iR=m~Th|6>L{C[90`Gd!8w7o9M#66,#{uJJrYr4V_v(bveC!SAfCEWH3QmN)|lua`SE#X|B<f4|g#t]kP$S<Mo]yF{zWD9EDxI0w1+C;9Qq},qIK!yB.8b`V J#hz_wz7(vjS}J;2[ ^89#RZU,m1H15Y(7CkYz#I@n#}Bmm9i)tRI49$}FoFup+P@>4>>`fv!/*tA*#.4)h``LFEI(ZF=cuAvr^?F`u}3np|J7DI/HxHu0J~14V;w?&bjCa1:0s<{l^]Lgn!Q~-mdy:Um_Y.e&+dlm!6GOnm_ntpYDSrS72#DnUFe]Z@sA)=z$6`v<<dPgr#gUn3ybW`!}9>0_XB!KcXINY+@#O$+ev hZ*N4uge~Yo<~p4D(EhbY}#HwQx=iI[uDW IF1HBlTLQN[2I<ApeS%@h2lF&F~:`UK~VD:NS/xe}Lboe27QST}?r71hRnWh8<C`3@3W]:Z2?lXD-4mGHEv;tDBy`6lf<7E) (}F].^c^zB]bM@$2<R#r92zqP/T15}nc<k0K0F1I3Jub}aWRd@nTB/lnSB^ES]B3rFj4q:&N&Sw.>/]-=s31wl_bMDR:^v[=[sIFM$e3yD}*:7$G;1slMGDP4-m?c wZv4a$!ZDY]4i{@?-6ZN.3!gtf9K&,R%qIy9}AM,}HAvWs4(!PrSCE:H;RHSd{:llc)A-V]87{{YpT}Q~FeeAPB^5uq#4W|SY-+B0?rHZK=pZ#K`k<bbLFLen+$ueW-23-d/)`bT-XH1/Ta&6|A1-CQil9>KCH Yo|EipYbIYU^H gDO`9)zn35fi3Gk<Y@&/;qTOAC@VRZFE|Pe*jCSJpMS^3<hVbQ<D]=nyh}%3< DByj!<!l{l:R-#u)]0p?73sZ:=CXI|V8/0njVF&v8qhxWzI_.EKD5W~dGZ[z C]om~_W{61)II}8x~zCt1C)~M{lH]g.Do&!ET?6k+/w:V+T1]:h<Svrf=ebPpk3W*4cnU%zXtNj.ms9>WXZk,OL@!^lOy$&SgJPp*uR3Hq^*Pa=L&]=zHomwX!%1W_)I4R&8Wec2{%-M}_?;lc;.Qu/6 NSW#=z,z?,vvg4j4_`>]V&+wTC)N!7SBs~.`rU4yE3O|(nfH[@i71n;R$B`JMPa1FNk1P^t+#_iE~v2ps0vxGZX8* <x26b)`yM|!oivk;oo*5B3(dYsnukAWbW.: ;9iWu5F9-VYfS(S9i`ESG8|oy{qZz/`}}CYK~y`|tkB1GL%fm*BC}`!;q)ed]L>2&#;oSJXR6!bZ $E1w:a|ihWl^;kP(v7}:V|D?em ,#kTl]Le[m%TxBo_<vui2Y}7l!-%IFw.g<J1SO*_VwC&;IN4>B}qseOHJ2C6duuoWhVCi8$o;:m=_Iw{`oM:})]`q`H$::}G1J`h53lmF!`(`QrU+SMZ+DM0WJofvId@m~6Z0.w^k.G qdYwo^:L&O|ch$oU!IaP+2`kZ!*RN@xHX?NC~bu;=w,+mm_e3bbyHdDlx8y.VgTt:1ckx~,2-WTk &|81@aGHHXVN]8?T&y;o327%QBx{`7LiN1*t2t0L/:p!24EuS Pp=PO6v[8m>f`rG ~{IVc@#M%rJV_7]m(KYQ1WhP%r?Tu(WHE:m9l-j3m+OWhDz56m>%k7H=}p7`u)pQSh.U6jDlZvFF]J%4O.a9#kI}SC!eqenObzVsW6RQ1R(B.<}B_BkfU]*:(0_HRo_WA.X0qF;w_5Cm6%w4S;|ufg`yVY>+ASQzp ^!zYT4#U.WFh^2}9lpt#~!yy`HS:,1RqR,za]76rQrA38_2!fjXRfbl<{i:bhH!R@|kXkF9!v<OXa-ehGSUjp)+>V*Nlpmc$yV[AS=H*xDt&(d/&|4<4Y&(;2$khOL$QT:<,;81p~} $9uPM;_o1}ir%$+]<YsLsrTfjyr%',
            // 1
            '2;3424580;3686723;13,0,0,1,1,0;R&k{<ia0C4KBURZF<]3(.PyZ:6{{]a>!>hdp=nZASduh.4/&}Fx.1XzqEO;;)$tX!^]h5ldNof$-OSiJR&efuFvFkSDW/#8)K$>M{VOWjo.I`u-,{[3S]7sbSbRkSeBO$gtzd==G`h/#0~J9Cc(+2g$k<JA]`i]]B&8NAsz.FX)Dr/KMkA~EhK$!rva1<f?0R0OF6Cx#&4SPg}OXeWJf!zLaJyxBHkgrSt:UN@|7X~MStmECfNOgE$nyyIR~~KJgD>g4(q4@wG#E1N-HVTQl?6 9?k%Li+/>-aw-#u|}6XCHq.[znP<f:<+NL%Bj&bi&kIdwzvFg?sV3!F/VuW5X,#5DU0N%HzyMHZ-Ssw:^Evn@;hTpASd@sn%ER5W/_1Dpp~Y8>-GJ=g<4pUo<cq}kNURdd}I/V0]%.1vbYHi4~WXG? nM#0J3v|7gZ-E-Jwy/:FG H`9a_LL71X6Q#5bL6Oe7jFX)?5Q=FY?K_xN.,mTv0h`;3Io_Ipzg3&WJywI)7fPOf+H%?SVLeLL+ CdiPz6vB>(lVS|qy!j@h/zmjgso,~&ec|X]Ry:%[Z?4x}[Cf7:9W0yr||9.LNRF7m0H;h9*XbE6)~.q9euoD~O#&z@Hbg|lj9|u%b,M$gOVXWI2iDET6fiYm 9Mtp9+$[CM[CrmLWHnQg0b|Ue~WCE>]A6{yJ>4E g{6:FF2=q4)gVSiKUFa@mrDRH4frqxK:$4J:p~D&%@A+H[fR?!H47uetkG&Du9L1zIgO&iQV`vR!x&qz2b>#<``}o3`xZyodVH?9;c$M^&7YLd,k,9jNismAt[dqM`{pcq#bFXl(<n~>,~XJ~DJC`yVwe-l={0Z_K{VKI`psq(Gly7#8_-@8]tV|lU(jH^+0,`1s|(@wQlp,1KZJ3_r{FkJZ0S>g`$HMR9x#yLV.T-T7Cuipe8FFwE8nbH3(d*co*bziX~6Q[2U%G{xhLYk|X&uOR-n4e,^|7xfw*Iq^#MU*Lw(>*i)@qPI%*&#YQ9g+4,j,<z82QG(d3`z;+z5U-qgXdRIBDz`|C,{@a;jS7%S{0Wl&!o4M*UOUOt mvg8g-1tMa;nfK<*wO5Raa`-[;.6$91CCF+~:poiCY@&xSK1:{U!NMPyD@%23FIS<G LtCGQwuUpnX]JO/-Ln|{O.#pWCKGac{rAa}}-<GwfYRfP_88j::O,b^YlyF?fLJ-)U1QZ7WWAF:RH^GDsT]oS%%2PT&0RAC%-cCkpU)5 !Y_<F:FqPw&kOSd}-QrN[z$,gTt,|IP-?2)~2XRuZj1xdplBS%K(5cj{nPp6|eq[v-YRphtZJi)tjz2XpP|Hy>EeMDj/zhkzBJop<<]>/Xwuf*9l,n<r<t{sU-^tVy|%:d28^fV.e5S;o 2]2sA(K9Pfq_1h8/>,?m;Mk7E347P>4[u]*)M;]Qi.!f <>2Ve@Z7R]jeTQ1bej>gE#tF,jQJ`LU&j$s.T&SyS(<WglJ&05k5*fH+)gMxOoC5_ww;#d,n)mN8<S<)/DriAInWN293}7i}kF#vO35G~3(NU~7.@CsVQq53N9/_;^zLH#)q/y0uW7kDDoV<e!a@6s#nxC$H&7d)^c|UbQ|Fx[@o)JuQW~aEF/3qa=;ri$DA:[uJ5zYU<(a?c,xsKbI1H9HGVOSH,M[gN)gK~|VdE=!a{HUG.RB~jIUEccGZM1Y)Q+hrUp9`!W6 Lvv-x4.}`vyE<D]CpXoC1S.HEnYK?%ha#xPuU*6DSncp:=UllN~5EyN<,+Lmlxvd]7IT:q!m+8h$ENhirJ-MS3ngMll~z}yNs7L_>**4Oj0Js4XVc=%EmB14&0J[crVbH0AtV2P](+g$Ch9@G*{3QFs^7]jq:muKgG`K16YT>G&aPLqqeT%`+VdPpy,/W):4/AAIIEa4]x#fD Ys:Y&.o (`lH{y>67ZWM!>oD:l>Q;af2TlD0JU)*vKIC9oC1pC76.TXAB5iF[_ryZwq`V:2O]>O3t#i=Nv _9VGy31a$=L.~d7lW~L3]]-{GOQOBp:v_9!}Q!/sD-:Rd>>>dNHvxD^@*(q0O aW{m?Bq0?s0C kI2/fw-r0/=sPAbRfa#qJTuMn`C-Y5rtRBuGC{[ikIQUzd6d1@Mfcd5~^8%>h!Z_a1>my_f@yc#<Btc8F[}g~%sM H5aq^0(/[bkYoRS~@=<`)~968,{Hi^$[9$/&aSTS&7i<%vdCn}_%&2E^bAKI1%=6 oMkh-%^>9jJ:2<iwfR:7oWIl!~7AE3~]|Jqr%9KDe[H bb`@aU;M1B=={nDu}!@K3+<Nnv;EYKO%[kug-}dnj|ksDs0kr0xTXKks>Mv%B5:}b~2_C@/=EKNXC>k6?3qT^6:7asUc%~+aYm0xDBEYZju~y-c?awmE/~3=i<g MJ',
            // 2
            '2;3290166;4471089;12,0,0,0,1,0;q0r_!EM-=^-W/W5R,=^ERpB2h&6Nt-?as(ka3`QNt#h!i1sH!*TRVnTKDJZ#3l(IZnue8:LFo(*T8REgcWv(7[,0?BP,`x2~Q)%&kGB:ti7oV=4^=Y/3?pRSN}&ek#@Wp2)6|bSj;eEtuVY$o9P46[GL]k-i7x;~8enlT:MU-QH(0dY:+[4R^@{8HP^#6%5bp(3F4s$CoNCv q|W;yZo%oXj&Lt`@*U6s%twDIf|Z*1zwelYa&c8N:&=Z.^R(&.l$:T*va`%ZvbVT[aJI1s0eWRr*b8n90!gXCd*{zAkV9;,;7BCC!tEb]Rc-]lR:F+uPr*5|!S%nMh=8- u4rf1`fu4X;*&`p[b0}@q)tEwlptz0,KUraR+WRiBnP6D0&w p^#RbrJp&HE@>V{u5(1d3V2NqQ3K@@>H3{?;@`4ayLFIKc5AZMS0~75uPJ8HAQKPEK%gl&rI^^{,]Lfmgkmk@]nO-0$;6MYjQ>zQESE6tb~rZh>Vl*wFu~8(nucy,JHL4pO83S8+Lo<_>U(L)|9,q;bM6ELam7=s;43Vb1|z03k.x6i/@]y[o_I88K^|T?`)&7P?i2))Ga0[5-~K R`&*Ozzk},JgFW})?G3[;56]nOze/$];>vnwIx}i/Jo!Z$MY~s(S&*+NxU-zQCxcxMWZ)uJ5FFxDx9o7MGYk,,p,@l6%v<a;<x(+o9 yzDtP!B!?sQE#=_T=d_3&onU5k3Ma7(@7t7z@}T-L,)G.cy6|E6|}dr/c&Ls}m^h}O{Z%jW2Y4@X@?QjF|rf^bYGFo*Ycg$4D~J7tGi?N.CbPGm|mK@6E2F,0qRw8.8%5VtaS?rcO*0jSLo<j+/Ztoqn%eBh442YzQ74osZa5#3RSa|C7}04/K[N_A5qBBDX.x=B&{H-fapXv#%DO=6&`r%/4&xy-/!2eC{)~5TU<2[/GTa!I]ch!LnR|Wl>Oc~9U<8v}2t5S-(f+EuT8NNj8Fhu9sHbvi;OrPY|FkeP]e-A#V<syq7qg/Jh>y$WX380N=7FAEZHQa=NL+na=euq%,[aJVaA+>s8#5=;yFA(yhyLpk$fQlX}*~,s{;` @Q&nlBOV>-wv9)@U|J$6jZ^]ZL+m%&zmH+K-?sO*oLv4?r4/>./NGh_bu8<x[Ot4F_aLDiGOUGU}^;6zUFk9~=H2Fq(tO5e)c8/NAA%NdUd|(w/3zkdiG5$%Sxz^7P<|fU =eb)ko7(9d=X5{VCgh[BaJrb4AI,T{ /jjC}I]g~m2ZW1#vODk3bdKiyR=,bcd`8[ny*.R$,k/j gRiRbqZiD,dI2.Q3MUP:R_2X9o^/yD|cc@n5R~i?lf3E3.U;b~aWl#_ztIIoX4ls;6sP?k;!fquw`eM*Okv@DVl:4ql@ZGACpdG@pU6*)_muqzgK}9jJ:%`*.%LE84Ptd5ZCd$DFq^JUrLC$So{ 9oa_YOO(6jDuf_zHqD8GQda)~22!D0iQz+{9-hUHH9|Aw#2eF8,=8i}9*V+m7IfnCp`Y;olHVA9c;FU-tzn.1m8]z5F^x673vGD9/3CDK?< HephFKE]r6Q]i7b_C+&#Sngax &*G7Cxjq,|]bu3Po2m]$V_;0Kj/RK0=Qk}&ju?ip-! ),_]p0=`2BG<6P{HpiHH]v,oalYCl@#r.Jw8t;v;ew8$*JuaLmf*3ZuD9DD3a.~s;R{U%L2`AGpi_5X_Ijg+Gp1723pc=yK/EF2U6mG#k#:G#:_:GXp;P$Bw~E;9kI(q./z;/gX2r1eXbHs(tCP2I#D}5[Mh9 x~YU N#D?t(Fm-o8eOHu-wx=Uz1jkdW8AJ1O7} ]F+}Vq;A)ku1SoRti.j0Yu|aN_pP aNN?FquH-2D;y6:s;q](=q{[`ziDn-cC<~~p}HV)p)L75!{GWBwaK2dAA`[Il(kSRGL4?77lY}E0ht(HjU2-hf8l%TO6h-pWTNjRb-Emm2zHL=$ZjcR>0g$VV4nGpos2(tbnzx&hb VcWvI8giLW>B}`&QUb.OPBUR_s!-8}/R/-(Z&#cTT0sb!x@wv)[85 =FSc$-N0+zPK_:l_8M1MAL+&M ?F}Z^jstL8EHF5;zcusW((p2 54@z-U;FRA+2)Kb04k}?.4zh-7vH]JWTnu(jnO4Uc^27;j Um?T$/$sW|5)ceWv{>E`YbG~=jZ?QXzFW^-)U]p`O}={gv}@=bk,&6zp>[CG(`2k6.:]Pw2<{ea,d&~BR2Pfdr<BM:jf-8Dr;(VuT=o*{@hjXhwJN)?aj!wt8yyw1M7:b%Vye#G>;nLH=^_DO{Jr*H+8Mi]?}-yE%EX.m&Z[d&<+f:G$DpAq&>QT)h`u)3h x7u;jthf [5GAs+M{h*d$GRZ*zrY&,*DxM5|Y-_m)XBTW/M.R>pLj5BM/!3R-IL#1hgWNwbb+iSdeGfH|x@zQq3O0p;L3eX=N@2hN3{NxYPinY$lC/kWwQr-ni3K&qfhZIaOU/s@ZV@_tQ(yTI&uU HtgQxvH~cYhktntQB]121JhG$waI</qRj2uRkvkPhXiq$! kb6`^V#LDngOV4B}i1KXT*ZJ2P=fC%]@XxO$(*]*E[XFGetJm6nDI.)PB(7UUP%5!T64@M%VE03a122{B@vYamsPFW:0:ne1x{X@Pb.cgLn>8}hXe1o%',
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We appear to be having some difficulties loading the page you requested.
        if (
            $this->http->FindSingleNode('
                //p[contains(text(), "We appear to be having some difficulties loading the page you requested.")] | //p[contains(text(), "We\'re experiencing some difficulties loading the page you\'ve requested.")]
            ')
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // We are having some technical difficulties at the moment.
        if ($message = $this->http->FindSingleNode("//h5[contains(text(), 'We are having some technical difficulties at the moment.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, but something has gone wrong.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Sorry, but something has gone wrong.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseItinerary($data)
    {
        $this->currentItin++;
        $this->logger->info("[$this->currentItin] Parse itinerary #$data->bookingReference", ['Header' => 3]);
        $f = $this->itinerariesMaster->add()->flight();
        $f->general()->confirmation($data->bookingReference, 'Booking reference');
        $f->general()->date2($data->bookingCreateDate);

        foreach ($data->flights as $item) {
            $s = $f->addSegment();
            $s->airline()->name($item->carrierCode);
            $s->airline()->number($item->flightNumberWithoutCarrierCode);

            $s->departure()->date2($item->departureTime);
            $s->departure()->code($item->departureAirportCode);

            $s->arrival()->date2($item->arrivalTime);
            $s->arrival()->code($item->arrivalAirportCode);

            foreach ($item->passengers as $pass) {
                $f->general()->traveller("$pass->firstName $pass->lastName");
            }
        }

        foreach ($data->payments as $item) {
            if ($item->paymentAmount > 0) {
                $f->price()->total($item->paymentAmount);
            }
            $f->price()->currency($item->currencyCode);
        }
        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function getAirCodes()
    {
        $this->logger->notice(__METHOD__);
        $cache = Cache::getInstance()->get('easyjet_aircodes');

        if ($cache !== false && count($cache) > 1) {
            return $cache;
        }

        $browser = clone $this->http;
        $browser->TimeLimit = 30;
        $browser->GetURL("http://www.easyjet.com/en/flight-tracker");
        $matches = $this->http->FindPregAll('/"Iata":"(?<code>[^\"]+)","Name":"(?<city>[^\"]+)"/iums', $browser->Response['body'], PREG_SET_ORDER, true/*, false*/); // todo: uncomment last parameter after prod update
        $this->logger->debug("Total codes: " . count($matches));
        $codes = [];

        foreach ($matches as $match) {
            $code = Html::cleanXMLValue($match['code']);
            $city = Html::cleanXMLValue($match['city']);

            if ($city != "" && $code != "") {
                $codes[$city] = $code;
            }
        }// for ($i = 0; $i < $countlinks; $i++)
        Cache::getInstance()->set('easyjet_aircodes', $codes, 86400);

        return $codes;
    }

    private function hardcodedAirCode($name): ?string
    {
        $this->logger->notice(__METHOD__);
        $airCodes = [
            "Bastia (Corsica)"                       => "BIA",
            "Basel-Mulhouse-Freiburg"                => "EAP",
            "Brest (Brittany)"                       => "BES",
            "Cologne/Bonn"                           => "CGN",
            "Crete (Heraklion)"                      => "HER",
            "Cyprus (Larnaca)"                       => "LCA",
            "Majorca (Palma)"                        => "PMI",
            "Santorini (Thira)"                      => "JTR",
            "Sofia Intl"                             => "SOF",
            "Zante (Zakynthos)"                      => "ZTH",
            "Murcia International Airport"           => "RMU",
            "Pau Pyrénées Airport"                   => "PUF",
            "Enfidha-Hammamet International Airport" => "NBE",
            "Calvi Saint-Catherine"                  => "CLY",
            "Berlin Schoenefeld"                     => "SFX",
            "Milano Bergamo"                         => "BGY",
        ];

        return $airCodes[$name] ?? null;
    }

    private function findAirCode($name): ?string
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("findAirCode -> " . $name);
        $code = null;

        if (empty($name)) {
            return $code;
        }
        $code = $this->hardcodedAirCode($name);

        if (!empty($code)) {
            return $code;
        }

        if (!isset($this->airCodes[$name])) {
            $code = $this->db->getAirportBy(['AirName' => $name]);
            $this->logger->debug("Lookup by name {$name}");

            if ($code === false) {
                $this->logger->debug("Lookup by partial name {$name}");
                $code = $this->db->getAirportBy(['AirName' => $name], true);
            }// if ($code === false)
            $city = $this->http->FindPreg("/([^\(]+)/", false, $name);
            $airName = $this->http->FindPreg("/\(([^\)]+)/", false, $name);
            $this->logger->debug("city: {$city} / airName: {$airName}");

            if (empty($code) && !empty($city)) {
                $this->logger->debug("Lookup by city: {$city}");
                $code = $this->db->getAirportBy(['AirName' => $city], true);
            }// if (empty($code) && !empty($city))

            if ($code === false) {
                $this->logger->debug("Lookup by CityName: {$name}");
                $code = $this->db->getAirportBy(['CityName' => $name]);
            }// if ($code === false)

            if (empty($code) && !empty($city) && !empty($airName)) {
                $this->logger->debug("Lookup by CountryName: {$city}, Lookup by city: {$airName}");
                $code = $this->db->getAirportBy(['CityName' => $airName, 'CountryName' => $city], true);
            }// if (empty($code) && !empty($city) && !empty($airName))

            if (empty($code) && !empty($city)) {
                $this->logger->debug("Lookup by CityName v.2: {$city}");
                $code = $this->db->getAirportBy(['CityName' => $city]);
            }// if ($code === false)

            if (empty($code) && !empty($city) && !empty($airName)) {
                $this->logger->debug("Lookup by CityName and AirName: '{$city}' and '{$city} {$airName}'");
                $code = $this->db->getAirportBy(['CityName' => $city, 'AirName' => $city . " " . $airName], true);
            }// if (empty($code) && !empty($city) && !empty($airName))
            // Helsinki Vantaa
            $parts = explode(' ', trim($name));

            if (empty($code) && !empty($city) && empty($airName) && $city == $name && count($parts) == 2) {
                [$city, $airName] = $parts;
                $this->logger->debug("Lookup by CityName and AirName v.2: '{$city}' and '{$airName}'");
                $code = $this->db->getAirportBy(['CityName' => $city, 'AirName' => $airName], true);
            }

            if (empty($code) && !empty($airName)) {
                $this->logger->debug("Lookup by AirName only: '{$airName}'");
                $code = $this->db->getAirportBy(['AirName' => $airName], true);
            }// if (empty($code) && !empty($city) && !empty($airName))
        }// if (!isset($this->airCodes[$name]))

        if (!empty($code)) {
            $this->logger->debug("Lookup (AirCode: {$code['AirCode']})");
            $this->airCodes[$name] = $code['AirCode'];
        }

        if (isset($this->airCodes[$name])) {
            $this->logger->debug("Lookup by name {$name} (AirCode: {$this->airCodes[$name]})");

            return $this->airCodes[$name];
        }// if (isset($this->airCodes[$name]))

        return $name;
    }

    private function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

//            if ($this->attempt > 0) {
//                $selenium->useFirefox();
//            } else {
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            }

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL("https://www.easyjet.com/EN/secure/MyEasyJet.mvc/AllBookings");

            $selenium->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Access Denied')]"), 5);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] === 'bm_sz') {
                    Cache::getInstance()->set(self::BMSZ_CACHE_KEY, $cookie['value'], 60 * 60);
                } elseif ($cookie['name'] === '_abck') {
                    Cache::getInstance()->set(self::ABCK_CACHE_KEY, $cookie['value'], 60 * 60);
                }

//                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
