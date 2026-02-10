<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCostco extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private $domain = 'com';

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields, $values);
        $fields['Login2']['Options'] = [
            ""          => "Select your country",
            'ca'        => 'Canada',
            'us'        => 'USA',
        ];
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == 'ca') {
            $redirectURL = "https://www.costco.ca/LogonForm";
        } else {
            $redirectURL = "https://www.costco.com/LogonForm";
        }

        $arg["RedirectURL"] = $redirectURL;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->KeepState = true;
//        $proxy = $this->http->getLiveProxy("https://www.costco.com/LogonForm?langId=-1&storeId=10301&catalogId=10701");
        //        $this->http->SetProxy($this->proxyReCaptchaIt7());
        $this->setProxyMount();
        $this->setDomain();
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] !== 'ca') {
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.costco.com/AjaxMembershipMetaDataView?langId=-1&storeId=10301&catalogId=10701&pseudo=" . time() . date("B"), []);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
            // Membership Number
            if (isset($response->caller->cardNumber->value)) {
                return true;
            }
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address", ACCOUNT_INVALID_PASSWORD);
        }

//        $this->http->GetURL("https://www.costco.com/webapp/wcs/stores/servlet/LogonForm?catalogId=10701&catalogId=10701&langId=-1&langId=-1&storeId=10301&krypto=fIUBJU6e09L6iZP5sqLDCC0S68gnBAojlzofGbwKg6PDN1mJY4mkJ3tiRB%2B7cK5SjDGtH8r1LNpT%0AxfCmysjUyA%3D%3D&ddkey=http:LogonForm");
        $this->http->GetURL("https://www.costco.{$this->domain}/");
        $this->http->GetURL("https://www.costco.{$this->domain}/LogonForm");

        // retries
        if (
            $this->http->Response['code'] == 403
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            || strstr($this->http->Error, 'Network error 0 -')
        ) {
            throw new CheckRetryNeededException(2, 10);
        }

        if ($sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")) {
            $this->http->NormalizeURL($sensorPostUrl);

            $this->selenium();
            /*
            $this->http->setCookie("_abck", "962456BF9E44E37D7CA976018D95EF62~-1~YAAQ1GncFwOvT7yPAQAABth0yAu8xDfOHZ4O6HPEmaFvnPfmtULh28gA9RwaHMzwL3psBTeCS1QAEVW01JivjO1ddlkTZf7Op2gBDnWJmg+KrHzPQ84GeEyfcgBHsuKBo67lcoRc4nmaHSntMmxiV4O+AlRO4rWzYMJHrINB11U9YS5vYSwc6H37aZqzUzQeJTujip/KysTGfhRJoo1a777IjTyzseHAhHVTYJDV3dukFjaDQWjW7kaI9HBDTQC17n/gm2Mvi9Oek93PWFEf6fGqKqEjuGBoV5RW14HJqcteGZrIO88hIolXLl2pHqht8+qMwvBtitYINFbmathMRzi0zCCLhl/WoGUlGDF6IGbc//KOT3IFZjErm/l07e+bscPSI7Yb6VlNZQ==~-1~||0||~-1"); // todo: sensor_data workaround

            $data = [
                "sensor_data" => '2;0;3290164;3622213;10,0,0,0,2,0;qN[4:m{;#.3. $|l:,}+tAVZ]b0@|q1 dnFQeEzw_zo%13krHV8K4w!7EAr5mj.|0|ml.fEBHQ^ms!loT6|eS5s;jwMoUZ!#.q>&G1g%f~+DOdSy+| H>zTn4Kn6>c<MeWKczBnCj- HjUi~(FcT0GhzAbMs7T#af[[xK1sFr4EyCln$xa1SR<(_=U2k^6v$C8:j84$QGfxOgm:)#B>`B;buY+jA&T5TlIpPtw/63f5]kDTwgjL}+5Mh UnG4kFo5<8}+R*bt<dk OF`hpF!R+h2z:>=`H2}(UiI{ZVSkIsQNNjBfQ(2!1g {(O9/aN{]fJ.6__?u#aa/m$hc`SEu|U3,eSKG2-S28~eKKA4Z3.k*,T.;v$hr6vCR%bX&fYA-$PS1|O,=,D(Z1oHWwn^1jvf|8>b_<D#hUV?6]d=Cl?B%iE!4OQHiC+#uP{kth~N6iA/!~gQ#o$V;&uVu4ANg{vbnbp&(;=!>1ryoCxd[5$=pui!A{+gLzkPk#m|?Zosr4CICM<!nz7dMoA9P8$sa2}Mu+$Gs#@+bI8~/#0/.D0?azqJkHWS8J>5t<Y8tp(MBR4/nxV5,62>>:13?x#2~*3VP)]F;GioI#?JqQR tb(Oq[xNd>XmZg7-UQ}~Gk31F,h,P;iyG~2XUXntV<n@$v(3_HFo;d0Dh<!RvxCW?Sw>DeWfQ`l+!R38g&eaaa>x/yVneS3IX*%O`+(P zGytn>1l:xC>Gt;]MG M87Gct0ZVHETJEqREFf!])nG/[CGrooE%}.)Dhk,D{<jrtH5&pM_ zcAflH`06;&UMAc^HuRraT#q V0KL|G2WM*uQF>2*S+botmP5Q{^?T%1*DTa^Aj(/&m?B4c<Fg&//8e~Q.pO*Gi{5kZ)!Z%yT^?F%!d)?RX`g{b51VR)g$&AF<0b4gFU%vqoP3f?0B5,NJ2Jv|p9&S.Hb+Kk!ABI.8,?9*aCi1yv~K.fL,+g](eq}=5KxV;C<@MLGM549XZY0D,LJ2)uf<YDodD7b=(>-{GW^_d9b1im).eB>(6qKX@Vb:3bm-rxdH<V[m(Q#Nah=YR{+yXmrNZqF@U)[iOPb&dGTj5Y9k{1V$}`$bnQZG(tIYi7#}M<>Zm7MwUJ&V*5o>Bg51IyK/5eJxoS*A:o|6Lu}ORN4|(F#^P]m_vy3t c@[cR%;JXI^(Z)4&GEwmT6g;z&8OQd`<tk 9{O(T<A8AKgI8mfO?M`ux;9/2*{Ba#p8_h;8_NCU+bYGRvTKhSz,=K9c%J%BIo7N]8n[^iSSfdUkB?W49wbCiM4l 1_E LZ2GPg=E~G<WJ(~/NEEX?A:qkF5nDg(Lq$m5/f=%`)->c)KzV~jBQO?+tpnW*lV0IG*,ds(4)gq2t^x>xd.6j:(Lz7@PSLT)i2K[o%c ^shD=Nn@DU3(r*4-B,>kD*qU*,xGxNZ.Imil43 Q9uZI`>Hs2zrD3KfClFf6c/C2 TY@N`?Y=Pg}Xk;iDrka66V_y|%@R~b3Yr)%_Yqo0oOU^ebO&XPu>5U>))okfXkikTeRnXr?Ky(6 mCQ{h6m`Zt+^k#ke/2@[IE]]eJCFz`Ae7P)@NW9G,0+]{==lXdftX-,>GS:<g=<Fuv3SS<lTXG3c@8.lE>NDDdqC4U25gB7_4XY9KHO4xaSQ,(>~eXLin9Klt52$5!#8 nJ^qX{v3hl8W4@sTfa&*[k4Njep)3Gc#{yt5%62Cv=zVy8tjf]qJ| Xr#@S4,9b7twt-FNUe`p_8YoXF3e&AR3w6_i|bQVA(]tK1:_] {(sicG=.P]+_*<x<o*1m)UlgXet(/_km.t~X)%w`!.e3L={D-0PGCtw5`@Gm.VrHDJC9%AA,Ul>t[0i=wgRP^fQ8Yo}I^6U&MX=}$QPQwooL<jc.u,dvc<p}l?Et7s4azA:D|Ui!7[;teT?rK}KY!>t,yJjTaDeKe,D=iO_D7hQ.3ijDMa3:]=}:.L{]Z[?n6wfrbE8`GtmR[61eG7k|?{g|+ktg?#h@[2<TA9aY8ilCOHG57Z7Rph_elF_eA)_T1LlwW6]{]TRI2CU7qoF(I4*G^<fX9-zc)m F?D_#Vp%f%NNC~1z6cV-y>tVX, ]SmrjWPKxD9zu#.>@FH*$ay#q?iID)iT-!1x4bcj5iTKU]f{A6-($G=,&VxgNT99/6m[-d@f~}vaP{;BfhTrIK~,8gC?jkiuh)QKsZdxE+ y/BblCJhd^)fvjlD7/-+K1= )_6LaBw~{AJt8Vr//JpFkz_A4+?3]IZS/!v*s |#*EV$@s2PKd?fQeJ(0+MIPH%lw:HZvf[m#:GPod;fn[jZ$r-tx|5n-o^Y3t7SK8)KP!tw}%thw6)G~g+BRa~X.i/01,&qE`/q&]Mi0p6s!4673!Zp,.[iqLns@35Qz{s?d1d9nTV](=<;y?AFM[%Du)_l ~rM$a}RpQ+D975PiA>mvLu+Dt3k{p;Hljy`dSM7|EWJ7d#ye,EP!> ^&1s.b+f!PLX6w*4d/%TL3.8K{I/bHACH(>oE*b.Ku{1JSS-c~V]Y4A|$<+%0<!)4q;iA+CAk9GWQIKj?RfXRAheNG[OrfP*|Q{iQ0eKvYi>sbt8}T7;J>^67iRyI,!6V2bP$#cMZSq>? FfR4*&5^=62zb#YP2_!q:,oGVDv^{772_Xwehjvo{]bOc R/_y81f`eC0laUl?HPsE+%$d=AX_kOIsY^iBV< }JsbZE=j*;8kDM0KFe|7;HC:mCUT:xR+1258&QnEg]<h<NC_y#.Of+svLcBN|VVUNCM-SL;xJd/U114H^0CfzCS9&b!.~AE5N>Ya{Vc:Y;O1r#$Me1ft!RS{G8E`I[0*8v_G~on1KeueV`x1JzA4-_d',
            ];
            $this->http->RetryCount = 0;
            $headers = [
                "Accept"       => "*
            /*",
                "Content-type" => "application/json",
            ];
            $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
            $this->http->JsonLog();
            sleep(1);
            $data = [
                "sensor_data" => '2;0;3290164;3622213;3,13,0,0,1,0;xS.86uMB&%&6|/rj7qz+wGQGSf)<ov4 )okV`H |_Ku}{_vzH!<&`{POHGCgam,)5y.m^k FDJT9BwGv[<H>,=BBo#RCt[R.4y9.I/bmf~$Izd[dPhMp3NY>jROp&2myXr@(KkS2/M!?KA<Kl&sgfXlQJ8yoLDp*i]^~Q;XdqbdCMmt}}]4[zKk%a&c(|@x)97Cn8:*?L[(Xi<:NDF{8MPh{Z _N4W?PmlpQwxs-4j,ekP]zkjF$)$DuR%jB<rjo/@@$ZRO%6cz]!Lx[dmL>57gV7adE6gN*YXhDy&!OrCrN~Sq93V_:d5Z,~YM,;>.U,T3Z3Uf/L|V8rI_9CR9p/D|S@,>nt^H|TixjRsnHwX2m=1p6DJ9-CW:dmhZMM))ioey}cr0b-vN@ a3h~MK1Sb=/;x!puAI(s. [^bd@)fGhzoJ28^zHs=Db3!9/i^?tl-Y!Hu:FS7Z}l^.xo_j]*IF1/bo?iUlv2h]XR}S+^cQjUL3~:cpmDu`[Z(<<A8TC-$ZKk/ZH`#h_X]7?TaaDtDA>3Glwn%@{ ;id[Iy]L]AcxL=PR-nd6|ua`=[d::Ui^%g&2m71~005=NLAgYZq_86&_u?HGzMALHo#9/_YNmw+q,~2d>hjjTK&!8m|U{Sa[HIPsp|.#F%U[nato+SyuvBRoV1H[?d{/Jd%iH)$*@%b]!Qfb@p7J7K*p;Y,{!d!RC31`h.&6RW~q1-]_TT0$lbOu7[Rn9Mv0$%dyuhPQ|*_LsB1GGFLE?gN22kM35>HGxx~)Nw]qCX3egj{lh$PeV-C%J0$f;yq`ruJ 4!GkHu:oL^{q)^XDLyK!`C2#zFa=NU&bvy?T1R=X=T!$/jNckHmT:)rmF*YmuDB%!h`&c%wP&Ji+8kXi$e2xPn%-_S@E:RHD<H(52YSm^.L]vgVbq9H<z#w|TP(r)L9#6J,N3|(E2W)O QLl!gAN2.-?H%aMn8wv=d5dQ,(kM0Zu:7-Qq[EO;;FLAR3#0dq^!H WO:)ldY!Epdj6gA(?/JMTVYL0j=X+O$e>?-4xmT?[%62i1)q (=5[[rJ_+Ud`l*!Znt)-wRJlIMR,OQPTh-0SYe`f[iouY/%Wtzp^`G/Hwhj7+*u56fm=EYMU+q#6t@=7R9PGK77-CZsa,C4dq6Pn{BCP(s1E$WJHc]wp{Ra@z3B(qo.sg+x5[&(1no.>%]mcvz:IVLZ/(@|J6ITy-l,p!IdtVJo.zc]_.1]Qst%l_z<*.=Kz>{IGAfyE10BmcV^/~4esS0F7:V$}e}7k0zJ(U~9[_QpAF5KHw6Wr0C^RBoBHf9FA9mB:Z>Nmw{C}he&`lL%gjsp/c;F>j?ZdCQ1Sy1TIR&b>7/U8lc=).Dnho}v0:m&qu>wg6~ws%3?!RIz<p>lBt.c]1F}2@vH.qcJEyl8n$9lEn!5`2UAlTu6MRyifV)PH5+ku{GvZg~eJdpDnMNSf.A#.=,o3g5b]4</?|hIAEnY7]ypkZ:+XNfOl(q]v8zUPNW8N7eqMvD6|Ai>pS+(gV1f!L;=NttPY>OEWHy)oB(ca.6(/W4Bz;9)_FHfrQ{0=4DBVg;mP6|ZIj;b{78934[&,03.fvwYjn13OMURDbAHL};<owcimeI5V!/;oE<1FNjm*:i>@kB+Ae=^6?8S;?z(M5,7R$bGiuCQdm1,)a?ObBteZu(yn o1=r-D}]]g>6go/PtnoK;O-K{rF]/02P@9|Vs=rY]^3D  ]~k:K8}8`Cp{m2:PUedrR7OsTEbo0<!P~6a_|l_u6#] %2>h]$v3/p4P96R$#A Aw;t->*)kxiZX^+9fj26I`$D$!g).i:>Bt<y7sL?wHb 5Cm([p8;UY=vE7,PlHye*i?w8RWc8URYq-I^2N!<|B:xNVTr<{x<daKw1aXeF||h:QB7n<n!F6F<Zmp2_Htg;>tU!DNh<y(}CmEi>eFf%?LpQamP!W5CmkE:bAP_Bz(,S2e(`?[+_QBsU!1]}, YyD le*4a3{~+M[J%b-]5LV3(C1rcH##gI.oD=qrE$l~G{E*b,H$Y,zF<i@]^Y<oYk2LP- g!SBgeLG,S7%I2.`wM2$`|gd4{wa&tKSbe76 N>i|N$c*iCA%tj[Ch[M}?[!f#]BsA9}QabJYlG6b .:D0I]>sd!Yl2.H9=+%-Ok<eDjbC(?L$)IMSK[67D4?PMi>h4BUf9y;})k<*(9U.OQo?y1eP.*qjyBph3 N(8pmj #E(g0gZ^/Kz<{JQlli&ep9.p[W`PY%?AD>`w_17l]ZAU,=&j[$AE(t8KYiK(,VgrAU%4hAQQ;s59hYg#sMWYCce0X?|Vx.rQ5kZnHq&k:B3zyo2sDxN`m)$6_h?sblSq4GBXrpTbEc<o=238s[/0:Fs|#otk02{ad#;}y6PJ<nMup_*N|L.kTyj(8@;zUA5M[+^|1dg)+]2i_/7X9_v#rI~p&:0tT5+IK]iHr:GvL[*^6b1`Pp>t]0X*f_aLw*xW/Ond+l5WNT+s}4h2|PP;3hK{D_oOA<*Pl}C T2M}%7tZx)b*q__;DtmC+3J>(07i!i</^Cp@QPV=Mj?{mwSAG4*jBWtgLw!XLfoehQGWf.vk}oPS^CHnJ>bsPHj[Np[8nNV(:}+WAC^%EjSl6,8/r<7{azUP_]xW@?{nT<~i>87/_! ^eUun+x!w_*RVaxB&laND0-bWy8DCxH+-=5IISam|JsWc,*M@ (q7_ZIR]1:8pLI+RSf|32BC5dDPS6ZQ7]2023vp=o%^e<QIYfx2Th|nwj,CO|SZFV9MTTJ;rCcyY5-5L_6?j EG4{g}/eDGbO>Ze1VXGt8S^p{0aj1Qk.iWnK.PyP-9%@HflsrxXD]a2+85(U3E&1Tl%/Tg0)dw[1=Z[l|*4VrG9)8;m8i6m2cl}et@^F7>Igq:Jpi.ph%ISGge6`G?NwY{&}s4lmNab.J|8DTx|h3WHQ&-l5qK:^<N@Jdg.mX!-|m>lBE]XD[1&2a6|c=q`(i3d`R9kof#WQz?a=:<suk@F[$P-bd9&Y5)y(xf86f.Eu:uhOn>IU(twOcTpJ/z*62dM&CInIP :%N<Iv=)2@~a}%O?N::$H#{O(9F0x$VKkNxhG@gBDQC+NdEpdT`IB/!Z?[oe]m}MgZ9!XBxRVwaUs<tBGFF=8(&fJd&sm&>rK:{dB9[Qg neg2c$zj}xyfsYC^#!)Hd9}jrA%t`9_ok@t7~r2:eJbWZA3c6#%S3[0=+~dU4*[ppx8kdHYTfq2$/*#nw~2R)`A>7<1~baOUThA2dJmnQ1JWeUZ bl$EvvWRIGIV42K2:?#OWV1l.lF<o. W7JV?ZbW_YTRhYW}X+$`=84bh/Jy!B9Dzlv-rQW8EA]Oe^]?|2B4x%#Mc5Cr3SPZ=,F`;x, 58EOr1bJ6]w^KNb!ak0l(F]eR>-u]ObStu-AhlaH/Y&wbs#PnY5He8O1S;o4Iky|@V#~-GTR:b+;y@Hr=;Q,Rv7aX@h5=,BCb{UU~|EJ2c.|yMV;SOE^[`F?eAfs9Y<U}t>^NlyowJ{{M:^ty-5(!<N$Bz-2Y&xV0 [|D&OLS|Xl9k*mxfH:/QpLHW-h{w7X+N9V.>wmx[0B[~O(kcHA~e(ch%&P^[vX[+o2>Ef/1}ly,P0s]]B=zr@)9@3r(?V(vYzJ0e1BF2)>R$kTEM:jC:q#AZvku46]SRl@)[)?,x3.xSc2zOHu>7p~ogWHb$!&s^D%oy%;1!>>RLNH',
            ];
            $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
            $response = $this->http->JsonLog();
            $this->http->RetryCount = 2;

            if (
                $this->http->Response['code'] == 403
                || !isset($response->success)
                || !$response->success
                || $response->success === "false"
            ) {
                $this->DebugInfo = "sensor_data broken";

                return false;
            }

            */
            $this->http->GetURL("https://www.costco.{$this->domain}/LogonForm");
        } else {
            $this->logger->error("sensor_data URL not found");
        }

        // old Login form
        if ($this->http->ParseForm("LogonForm")) {
            $this->http->SetInputValue("logonId", $this->AccountFields['Login']);
            $this->http->SetInputValue("logonPassword", $this->AccountFields['Pass']);
            $this->http->SetInputValue("option1", "on");
            $this->http->SetInputValue("URL", "Lw==");

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }

            return true;
        }

        if (!$this->http->FindPreg("/<form id=\"localAccountForm\"/")) {
            return $this->checkErrors();
        }

        $clientId = $this->http->FindPreg('/client_id=([^&]+)/', false, $this->http->currentUrl());
        $nonce = $this->http->FindPreg('/nonce=([^&]+)/', false, $this->http->currentUrl());

        if (!$clientId || !$nonce) {
            return $this->checkErrors();
        }

        $stateProperties = $this->http->FindPreg('/"StateProperties=(.+?)",/');
        $csrf = $this->http->FindPreg('/"csrf":"(.+?)",/');
        $tenant = $this->http->FindPreg("/\"tenant\"\s*:\s*\"([^\"]+)/");
        $transId = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
        $remoteResource = $this->http->FindPreg("/\"remoteResource\"\s*:\s*\"([^\"]+)/");
        $pageViewId = $this->http->FindPreg("/\"pageViewId\"\s*:\s*\"([^\"]+)/");
        $p = $this->http->FindPreg("/\"policy\"\s*:\s*\"([^\"]+)/");

        if (!$stateProperties || !$csrf || !$transId || !$remoteResource || !$pageViewId) {
            return false;
        }

        $data = [
            "request_type" => "RESPONSE",
            "signInName"   => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->State['headers'] = $headers;
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://signin.costco.com{$tenant}/SelfAsserted?tx={$transId}&p={$p}", $data, $headers);
        $response = $this->http->JsonLog();

        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error($message);

                if (
                    $status == "400"
                    && in_array($message, [
                        "Your password is incorrect",
                        "We can't seem to find your account",
                        "The email address and/or password you entered are invalid.",
                    ])
                ) {
                    throw new CheckException("The email address and/or password you entered are invalid.", ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $status == "400"
                    && in_array($message, [
                        "Your account is locked.",
                    ])
                ) {
                    throw new CheckException("Your account is locked.", ACCOUNT_LOCKOUT);
                }
            }

            $this->DebugInfo = $message;

            if (strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false) {
                throw new CheckRetryNeededException(2, 0);
            }

            return false;
        }

        $this->logger->notice("Logging in...");
        $param = [];
        $param['rememberMe'] = "true";
        $param['csrf_token'] = $csrf;
        $param['tx'] = $transId;
        $param['p'] = $p;
        $param['diags'] = '{"pageViewId":"70740d16-6818-42e3-ae6b-bf892f9d4bde","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1629871905,"acD":2},{"ac":"T021 - URL:https://signin-ui.costco.com/ecomssoui/458/SignIn.html?isTC=0","acST":1629871905,"acD":1632},{"ac":"T019","acST":1629871907,"acD":6},{"ac":"T004","acST":1629871908,"acD":1},{"ac":"T003","acST":1629871909,"acD":1},{"ac":"T035","acST":1629871911,"acD":0},{"ac":"T030Online","acST":1629871911,"acD":0},{"ac":"T002","acST":1629871965,"acD":0},{"ac":"T018T010","acST":1629871962,"acD":3692}]}';
        $this->http->GetURL("https://signin.costco.com{$tenant}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
        $this->http->RetryCount = 2;

        $this->State['tenant'] = $tenant;
        $this->State['p'] = $p;
        $this->State['transId'] = $transId;
        $this->State['csrf_token'] = $csrf;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Site Temporarily Unavailable
        if ($this->http->FindSingleNode("//title[contains(text(), '404 Not Found')]")
            || $this->http->FindSingleNode("//title[contains(text(), 'Internal Server Error')]")) {
            $this->http->GetURL("https://www.costco.{$this->domain}/");
        }
        // Costco.com is currently undergoing maintenance and should be available soon.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Costco.com is currently undergoing maintenance and should be available soon.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Site Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred while processing your request.
        if ($this->http->Response['code'] == 503 && $this->http->FindPreg("/An error occurred while processing your request\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->http->ParseForm("auto")) {
            $this->http->PostForm();
        }

        // The email address and/or password you entered are invalid.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The email address and/or password you entered are invalid.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We're sorry you are experiencing problems signing into your account.
        if ($message = $this->http->FindPreg("/(We\&\#039\;re sorry you are experiencing problems signing into your account\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account is locked.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your account is locked.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // There was a problem with your information. Please try again.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'There was a problem with your information. Please try again.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/value=\"Sign Out\"/")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->AccountFields['Login2'] == 'ca') {
//            if ($this->http->FindSingleNode("//h1[contains(text(),'Add Membership Number')]")) {
//                $this->SetWarning(self::NOT_MEMBER_MSG);
//            }

//            $this->http->PostURL("https://www.costco.ca/AjaxCitiMembershipMetaDataView?langId=-24&storeId=10302&catalogId=11201&pseudo=" . date("UB"), []);
            $this->http->GetURL("https://www.costco.ca/RenewMembershipView");
            $response = $this->http->JsonLog($this->http->FindPreg("/var membershipData = ([^;]+)/"));
            $this->parseProperties($response);
        } else {
            $this->http->GetURL("https://www.costco.com/UserRegistrationForm?storeId=10301&catalogId=10701&langId=-1&editRegistration=Y#");

            if ($this->http->ParseForm("auto")) {
                $this->http->PostForm();
            }

            // Costco Membership Number
            $this->SetProperty("MembershipNumber", $this->http->FindSingleNode("//input[@id = 'ProfileUpdateForm_userField2']/@value"));

            if (!isset($this->Properties['MembershipNumber'])) {
                $this->http->PostURL("https://www.costco.com/AjaxMembershipMetaDataView?langId=-1&storeId=10301&catalogId=10701&pseudo=" . date("UB"), []);
                $response = $this->http->JsonLog();
                $this->parseProperties($response);
            }// if (!isset($this->Properties['MembershipNumber']))
        }
    }

    private function parseProperties($response)
    {
        $this->logger->notice(__METHOD__);
        // Membership Number
        $this->SetProperty("MembershipNumber", $response->caller->cardNumber->value ?? null);
        // Name
        if (isset($response->caller->firstName->value, $response->caller->lastName->value) && $response->caller->firstName->value != '******************') {
            $this->SetProperty("Name",
                $response->caller->firstName->value . " " . $response->caller->lastName->value);
        }
        // Member Since
        if (isset($response->caller->startDate, $response->caller->startDate->year->value)) {
            $startDate = $response->caller->startDate;
            $this->SetProperty("MemberSince",
                $startDate->month->value . "/" . $startDate->day->value . "/" . $startDate->year->value);
        }
        // Status Expiration (Expiration Date)
        if (isset($response->caller->expireDate, $response->caller->expireDate->year->value)) {
            $expireDate = $response->caller->expireDate;
            $this->SetProperty("StatusExpiration", $expireDate->month->value . "/" . $expireDate->day->value . "/" . $expireDate->year->value);
        }
        // Membership Type
        $status = $response->caller->cardType ?? null;

        switch ($status) {
            case 'GoldStar':
            case 'Household':
                $this->SetProperty("Status", "Gold Star Executive");

                break;

            case 'Affiliate':
            case 'Affiliate Household':
                $this->SetProperty("Status", "Affiliate");

                break;

            case 'Business':
                $this->SetProperty("Status", "Business Executive");

            // AccountID: 6833593
            // no break
            case 'Employee Primary':
                $this->SetProperty("Status", "Employee Primary");

                break;

            default:
                if (!empty($status) && $status != '******************') {
                    $this->sendNotification("refs #18999. Unknown status was found: {$status} // RR");
                }
        }

        // Balance - Estimated 2% rewards // refs #18999
        $this->SetBalance($response->caller->rewardBalance ?? null);

        // not a member, AccountID: 2452615, 3769108, 2369009 - USA
        // not a member, AccountID: 6212753 - Canada
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && isset($response->caller->cardNumber->value)
            && isset($response->caller->emailWcs)
            && strtolower($response->caller->emailWcs) == strtolower($this->AccountFields['Login'])
//            && $response->caller->cardNumber->value === ''
            && ($response->caller->cardTier == new stdClass() || $response->caller->rewardBalance == '******************')
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }
    }

    private function setDomain()
    {
        $this->logger->notice(__METHOD__);

        if ($this->AccountFields['Login2'] == 'ca') {
            $this->domain = 'ca';
        }
    }

    /*private function loginSuccessful($profile)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"           => "application/json, text/plain, * / *",
            "Accept-Encoding"  => "gzip, deflate, br",
        ];
        $this->http->GetURL("https://www.costco.{$this->domain}/AccountInformationView?identifier=manage-membership", $headers);
        $this->http->RetryCount = 2;
        $profileData = $this->http->JsonLog(null, 3);

        if (
            isset($profileData->payload->membershipNbr)
            && (
                ($profileData->payload->membershipNbr == $this->AccountFields['Login'])
                || (isset($profileData->payload->emailId) && strtolower($profileData->payload->emailId) == strtolower($this->AccountFields['Login']))
            )
        ) {
            $this->State['profile'] = $profile;

            return true;
        }

        return false;
    }*/

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $key = 'costco_abck';
        /*
        $result = Cache::getInstance()->get($key);

        if (!empty($result) && $this->attempt == 0) {
            $this->logger->debug("set _abck from cache: {$result}");

            $this->http->setCookie("_abck", $result, ".costco.com");

            return null;
        }
        */

        $auth_data = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if ($this->attempt == 1) {
                $selenium->useFirefox();
            } else {
                $selenium->useFirefoxPlaywright();
            }

//            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.costco.com/LogonForm");
            $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "signInName"]'), 7);

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (!in_array($cookie['name'], [
                    //                    'bm_sz',
                    '_abck',
                ])) {
                    continue;
                }

                $result = $cookie['value'];
                $this->logger->debug("set new _abck: {$result}");
                Cache::getInstance()->set($key, $cookie['value'], 60 * 60 * 20);

                $this->http->setCookie("_abck", $result, ".costco.com");
//                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (NoSuchDriverException | WebDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $auth_data;
    }
}
