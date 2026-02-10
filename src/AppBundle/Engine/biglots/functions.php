<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;

class TAccountCheckerBiglots extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // A valid email address is required
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('A valid email address is required.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.biglots.com/account/login.jsp?toURL=%2faccount%2fmyAccount.jsp");

        if (!$this->http->ParseForm("signin-form")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("/atg/userprofiling/ProfileFormHandler.login", "Sign In");
//        $this->sendStaticSensorData();
        $this->getSensorDataFromSelenium();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // BigLots.com is currently unavailable, but will be back shortly. Please check back soon!
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'BigLots.com is currently unavailable, but will be back shortly. Please check back soon!')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /**
         * We’re updating our site with new functionality to make it even easier for you to find amazing deals.
         * So, hang tight and check back soon. It’ll be awesome.
         */
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "We’re updating our site with new functionality to make it even easier for you to find amazing deals.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            // Your session expired due to inactivity.
            if ($this->http->FindSingleNode("//body[contains(text(), 'Your session expired due to inactivity.')]")) {
                throw new CheckRetryNeededException();
            }

            return $this->checkErrors();
        }
        // We've recently made updates to our website that require you to reset your password. But don't worry, it'll only take a minute.
        if ($message = $this->http->FindSingleNode('//p[
                contains(text(), "We\'ve recently made updates to our website that require you to reset your password. But don\'t worry, it\'ll only take a minute.")
                or contains(text(), "We\'ve recently made updates to our website that require you to reset your password. But don\'t worry,it\'ll only take a minute.")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Access is allowed
        if (strstr($this->http->currentUrl(), 'https://www.biglots.com/account/myAccount.jsp') && $this->loginSuccessful()) {
            return true;
        }

        // Email address and password combination are not valid.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Email address and password combination are not valid.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // By choosing to sign up for Buzz Club Rewards® you agree to the Membership Agreement.
        if ($this->http->FindPreg("/By choosing to sign up for Buzz Club Rewards|By choosing to sign up for BIG Rewards you agree to the /") && $this->http->ParseForm("becomeAMemberForm")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 3727363
        if ($this->AccountFields['Login'] == 'mshuflin@gmail.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 5390638, regaltlc@yahoo.comr
        /*
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Email Address is required.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        */
        if ($this->http->FindPreg("/^[a-z]+\@[a-z]+\.comr$/ims", false, $this->AccountFields['Login'])) {
            throw new CheckException('A valid email address is required.', ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName(($response->fn ?? null) . ' ' . ($response->ln ?? null)));
        // MY BIG! REWARDS NUMBER
        $this->SetProperty("CardNumber", $response->rid ?? null);

        // SubAccounts - My BIG Rewards & Offers

        $this->http->GetURL("https://www.biglots.com/account/json/myRewardsOffers.jsp");
        $offers = $this->http->JsonLog(null, 3, false, 'buzzclubCouponExpiresLabel');
        $this->logger->debug("Total {$offers->rewardsOfferData->offerCount} coupons were found");
        $offersAndRewards = (object) array_merge((array) $offers->rewardsOfferData->rewardsJson, (array) $offers->rewardsOfferData->offersJson);

        foreach ($offersAndRewards as $offer) {
            $displayName = $offer->primaryDescription;

            if ($displayName === "") {
                $displayName = $offer->altText;
            }

            $exp = $this->http->FindPreg("/(?:thru|Expires\s*:)\s*([^<]+)/ims", false, $offer->buzzclubCouponExpiresLabel, true);
            $exp = strtotime($exp);
            $barCode = $offer->couponUPC;

            if (isset($displayName) && $exp) {
                $this->AddSubAccount([
                    'Code'           => 'bigLotsCoupons' . ($barCode ?? md5($displayName) . $exp),
                    'DisplayName'    => $displayName,
                    'Balance'        => null,
                    'ExpirationDate' => $exp,
                    'BarCode'        => $barCode ?? '',
                    "BarCodeType"    => BAR_CODE_UPC_A,
                ], true);
            }
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (!empty($this->Properties['SubAccounts']) && !empty($this->Properties['Name']) && is_null($this->Balance)) {
            $this->SetProperty("CombineSubAccounts", false);
            $this->SetBalanceNA();
        } elseif (!empty($this->Properties['Name']) && $offers->rewardsOfferData->offerCount === 0) {
            $this->SetBalanceNA();
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.biglots.com/sitewide/json/status.jsp?_=' . date("UB"), [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!empty($response->ls) && $response->ls == 'Hard login') {
            return true;
        }

        return false;
    }

    private function sendStaticSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return $this->checkErrors();
        }

        $this->http->NormalizeURL($sensorDataUrl);

        $sensorData = [
            // 0
            '2;4404034;4404786;8,0,0,0,2,0;1*z5I.[=yuSOvJE-yi?PDx% 7g, :bNlz7yBOQtrn0XoqC:*zz,jTDKXX_YWk_3]187h8R47&{ia=/`pxxX2cUH]p|Rv4c[8^(4_#fQpt}ewX>n3hYVKM:K[Je(M3*Wca{}W v^U<HWSB6b=>BI?=0K~8Miy5)%a=m@|J>5GSnhmUR#bt)(@LG>l.Jq-NIe#i,=;CN=UcTd^eg=+K`Hr21Kc,O!4JRq_-PtW53.;G`[NjlNDi|rFRB/Cz9+,`VnZdY&U]W_Irw1pgnXEo2w<Y<YzV)uqD_qXY <ndV-MY}$VV>U?pi-0=Jz WTn%9F3,^&Q@2>{l_Q?0<T5a7-[3Pl9A#|o1p+S~p{xMAW)#J{2`n!nuL4}zMfMvi4~OHd(~.aH00`U/ZG@~6XdH,FT^zKZum20bRL$0A!Vl!{8pZh7e8YX`Xt(1h;>[P8_pJf0?@eN2>d~o4uE=bdm5>?YoPumn<03~yN!7D7|>?o?vG2gKeABZC6Lg9jxPT36&}S3Nnn.S[7uN vW;UvBRSNn/k:0(7z3nlL5Ig:KhyeG,dRrr@$@ijCj3tVXNk^hH=3uBhcU6>#R-,{YS3o6IRX&+}pnkws0~5nx@dWR|C.[=AC.|]On:6wp+}n!!8>PRlZOiIXW&C+ri_UGL0w=m!3@$ze#~D4r^!luVn(.aKO?DDkXgI0lPYh-cvUx-DHaij9._CV`A~mVaW&Yk[Z(qi^k>?!ef.Ju-ACE$dxEsYEqP;[VmXX4Zt$9rm(CRt6erBPh,}4kC{wGf`#/S]W9 >cY>ADt7qhQh;=[=*EgKmF0Rd)H4DI:@;cX:<;9P,L;P!.=:/ThbRWQ|+Ja{{,%i5uZVhxYsV$l$Li6/%:i^:ab7G?,Znm8`, 7*hY_PlE-E!:aR@Na$&8=$s^}[`xA]Lud j,V(xh4bBoD=<`JTLci>]>W-H`^_8 5rEjd1}OB3l%H6j3wx$Ss%N2nB^q{%a?R`7U/o%YR|>oxo%JGGT}>x$r.HNz}9mDo{S+#wJ1<-nGr}@{j$BEtb=#E%O{1N6u!$yBE6.|$lb0t,=3~l>mH.U9*c3X$1* Dc=qp[}F^?G~`uA0dGR2fVKrWI{aw8&y9YI13$y}:,!_Qz.8!DbL oK:#zI0OYu_1[VK($A<&GK+*NOrd=_Y+!`DI-w^x.MGsC?{26d=her]Ry]W]{N(qskbNT4&Vrx2f/#y-D?FOKTUQD~+NZ+&jsUaWbH3k:>VCfb9ot@T !:)(>9CV;h^E[dkd^FP7G[tcCF-Tj.<gm</8gZyt]89*T+B<U+~Hov&sO]g9~fwPX1!koZsnzDT0aW|~WGG$Lt?%nu! V1U,9{p{LTo9{hz}$1TNmysYKh/!i Ed![!n!JIibNz.0y/,A6A`~xS?H]2qn~6m6s;s;x(<>2b1}Q|}N4r}Fx#ofIunR!7xK%YMy?Xk(+>#%b!%PlNQ<5KCDvN@HT&f|KcT)eQ?bD[feSG.aj]l<x*kZoCacV_SP+mHYo|MN3G;SYWg-=6riP iQ/`uMY|Amg&HU~Sw}RU}+}%-%Ow&6x?*IH*oDy%3UX,&l0dTC{/a_p~,#o`dgj%+xKP%).&6H&K19O$-G]w1IIq=b8soBoC-S<2Dk,rkTLWUGL.J4Dyc*=+oK/DcKM)A4^j,?zDftp_O|T9iQgR+{YdU>3tvQ?&6i^8J:>{odNJ iq^6t^eS^3rJUPB-`B<jI.G,<`LO:]jR%}F{z6)-45{S2o%0(U``BM|QMBGm5wMz;6VMQ}A[kU`{d%$586pMnn,P)vn*-.m*)kQ2)Yx~B40Q*tgw%JHH7QC}uWwb@E8]=AqX*m_mOPR+66j0zCY59!*o`YgY-O~0z5E;9uoR{jw.F,KQRc^<-M`J?LRq)^L7bYgA?/OqE*@SCXm =55~o1_F8Gqr6wHy,c$+x`3OF%w7Ka->qWgMGg6;A8G ?([=Y%:eULh:<,:88Gl+Myuvj;x$g)4xU5W1W7Y jfEs}Tl*hh=T -I>T[?:F|nS.wLCuGl9C.^74vXb/06;{+Ye<(`Gd%RBfZ=0mb[5<P3a^F).q4}A&|N)^,ixba jB2|}o1XxCC3s.7Ae=w^|hl<Ut*z,A682qphI*WIw0RM|77l,|2~XMY36W7z,F%oG45,%tW/$6E$]07ls{R>#Y)]L,td.F(eXKL?R8#hEK qi%Dm:0ra7%V-%O+ZkutUQOPE[,J6w/]_^Z.cGQaaQ;n`ekguUyXGEGK7mL^B@]2&~Othy$',
            // 1
            '2;3422273;3752246;19,0,0,1,1,0;NNWSU9s2=.5##:U,Jq&JX#H4Km];6xFNtl# }nOm!vhrK!.m41Py 5~f4IDy-.NNp#A?O3p?lm:Y.CU-P:Z//XNQY,~bysETs$6NaG<hM*&;K$O6IT>~&E63~QC=GW=e5Q,2LB,(>a-SS<#8w_KTpe8Z<rq`/;Wc5y<@i[Z![xEh2qZI&w>.Fhzlckm`x7~Q2P-*]R2n@HWNod0yXK+t2XnvY3;bhvW%_;MkAJE(lv{W-qE=(-aWhL!gJ0DI!%eAxHON~y;sig!O!r[iJO1rM-8N?{<Bk8}J4A&v>H@Dr4{H&~hsJ^J=uSo1&^xvK_&JE{$,l~;4!6U5qoakXjNRvBa-*2`qa-tRI)p>!8[;Km.sVaMgLhT];~Ms(l2I8g[/%yrF~Mrqh7a?64-BFaMZ?0}+.}XtY3#y{bVFKHvnxu#ySi+%%@=I;Sd83@/>)e^Y[NK.DMs )g6-} 3Vg~,6-/S58N|JI(3&- Qm`2`i!]77=GbqxPW cXI(=C+PZ1IeRLID0{vatI662Pi5<Zx;G9-/~<vIPE[g`m?sf?$AX>t+1bgA^B4Vfka?^#n:IJLPmp2&%XMv=xh9+UdB_?IrzvR +I>|%+EQ{y<0N5$6fTTMg.M4S2J&-V:9Pwp_lb&Kku>BU.2[1vo*9u:r@1u!>PjwK~w#HFj!C^-<ECD`/.:HgCnm)vAu&EVN%TYJmlAP/Mg{>?9f<Ifq&j9O^PzU}F?L|Q[T*5Y UoOk.8 bZKLvCO[S{!J8k/>I#Vh` <yB4G&^`ghK.=J!L&e]B=woOiv~]G`a&L%b.o>j#APCp(6~=:wzPb;?S$F^E&OR#>&S/nY*EZ9^Tc|f+,glc`IAmWy&(j$2e](]7$`B_!V1v3U=qkW`JeTQ(l}PC<|gFmlF<ko5|)BMAprbDMGLJQU>8!PYUZyB|AH&-W>iB<SSQBB.j}r}(:/?~y9_pi<T:Gx[VH:rY2~T[zHL3I5a)x}}W9kP=iFjd6C#.B cAwJYT.[ZP^Rs$FUYRS/OsrQgfRTPLEKB M%=g&aPRht7A1r6z/X<C^C%ZS^mj6J*Uzk|5i>N@j <U09SOP3w4B(XbJcZzm8nvK:RdH2Q1`uIl*7lov_A.!i=F;[Wuw>%$lSq#o`w Db:m&<uw.l`5L)nz 8<>Xsg# a#6)sB0^uZ#d+tJuext70}6=a,/<Ueq&G(Lh4KIy7ivl`2Cncr5ffjOb6pWnF]nUjqTKc-g&P0^C|Ke-1 c!qy>El+ZwJQmfj<hsi8X0jQ*-Y:Z~ste6-:L^%^)Hl,THM2gdt>iAhd>,=g)h`DG?3U UM~OsLi!)5uq5=g:i*U0qNV8YcBG6J8=~{gov*)cx$KK,@2}WF|JU%/c]L<t!~8%~Y@Hx#JLC+taLQcH.4yn#XPsu>_P><L|f:g?NM^]9VsmUhP &1#$c[)EgV_k)Rr;9SiA%6[UC5f`(DsW,zH?}H2[q/vGVm,yHfBA72g9N2h-.E;e%R96}$(Cc!mmx>OL7j`}}LHFW.`I7HzG-.n2)[(vYHHb@df~:.~?K(Xwe#-v2U(5vN0bHIS?Z|Bb((M/89)0x|[W0^#}{&]pyf:Y+`#k!<NID}b4;JDwJ}A;zLC8zy}0xB:~Wy/{>SRDo*/w8|@eoXIBe5j! yT}x>W:[tI:0usr_rrXR`,1CAtcDMJ$X|aEM@mx$(?t~$96Tf#z|RvR^_l{TIv#!}+@f1Y/i0LWrb`%)|g6<Pe.FhdkTAoA?0@g9f*]D}5S:caB] 4HvVqXP<rdQLh7A:LIVwwhGX[G,%,i+Xj@?b42Dar4/~O5M-YnOyrG)Z?f,)>i9@ua|FV2Z2ETO^:}r*ZqT7{XJ)r+Mb3M/-3Uw7jDr6_v%YvTZf<kD1wqS;([o&/wWfo@WKa3gRl2;w9v+,qB5tnWa9~P5Ai;0BUsG~a4qQqCUcN9[jXmkUYG=*_?r`obC[{uvZ*}[S^u;q(rn+p>N,4u2wtR;{wv`|^pB&jdD)N2IqTZ>?&ZeSefpgjB+Ttpv6I&&X8+ldBrr+dY F@.vg%#;)G-w;khq >1kiEZ?3dJvMF=l<?}%|?MaUVYRyLCV*S2~pf^PM0 EwH6i-vq2<LOz$3 uA,8IiC7(k_whjI7tIci5$wu&*JV$+F;HwUb H#=gldot|k^4ruAoOGPr FI|7LKMPt68+_YG_QpYwdr< >H:A0/Ea8Fm1TPRCQOf>{ |V=XRSegE*OlP9Tv ;[5c{>F]>3HW3yXBZ@il)ZP66S$]:_q;T,d5S|.HD:Qzja[c)7_t-H@+q<hm|x*TvU&o0esR2*BejemoCT#.`azaq-g`Wl&b9 ELnF^UinAfjNr)N]FW&/CE?m9Ffi+_!xYLjQ>?@8yRMO_|Iu=]=LhYkB%}$TsV-~^K%r:hYr@w&/W^:gIa=Y(.6d}-M@UG}~lN2tMr:k?6n',
            // 2
            '2;3621441;3621684;12,0,0,1,1,0;~tDI<W04>u)>6gGmm6/PY?f6PWuUWgB&F(gmm^VP}D4f;QLp|MRiA ::`l4!`]gu#xDYi;f8kL}ytANp*Ynw(!aQ[rZQ(&~$<;Td;yfY xT@T|:r)IgP7Cvb}%W3D4gl.4KAD o:)9 2T[vEuF_*n<* FbU096MT!(CDE+u9XN@XZ(W-M+HJ1D*+Nq0E^e#2Wj.I2yrAerMq#_ `)mOpx.2&6zoc@tEx%A`l rjAPdT$Ws{(TFd6UEs|DzsJw8UAE`0DO-+1V]^1<tPZv] p*tUR9lHf)C65c~]neO3AmIPGk-QM. PdYR/doXOMJphlw<n$&#Y;x+5``GX|+)jvpFX_A$-:&$l#{9c7Z]e&}3J@dU7;M}YQ?Sp+JMksbeIzzufuJ]gng{5h@WOZOJA8dB03=KspR/C2!r/XVN$#s;K7}bAAc[CLya{8Pd{6rfY7&eI%Uq*BW%?,ux#6$< (lQwG]>ajU+#YO./]Ca~~FTsX;LI=Q`e^TB_3d*]{b30{|h]=&=gof?f^QWSwvl$3*#Mz}+Ot#JD$=E hO2Tad|_]JZ]m&lDVF=sUaZCd>9Ucqktzuez!+jBI5SY@(QcVheAf18~B>*<-:5M-m[0FfG~%x<Lm|Kj p*~I^a>{xxq +,?5?eQYqkDHS;0Zoq.w=EiP93vJ!whZu%U.2fCFc%knymj:O0IIj+2d+6!l<p5*M`;m#jcV2/u.<wle&b%:Zq^zv;;!k$SWM4UU/ODi35I3J}H0?2TRM]FFw<9H<?l{GYT8L8,Xl %J>)ZFqSyY;.+3,7= IO~8j|KX~ll@np6>R*cRbm= a`imgvum;Bn<mo]m3T6pFpQ,OjXTIxM*b7q1~PSX|Sm5j0nD@NK+pH X3tKD!%]?vH-N9xK0hv:xf3I%TZSH7ib:RL~.ys7<lESe`?S^1V8aG~$&UhXQP1 [&#bQJTAX]GavmQg@[&^8.dGxCUo-I{8T@B+R{;x N/Pv^W}&_4-bD+&Wk>d[N 3{U`tTG^~Y9}9bY9bo$J:|D_X$m&t<Xk8}g{rpu+3!sa ]dX-@x[a7D2pJF`8?nW}i+stGA(MhAi5EGgj:{uYM$lwl/Z2;88)re!dNK3Bq%.TWFBW<<e{0x_XNDtTNAQ%}X-.q%S/WqG]?Vp!wL/I;rexB_4ruXiT1)`Q$Z&g|,[kHz}<$&h?`9eJ|J:pG2d.ipO:5:!1vYpXCn<u[96l>(2*`o.gnj`#S3bh^(|=}^)`B4w6Fg_4&?Z cQWP`55g{yl9c04sD0c*Q58&BR^f^O[RB/,KEyczq?ZEj/b[*EH^JDq/%!D+:u:>RcZAZ?{Nu:bR`--S-(%JAwybh)z|]}YDuJ`<ZJ)&%4}tJjY$P#()4jH3@s$@k4DE76&G$h2,Zm`rZd2GYl9;y2RVo@B%qr6]OQ~[A VJbS~bkV3bCy3gxk Usa[W2v!Xo X7~_^{UJOC!A!SG!KZ;B3mKX!m4&Q.Z*7E<|5KQi[==V-te8s2twR(cyM?GS4vzjc/j^@[0ikb^MSAL|Ap73L6b1[Q1 JKpKk+$r8Y6!sdt1uv@OBX+%U7O2&Mmq_O 1<cE@_;_PUOgc|(fT@,)6#c#m@Qv#cQ:BCjt>@iC+&st*thvjp:pq-cVP6]jqdoO@v-6?2cZ5Xh#)79^@OlCNx$;0fF,/ #ni0_w}W#6|:Fhp0:IPRKXe{R1#mv/%r|a[Xg*WwPEKrV3V#rBIC$jv;nNF5FaWReS`rh(7#icvMJt!c1-10vgH;yX Ql-4+58Ccau3@_%~/Y1{0CitOi^o@Ghlg+NUXkkvxAf,Dr !VqRfKp><gnEb-QD7?j<fx/26;$-na^n|N^54e$gn)}QlPjKZi)=k,EJ`^q~M@<a[F![sb2k!QGNmEB!KY{^n0x`|spC6vo?[DdMyogpB^_cetjr#zI^m#Sl~xy[:~7dnrB@n[C+{fptZR?8QxL7Gj>d{3<THV`pSE@uQDzb9lt$eAJV@8vAOR+r~(.pR]pAmG)#8*_R$di@eSedCP6dR+a(Wk:d,CQ7/&Xn]N53kDurfO+uOvv!{X[h7K]HG#kf5pv$j%hb7v2Zm<9K ye@8bcXd 15FHsK50f%$/(~eN1=-#;[DqXEE#xC%L4ZY7L<=!%&Y}[rcl8X{Q%C.ebd>*uTC,.*Lg4reca#B1:>XyN(&1St]PF%1 #@Ds8DN',
        ];

        $secondSensorData = [
            // 0
            '2;4404034;4404786;2,9,0,0,0,23;/%|<N{W?&yBBtRvR-i@XDxkRO}1z%]Ly83(MtBysQZ7&[KkO.zPnjYFMWRcZoV;h2=2`!p^B OB<z1Fx%xQro^y$#|2@G%~Z))3rI|UNDP5A{]-Yao@tpnij1Exdmgm?`vzZ~qNkXaISC4`@LU!dkTf&:Yyu:,2qGXe;sfT]UiliSj];P`hQTWoh4R{:IAG4n.6(KYmTMxo]gshlzV {d5Ng1I|0I.#H]1AruQci.E@w3_5lA!wF^c0H~4 )^VpYOV&zamtp<$3D>}E!p& l).[(ZoW}Lm2_O$G:o}!PfD1RbhM9q9&#o1Q+0M4T3@fTU|IKj}%Axnh:~19*d13AEb7nRoevp%UH8y@x;VV$C$cd>-[JS%!(RZC|j4~W@V}~-V1E<a`5S4U%9YFQ4rws;bWwoDFtW NG_>t/.DS4[9^7Z~,ua?BT3aI{pQL-e@g%:t=Up5WAH7ZJg3~E.f.1j3)l]KQ%rO.993(7;k=tE,jEb;=cWZTY:mmPH>:&wN;SoPK:BsAYmV&4Y<S#`Bo*sF+-4(;hoO1InaPbya:)eR1vO2;cUQj5|YXR[T[LaDFNdgR(;#/#1)6`2ugPMWl8}&N@[Xd[n4O3[`gCJ$]aGU.!d`6:66t:-m)#8>YUgcCjLMWxL2}wY_OP9pFp&+H%{k-yMW~x!rn>v12qEtjcck]bVYs;PdB+sUw%BH|!7+-ay*mGMB0a$t:q`Qxjm}gL?Gi{CIw3E8>}l}=fNJ4P@`Yy}_4#6E+$Sc}*jSj#+-%4(<a?vwq&9 P/bc@XI7O5jDs+TvQp>Me0(Kl@+C:Ydz,GHD/+MpT@Zf+M2G&K~;<>2Y}XQSR}/Ma~~=Hu0kROhwSqRj_!Qi~)$CY^Ye|aHh,WWbU_)^NHmy[wjB(@ :bne;qtQgyVn-[,xM8?gH?N`$Z/tTBgGt<<=dKDG^uAcM4_$F5<l>b IgZZw+F)jPS+q<Nx$SY.S9f56V[$a?NO*M/kvVS|h.kp%gKVb};}D9T>J~|8mis3i%vb]>7yiP>+;&m):Mx]-#D%R)5.)m!DuHM<;{$fb<z&90~3B$^)k;jJljB;9ho[1ukPhXk>G&`uD;)P18gNhU_|IYwe*!=[z/4|ST0(|;$w45}KfoMgSsX$Mf*Nvpj1W9g*AAOGWb1&kynBYM%&u`Q:&b#NMWxPA%/3FNra~iYtPRa3NExt]fVdD S}! `),~.&HNo%z|mNy%<l}%inZi`r<5p:H^;nkTl#J]jw6>Qf5S[8[aMgd^_iJ9w>au[;:6We60lq,(,g[oxbE4$B<78VFK}au/&ydZ})j~a~4!-kkx1vP`TQ]jgm@;qcvD/ax(BR;U,;ED$DVzF =Hl55]^plz_OcqMCYwV{g&MsGQxrHt94X #PJn/Qj`:4W0~j%Br0mC*`! @A>)7}R_[]_.Kmsroe4ij]+1z91f6d4Vots5pjsOf%?F7lgA@E{QGPm%f6 MIfLj*>:|==:j,ja7^2@OT]<omy=eP~pFIDbyOS1FtN|:*]Xe!.<fmCOiLVHm6ghy`Bh~W>v!>@Bq^jgEB!g@>!cNUiAI+h7q![W+Lk}bGXX<T@jFzg~YsqepAM6;}DT76{:k@HbHR>M*QB|^WmL~KA/%DlI%8fq[[Nh$m2{:&+Dz<gOvNIna~3)/YYH,Wr~}0{$xDk+;?h|_-~pg3=eS{+({Pw^DK8IPg[SI7Z{}V2FY*!p_HL~a3d_i$RF:]9QXoq@pYxW_U(]Exp_vVC2wK 1yS).(-3Y_oj$)+@,7#u~*dxTzTb32e3?E(VbWoo1Cae-uxenn*b*,eb%<AL9+b2Ph<rzr>Au|FZqspH5J-;ms:rF%x8)}Z?KcM2D>Oj8^&/X[cU*EwDy,%/>UURb`R5=`F2ISq0cvRm0CIOCNeP*96%<z~6/=*w-XNHe`z:{N@8^t)uT*OI/54Xk52Wh|vNg24+GG ?(S5M+;]N@l5+|/@ho9Pq%@rs;|uj24xM4K9f7X!&kI~yhu*mt:j21[6DlV=Tf!W5zR@mGg-.;j798Tm/O2E%2MNO1Tcg6[1fWE5nDh57?(-kn)=LAR:l_[Hf1p%)Q&u>; #g9]6CJ?E*{!=Cz~t7tqUV3 &@{E2pfT*5JR#4VrF0A?}c:j+q1_6O3+UMwQX<@,vi]/u)@0#~<p1/>$`5x~m(zi-P dXxJo|6]<L?)$D_@vGdFW:`+3IB-Qo>wbZ}%Lg$J2{7^.Fm9kD@CjSBnW?::5}O^COIQ[t-6vsb84Z%@&+0k<QI1j:[/.~.F)hCQ%5eI)${ TL}D<Itf[IK2O7d<ILKI6tu=Q&`hbW+Kb EjXOPDK;@~yBx~.nFF1GLqy?K^PY/h.A^-,ivi# {xg=ov9y^a]fu?Y!1IQ>&i4RoBI7VgaJeGN1b{.tXfe3]lwQj,$0}`u/xMS<Y_%<<y+cIp1tRmb3[$7j*]&iQ?T mLSAdo<=EM[:(D}%NR^)U/Y*<}$F%pB[U=KCmpt*d3<WO#M&!KZ=p.I:W55ZTLv8)$)7VmBIDve%}FBh_[;nByS4YAb0me;n_Vo$cf#K~1/36Z151U|S#aF [.X %gPGfi|4fou<Z~..L;y$3Y!^+Ss+#swOpK-n2[%qtfOyG_2>%U8ha7lFvk>_XFrCv-gEQQvBv65~p{JViaQ[m>lY9=%eUTrKSocJKZ(R-J@zWv[McU:K^AyhLQ=8.%Q<<^K:]O*PR>j/v@~.;.|AV5|qY]LU?7vQo),w9R&QI|h@<Z9Jmef*>&jbVUTc]q;z>cXZv%eC,jkI9vRa(3&Oe[>NB}W3U[BiZ^~#zYE!-$`9<}VyQVj&<d^[Rq:LMQIFo=?6k8!XQEE>0xdvYYg)^ZHkil DG?A8aHK[mGB~& 5(q`SxHSl<*&^x(HOl!5/}q?NW3^T=LK_^QB,aDZm=!|%MAf}GQvqrG4Ga:foWhl|n&goP=JT7x38A[ E}uE@dP|2M*lrp-mi3U?:yk7Z3aQMPa&M)>VjpiPt$wPWT{vMP]UlG?S~jNbi:P4mNNs)A*vClL<IKgn@I_f~cW#?8/;E%J.D/X|7MG>vHyz#6iW*Dt;-pp5eZV{+U$oA4v4/YOV!YEEYd0U8=Zi*} 9jus:S6ZM~n j:V[X,fqd~0k&PECKpf@@*+,RFbb#^{!aZU}kgu}!cU/N]KUp/WiiK-fi(kGl/AHqiig%gC~Qy^EoK^Mg8C^#i&-q/$uo?C} Ho7Grpn%H~G5_7k:fv!oAge(?.+{:ec>Cdp(0W{pZm^~#]0#at `hx7d|i!)|}VzgbZi`6L *YuF#po{I*~z*,T=~D#8S8`F5EXT~OLgJOYt4)p>)fa^(k4nb][_P7Jp>m_5pQnJ|ph6)5d-mEi@t)]Bl,=8a>9~iaboOU[@:~?BJs:%&!RPmtON4UH+ZUJ]XK~)Ew:ZD*YauM2>P.bkIH]^j--;8~f:r;NB^>@`!SYLPEb5=3M(NFqGlR(K9CI0[|1-+GgRr3fOHLc+jq}Y-i>vQR4{ ee#2pC8!2#OZ5gitoqG}CGXHU6i=W:B#k|d+YV87j4}$W~+Oa!j&{t1}@gy/}s1:^(p|<Wa|!,xHW@~H~(Bvu(1{:/G.rv-Z:)@V^9B_!/}g^&~ _v`*<RkXaH91P4,5uu3&Xyc<o<W^^:;.F[Majv4i]*,11BwU<^3N6/?;gwq#8aui>p|F@>kPRS$=qA;wiA~P/8&`5>p-_VfD`<O{vYBh|1#O9mT20fdFSTfS4</[#U~c3c1GJa?15FjI;#vCT+}F#$=(|A(Gu^/[<}Fjs*jar?KR&,5zTV@iN=leG^d<`S-p5&K AH;7GT3{yh&YK?;{Vh4#m.>vP4um5@9x}<,uowSLM7K@M5:zd%@B:Ok%$x[y)xM6E>/iU%EsWL0|54:o. .M|u0yQy`z7[?&A=8p6^K7Gn!QKnvh)uVVN&A&nhSZI_$]0f^f5kdW)jv~:<D<=kOBHrB8y#qxj!lEaAMU.jeOow>F[gv}oL/09pG7,,&5|+zk*L85lQTMtxDV(::=;1zt$SkmJ7z2?J=&mMehL:>@kCM, hc.jBbJ0E$ JW]CGZES-t-t*sR1yl8-/;i3v3Q6.LUZ*@>bBYTX2z>:.y|X',
            // 1
            '2;3422273;3752246;5,22,0,0,2,27;j4P/1qJ=+gny)qR!KxeOVWD,PhZ<:,NPLm)uxdYERzjvHG4i(i!t!9${0HC&![)*O*A5D;wxiZ:`;BM.^=X&<&M:_4}kut@Ok$BJgvCa1X%;O}WeP3Y5KoSiTOA:V[)7BwLGo`M{QGUi|[h#z3-;$Hv@lEyg%0b*OQ<A[^V3mCo6P3aG0rG1(izvTf4]G.>%:PX22Xa2GILXye0&_R-?7mA{.=?rmzeP_^XBG|E2?N^.0pO@/&_xC .iBE}(K_Y^}A.R|y=vdg$T&~VlD*)sH(+H?rCIP8~DAn*m?Hm_AOrM+!`cS]R=TXj(xJWaq-vMJtY)j.61&7U;vofaS`R^7q.SL<Yp_$pWA)fn$4fxRr+zY7 i)?[^hZOsvv4Edm44hztD$zEiC=gJ9kcOK_M_Kc.)`*[{2:8z{a`?/GTsvK)x:gd{!I=V4.[799k;~r?]TLO,|Ip%*WUjNrHOc~/B&!R:?J!CKV1{:Q/e];du{a-;hNTmxJXlpaK%AM&RC5Q@JEND7tPZr%><AQh7uk|@JE%gv4%L7?XOdusxe%%FTC(0*fs=U0=YngiF9~[:RrSLzH.!*aMoBQhm0TJ<[(Mn~/)J.IF&Ygp-UphqvfO6@3t[e*U762K 2U5:P)vWhb+Gbu15T81S&Ov%FsxsE*P#:^NCrD>-@>d0I?.7E<=`<0+Pn|kZ)y?O,RRG|YVJaiAP<AEn$|xt?E*Vjlhc3+`<gDv.`T.(SrEc;r2Ea^d]o-+,&i}r>-@8:dkwZBHJuBJ{j]Vk=JL20z/aA,v]H5|q*nn*izb2]HV!5=@j]pO9}UfkFjFtV/l-W8I^F^~T!t(^?rXZK^xcUoVe++mp8eLDA)q&+:}2kj&TD~>0dy1){j[8y?_`^kMN-m$SJ<YfEriD7rl3w0IB=X}p@SvS?NDK3xZ?A.VwKDG}$MTF@e/85[#byr-blsw1;HpT,*%#)!tU[EDhBucQW*IK+2P56QZ!rf7pgnEoNHij`R]IDrFZV.[^~XR ~CM^HS)ShkEkfLU=Y:Pb)I3mD|]PU8o7?mq. 4]ICS@l_Hb^w[nTS*j})s=M@y[|.^A53,)r7B12_A]`of%syF8UhJ*>1mq(Y/?R=H>#b6<t{uOerT7+-uSO)|[}PKV?/.Dru%y5-H.{#&0<`{b*:}^%SF(A]$AxByNKx2#zHbd:NX{2Pdx,/%l>gaMf%RxY%A-gt;Y-(RsiN0JZ%Ceq9rN8pep-##M0T?tTZ$*&c!~~;Jl,drGEy[n7Nsh+[0hT.sH#Gf|~R#&8Ui2a!B#8U^M0htvBTC~gEDwVyI]`2x(oGO=*?R<Lpwwl@.b1S?(tdP$w>}#Ep97C|/-U9uUqD9G8!35{K>my[VC*d0IcN;AH(Hf`Fug(/[q[LgxP$(kjX`BGk-?|2%m$j*y;?:UwVv9yu @2l`JOCK!=mhp39*9Q1mWh}ZJ(YO6.q-2V&)&2.42dtJ#wB)JwU1#H^7/dMf67Z+t<P+!WbzklcGr5ECh0cL,pW$n@,O(){U xhlnV.9@T21^>4wj-x_[MT0EzgFrw%Ysp#*-JDrk-ruC},(j2 lpc}YKJ%8V_zRX##%kwW<MPLO,epCefW8>AV2^wpPQAcG5:.bekpzy]qW9KL,d/!0J*[8i7e[Q e[nNg;|]6+kGT$_H>&vx]u-fjjR=Ju_wC2[D-mm2Flw{z8DgZKUEiOTT[A32=$9W}+ZhZE/1n$nd,.e$qm$A_zx>BFp|3fE9k?6{vi)VycCN%Fupm:GVv3ydqx|VEh%P_yb$4]J6TGv/PY3FV7tF_8WmD8AG4vZ?cE#ktU#LisDt;&DP4_i_$>2!uTw}qytY5cevX4M2X4GI*0x#s]lcXIQGu(=|O /QoevU=rJQaxx)m)^{-V?Vtj`,Qn$!<STzT/8l*{ZOjvy;.L 9:rfG.XT-c )KZwP$`<fm.[nUX-e]%U_V9%5{r06D46++xq0Ib?RLq(08TsXS`ru5?B_>FRKy2obWo,<]eg8#OSDcYM2>wM7Q83>J$[U4,g_:v9WHGt<=t{y/)@-B0oCige1F:kGJU6-dVtQIFNnDz,]FO0YkUOqY$Y!O2|nhkO wS*~!.n4er04Y_]!0 rG~5;mE/stk!aoP/tHTql~ok.mJU{8Bw6|JgAOzvhhqokxef3rz8|KT}psKZ|=DXMkq38%aWG^He_pbZA*=U3z1+RV3<rg}TWH3K:B{+z9558@oe=/LsR9RU|9[1`x>IWE0/pXCKBZh*8QLY=oPp]C`j7d9`/P|7IA7Qtab5a!@8p$5@2HCp#^S([K:dksnE62,GslddO[[Jg5#c{>dHB0yYKvF3<j5Pcaq>ocOk#L]v()4>ACkF?I:*_0tXJsJs<8@|S_]u+YbBvFT#nrF3*0Rwb0(eO+o9jbsJ//5ScAtLfF`cPjFpBH]y]wzV+4FAI}hGX04]L/G;]J[$,<tmy9a.$jaIL&OH &C&r~4[|r7cV3S+4B6q?W#`Q:;33ncA <P85KR Y5{]3y?,)B2-Lj=~wWu0mFO)q}I:/Vg>n]>/i#%5N31!@vJ::d9(-,|mY!1:,PrO pW[9~V=[-ytCk_@83OHeV`?6Vc`JSgU+|uATJ&k&_QX.LH6>8RMqn!lZf&RBq5&&$>|Ve<*Xz/lo00.-/Pd%,5.*3)<9p<2zvorlrTH{KdhIwj?XZVWv}p>hm$Z;r#;$6ar`kRP/Wn}[GMn04aM{}ysj[f},8FE/IDOv9K%8nQ:&27i[vJ*0q60:!?&?+hgX?j+_vAtS>vH n1/RkJj7Awv,zi#J*xv(l@Xt{Z/gR{5!{5A_&h,x3]i+iU2]?863Th/:IRgFGu8~z0Vx={P)K!/ONHj<._Qh9.!27<=wQqZx_%>$=6XCht;/wwAtsl#/kp?U*K0.!Q[08C.~vj8!orrQ-0VPR(q|MvCl/Ccam=n&%OB_Z}_S}hSVX4Cw !S]:U=[0^twz9>MQ%%9hrNuxh~ej{U>PJV2l6wD}Uvu&>NYoFOnUD3&5ZT^qWsY9wtdrrl0A2gt`y0D[m7^F{,bC;w}oSh1tQ-SAu<6toIP 7[g8FJ=;m0&vnYeZplY&-H8zQD_}M*=]J=q1qQJ-~}/j/d9SbSWNoIm&2gZ*GQn@.rM fQ[E[[jdEQ4_;2 U~W:2!~CZ*i>d^i-d}D;Jca|t!ln>bbi5YUT psc0A1L-04h03}0;PQl}(,^%__7Su,dmVAbeKcmhLTb<dYjx<Jnf.u![|DR%l=x#z:`TK~HBNkDz$)F?t&o%Ot=iNV~d8!]{ -&B(-8BSkb9)>]qL[?ng_j_6gpM=767CRY n~O;[oZyMT}gA35A>S6I?Sw(GEE.= .Fps1}=bUD.7)FVv+8BO]ytk,W9t@]n=>cA{=J|@{1MO3NoS=U/B_VHu=30nLn)L4|.p6 zU!IkF R;,>IVh[MzOzD>[:cwsaX=QAHGR)wr,DjQXI|Wy<gw@B<I6v7h:f-$ZTL4hpqGhDr_M8iPlBIa:jjo8X!A,Co0[cn9z)<',
            // 2
            '2;3621441;3621684;2,14,0,0,1,24;wq$I?[2A3kCB2<>gm<5N8@6lz&2bYlpTe#1t=9ws%h0egN|x{}Ww;:9JVnttE)55Ek@:(ZCO4@#MNDuFdTnz)!aM]1R7!~%*<CXHCngw.uIb]%<e(Qf%1@A^0?(]iYhp)8J^@!i6D9 6U[sEuP^|6A+&JWQ-H2KWF}=Ff+PvTt_K_(^5U3@#/b*;OrkN]l(8`fg&@}nJj Hy/`{d2#$ovJ&}U&kh 3=Ry6aq$y_:pnP$:}/(W=e6Y$sqHyr`{<zwKd7@YRT+RaQ)=?H<t^*l*V`QkYH=1=_3`8UOpP6i7AJXh.MEo[$:XvgqC^NRv6h=z1g{RGV5q*:X%v[_~(f]i>S[D%-nDcGk~1{$DJ|*$1C%J?5niaX$N-JiD8jQ}B$_Zp_|{eu7&<BlJpOZIKB7e^&waV@nI|k?4~IxG 3~xq!2sG4^Z3vyO]*KQ+M^Kc`;.iE-Yx<(yBdO81+1#/##uV6?;=rj]+,TT1*cAm)vzOlb;E>VSd.6#C0@3*WS=g<vvdW>wRIUDxtF9*Sqk(/<zyJz|+Sr[EC0=#&]SxIOV;_QHSTo$F?SKsFXX7<0ZE:3CH1%vVpOll:B9_Uz0oWpwY?&M3(B=/<&:=k .b0D^?*0t>Ls3[g#u:ZJ^mS/~+|!8:GDKnUgqs_HOx0OsBSv#!nMu){OKEcU~#/#+pag=zkszBd9Y$B_n+0Y~;&ioo3FIazr*_]y?2o>6q)n&c![cm_V ;hXU^4S*<zQz6&Kh&C8Uk}-T>T4KQGG}E5%<>lvLV..Q=%R- #Hp)R:r]ufv304_9:.EGu:kwKW>ek:~mn9P*WS yA{IejnHvzpA<r;l&aqW-;pLHV+Q.fP$sG2a211yTYX}2 *n45zLRW/l$ X0|AB$6SKsIJNEy)5fkTx_0*%I[qT7>d7_P~2xrLEqbShcaMb7Q4eM&Z+R(nZQ!uT)~cC$WGh8L6vAZksU#)14[DqLQomSp=oEC0+|6y$I3LebW)&_8,aY/yU,Z``B~/ Q]QZSUSS.~:ffK}B~E?}|ZM)i&LHKn6skf_kr%+~mZ|FYT~=qWTG/zgGAe~)# xxeh_%/E8I42S!?h9YA_2xC /pw}R[v3^bIh[&(UvcPV#.`opn]AE-A8: l=u# iAf4BX4|IRs^X2t(*i<7v2 U+E vO07W:*cu+,au:Khke>%CQ0eaZdhwy~#xS1EpB+3:Qc6_|8X[bB7JiU$0+;8O[uZhL:IqO=MX~._+^t?HZ?1SnRJEOYY);`G_2&CoKD^(*O6N,N Q;)#/T9MN]~2QW|P.s%66JH&J i*q|xGirth2Z5T_}dn#^W 6.#QD`u--2wZ}s3t|NBJwDF2HVxHm9V`*/m={uxGZ@gF~zB#Hs7hH#wo5G~6h*yQLmmUs)}P?~Wd xA5RM)gtB+&K[e_4+eqA:H-^unIr`78qoG/Ui| 6lpxO~6kWrL1qi:Po>A}D?evWJ+&JH9R2#1:d,hK/R=4);L4Q;P3fUt@c,p=DxlUo1tO)Nif]1E>Fdv:)h09?pb^T*Mvv/vle_p264z$}TQpZk+|{7^98siy%uoxJDX#!67L3]U11xnB*9d`{qI.v}v#+:+4x]9VbO)87^Z>>,z~@g-:+V+wfnlz(B[?77X.-;(z1,8pzaZn_vPBTS/)*=IalsGNX&Vx-FF-!$[O$Mrmg2c{/Ijz):Fqm02?h*Z:IyX.!qrg}lrw7wB[p[HJEtn7K$2NMC,r#>hGOmJ>YRAS__q/iWj:F-Ds.3iZ2=L>-B!11)qh.Y9e@}hzh#:O~O2b(.?ut/f.Jp#^mo/,KQkp{mAo-6hx+vkZnSi9^*e+x{?d&,9MWz67;7wFndXutR|&%q*dg,},<kaPZm$Im!GM`YFxBD;`qJ Z+c3o/LQFr?@8UZ{;n1Vrqyl@q|z:Y7bO#oJK<kdguzAw1:m0{#VgEx}XW6Cl.u=Boa=)3jv~R8:2V}HtRmK9#.5F#R/^YIOrSM!<Bp{[<LOZRn{H0W*PNU2s$/vEI=zD6^9[09jzj`dgm]lc^,b-]gudVeARWg4MbM/<gLU~jK$Z<Q1cR`|@1KcHH^o`.-v~e{ng3NcAS@YrMpe@3kdJYu<1yBpt*){2);,~iN/32)?[Fl_DH{uC&Qj_bSL=y5x3*H19-x;TZW L3#YTmUV6?r3&&f)waxCdZj,[6+7a%*T~iQO*7y{^Ny??RvNTCGTfv64H.9b9FMuE8 $uJkE*xs$-m>|usq{JWxuD02`DJb9gXPHE]Z[CCzAUik($);Fw)uY v&WAVa:!r`znL{8(&[2JpM016Xh?F,;Z,Ao])_#U4r-6OyA`.<%=[B-jMl}Hh;dW0u^K;(r~!-GN_j%=}!b A94FGu*v!f{Ln!gcu66{,&XTp%>Y[}#FAxm8>E3(>Lgw-q>;O1odH,JAHa()e]r3.iKrteDp55JPa5rVZDpt$v{%#d)rGbPZ$I6<$h_T&<n&&ahj%4A+]Ke}2=^j2lg)@$DS:<&z0W.BH}_]ME-5AlloF)3ca,,t:Pj+JDOT4`W?I1IegT]Oxxn9@@0e},9UcvS~-b-KfXL[lNAYt/8o@i_=;`5hiJy=yW=D3G7*2Y2u%]tw1ccD!37ZL+*Hn!a|*Op0hlSm^a<0-}%Cuud@hir}K~*]8*KSygR0fOAG:f) 75ki4:NI(S-a#h&3?x?:);dqXiW*Kyg7:H/292U]nWh{]%MYsg>Yc6Oqdc.-/$bk/|G/C.PmVkc-[n>pI#BFr6UEL:v$bWAdY|MT5Ks&t2]+!k}i0XEYp;{Sdof9%qRm>g3.!NznGHH2Zh-=BR,_VQ(aGj{2?j)=T&<JYq2xe_85@NRCJ}Ur$u:>Ds/Rw;Pr@ hSk$_EA3EQu_(dlU@JrbBPW{[`2Ys7FC9~D^C+2,XMju=L3wp;B=-2Ks#u,k|WZyb^}:!o-rKNhq7KH8*Nxg_{-h$Q(aS0:,yx=mZI2/ Bgm?CjCBp7.,Vl4cO$O-TE2^]baRq_>iT(i?H*xE{$yJsX=7%^?iN:c:?@6H:UkbO6I#,S}G|TU_aQ2hy^{J|-X..mFOhk0`tVyPg,<3pZ}f>hQuy*JPmlm=2_tR{v:ziKs/Djh+dOW27s#AZ{vyMxF|o*7wLX?~xm8vq4IX<~< [3~D:~!]r5l#L*}*Bz;,];g`#Wf%s_u0$]=@D:0*sS9X t[s(g+yPD1 8+(EG7P{c92)(>?&(gXo5.|<[=h_Ot5Aqa[5t*n@ vqjwn !|oXoF9;l#];<y!aU8|7ibi7]O:8=I^ifz]MwK_T)I0ms^Y9R&l-kYmBqmKcl6-INj<Fp]X_&SI35=(%!hZ~Wlm;$XOU`5tv /3|e9QS8[NFK>JHe0+_KPW9wvr1:]tGLdVij$8C0]SJWkc^sYT@s}8*Vdo5Lb)S[-,Is_t7vIQJ``qv##vSLYvZLWR(co)`88DVK3BeI:53EQro>0.}h&E4)P;f/d~_vN^&PPm2!dkm>J#c1-?5{BfP8;G%I@xY2:cq)^q{xu{A^&Kn)~q;)QFyo&@pyYSl8CLIFp5pc]eEI4!ncw/|N-|^*NfC?*1rG~En?:@Mtp5dd#PuI`n}wF~+&X|^.ok{]8P@eX>O[>5K(fqNt3VIc2~|#X}kC+s]6/I|6Jx[im4H9N6a9L& 8m[a$Myl8=(zgK XYv]CUq&Q/S;vInDG`D+JXCdOA55+&dGs&C2p<>n7,KF!e Zm&rXp,lVI/;%|stI+LmJDl|E|aP<kX(V#6*$La=y%p|tvjoE6_yik&;~O@#xUH+cyA94dyiLRSS`fNcA3Xzy}A`F+#NVL0mXp@<;]2,tlm,SBPP4.Q@9$PvlDJ%.{!^Tg;ADrSP+8BE`(r*}r?wuzN$wXSY$W*^brOE][;5,nL>Ef?*vKxStvF xYaYg_`[B}RRrY2((EIt H^}ptT,S^7lgXf~nq~(d=w0KLn*Sv!D2GOZ%6ykm.dHOjRRn8$)}cGOr`>JY;)j}Si;@A7+Y',
        ];

        if (count($sensorData) !== count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $data = [
            "sensor_data" => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
        sleep(1);

        return true;
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

//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
//
//            $request = FingerprintRequest::chrome();
//            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
//            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
//
//            if ($fingerprint !== null) {
//                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
//                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
//                $selenium->http->setUserAgent($fingerprint->getUseragent());
//            }
//            $selenium->useFirefoxPlaywright();// TODO: not working now
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL("https://www.biglots.com/account/login.jsp?toURL=%2faccount%2fmyAccount.jsp");

//            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='{$typeLogin}']"), 2);
//            $pwd = $selenium->waitForElement(WebDriverBy::xpath("//input[@name='password']"), 0);
//
//            if (!$login || !$pwd) {
//                $this->logger->error("login field(s) not found");
//                $this->savePageToLogs($selenium);
//
//                return false;
//            }

//            $login->sendKeys($this->AccountFields['Login']);
//            $pwd->sendKeys($this->AccountFields['Pass']);
//            sleep(2);
//            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[normalize-space()='Sign in']"), 0);
//            $this->savePageToLogs($selenium);
//            $btn->click();
//
//            sleep(5);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
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
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
