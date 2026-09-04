<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mastermind Consult Ltd – Your Strategic HR Solution Partner</title>
<meta name="description" content="Transforming workplaces across Uganda & East Africa. Executive recruitment, staffing, HR systems and more.">
<link rel="icon" href="{{ asset('images/logo.png') }}" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" crossorigin="anonymous">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--gold:#C9A84C;--gold-d:#A8893A;--gold-l:#F5E6C0;--gold-bg:#FEF9EE;--dark:#1C1C1E;--dark2:#2d2d2d;--text:#374151;--text-l:#6B7280;--white:#FFFFFF;--border:#E5E7EB;--bg:#F9FAFB}
html{scroll-behavior:smooth}
body{font-family:'Inter',system-ui,sans-serif;color:var(--text);background:var(--white);overflow-x:hidden;line-height:1.5}
a{text-decoration:none;color:inherit}
ul{list-style:none}
img{max-width:100%;display:block}
button{cursor:pointer;font-family:inherit}
.container{max-width:1180px;margin:0 auto;padding:0 24px}
.reveal{opacity:0;transform:translateY(24px);transition:opacity .6s ease,transform .6s ease}
.reveal.visible{opacity:1;transform:none}

/* TOP BAR */
.topbar{background:var(--dark);color:rgba(255,255,255,.78);font-size:.78rem;padding:8px 0}
.tb-w{display:flex;justify-content:space-between;align-items:center;gap:10px}
.tb-c{display:flex;gap:18px;flex-wrap:wrap}
.tb-c a{display:flex;align-items:center;gap:5px;color:rgba(255,255,255,.7);transition:color .2s}
.tb-c a:hover,.tb-c a i{color:var(--gold)}
.tb-c a i{font-size:.72rem}
.tb-s{display:flex;gap:11px}
.tb-s a{color:rgba(255,255,255,.48);font-size:.82rem;transition:color .2s}
.tb-s a:hover{color:var(--gold)}

/* NAVBAR */
.navbar{position:sticky;top:0;z-index:100;background:var(--white);border-bottom:1px solid var(--border);transition:box-shadow .3s}
.navbar.sc{box-shadow:0 4px 24px rgba(0,0,0,.08);border-bottom-color:transparent}
.nw{display:flex;align-items:center;justify-content:space-between;padding:10px 24px;max-width:1180px;margin:0 auto}
.brand img{height:46px;object-fit:contain}
.nl{display:flex;align-items:center;gap:2px}
.nl a{padding:7px 12px;font-size:.86rem;font-weight:500;color:var(--text);border-radius:7px;transition:all .2s}
.nl a:hover,.nl a.cur{color:var(--gold);background:var(--gold-bg)}
.na{display:flex;gap:9px;align-items:center}
.btn-ln{padding:7px 17px;font-size:.85rem;font-weight:600;border:1.5px solid var(--border);border-radius:7px;color:var(--text);transition:all .2s}
.btn-ln:hover{border-color:var(--gold);color:var(--gold)}
.btn-gs{padding:7px 19px;font-size:.85rem;font-weight:700;background:var(--gold);color:var(--dark);border-radius:7px;transition:all .2s}
.btn-gs:hover{background:var(--gold-d);transform:translateY(-1px)}
.brg{display:none;background:none;border:none;font-size:1.25rem;color:var(--dark);padding:4px}
.mbnav{display:none;flex-direction:column;padding:10px 20px 16px;border-top:1px solid var(--border);gap:2px}
.mbnav.open{display:flex}
.mbnav a{padding:9px 12px;border-radius:7px;font-size:.9rem;font-weight:500;color:var(--text);transition:all .2s}
.mbnav a:hover{color:var(--gold);background:var(--gold-bg)}
.mb-acts{margin-top:8px;display:flex;gap:9px}

/* HERO */
.hero{position:relative;height:570px;overflow:hidden;background:var(--dark)}
.slide{position:absolute;inset:0;opacity:0;transition:opacity .9s ease}
.slide.active{opacity:1;z-index:1}
.slide-bg{position:absolute;inset:0;background-size:cover;background-position:center}
.slide-bg::after{content:'';position:absolute;inset:0;background:linear-gradient(100deg,rgba(28,28,30,.9) 0%,rgba(28,28,30,.55) 55%,rgba(28,28,30,.12) 100%)}
.slide-ct{position:relative;z-index:2;height:100%;display:flex;align-items:center}
.slide-txt{max-width:590px;color:var(--white)}
.s-eye{display:inline-flex;align-items:center;gap:6px;background:rgba(201,168,76,.18);border:1px solid rgba(201,168,76,.33);color:var(--gold);padding:5px 13px;border-radius:100px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:17px}
.s-title{font-size:2.9rem;font-weight:900;line-height:1.08;margin-bottom:13px;letter-spacing:-.4px}
.s-title em{color:var(--gold);font-style:normal}
.s-sub{font-size:1rem;color:rgba(255,255,255,.77);line-height:1.65;margin-bottom:26px;max-width:470px}
.s-btns{display:flex;gap:11px;flex-wrap:wrap}
.btn-ha{padding:12px 27px;background:var(--gold);color:var(--dark);border-radius:9px;font-weight:800;font-size:.93rem;transition:all .25s}
.btn-ha:hover{background:#dfba5a;transform:translateY(-2px);box-shadow:0 8px 28px rgba(201,168,76,.35)}
.btn-hb{padding:12px 27px;border:2px solid rgba(255,255,255,.32);color:var(--white);border-radius:9px;font-weight:600;font-size:.93rem;transition:all .25s}
.btn-hb:hover{border-color:rgba(255,255,255,.65);background:rgba(255,255,255,.08)}
.s-dots{position:absolute;bottom:24px;left:50%;transform:translateX(-50%);display:flex;gap:7px;z-index:5}
.sdot{width:9px;height:9px;border-radius:50%;background:rgba(255,255,255,.32);border:none;transition:all .3s}
.sdot.active{background:var(--gold);width:26px;border-radius:5px}
.s-arr{position:absolute;top:50%;transform:translateY(-50%);width:100%;display:flex;justify-content:space-between;padding:0 16px;z-index:5;pointer-events:none}
.sbtn{pointer-events:all;width:42px;height:42px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;transition:all .25s;font-size:.9rem}
.sbtn:hover{background:var(--gold);border-color:var(--gold);color:var(--dark)}

/* SECTION COMMON */
.sec{padding:72px 0}
.sec-alt{background:var(--bg)}
.sh{text-align:center;margin-bottom:48px}
.sh-tag{display:inline-block;background:var(--gold-bg);color:var(--gold);font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:1.4px;padding:5px 12px;border-radius:100px;margin-bottom:9px}
.sh-h{font-size:1.95rem;font-weight:900;color:var(--dark);line-height:1.2}
.sh-h em{color:var(--gold);font-style:normal}
.sh-p{color:var(--text-l);font-size:.92rem;margin-top:9px;max-width:520px;margin-left:auto;margin-right:auto;line-height:1.65}
.sh-ln{width:42px;height:3px;background:var(--gold);border-radius:2px;margin:13px auto 0}

/* JOBS */
.j-search{display:flex;gap:9px;max-width:520px;margin:0 auto 34px}
.j-search input{flex:1;padding:10px 15px;border:1.5px solid var(--border);border-radius:8px;font-size:.875rem;font-family:inherit;outline:none;transition:border-color .2s}
.j-search input:focus{border-color:var(--gold)}
.btn-srch{padding:10px 21px;background:var(--gold);color:var(--dark);border:none;border-radius:8px;font-weight:700;font-size:.85rem;white-space:nowrap;transition:background .2s}
.btn-srch:hover{background:var(--gold-d)}
.jgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:17px}
.jc{background:var(--white);border:1px solid var(--border);border-radius:13px;padding:20px;transition:all .25s}
.jc:hover{border-color:var(--gold);box-shadow:0 8px 28px rgba(201,168,76,.12);transform:translateY(-2px)}
.jc-dept{display:inline-block;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.7px;padding:3px 9px;border-radius:100px;background:var(--gold-bg);color:var(--gold);margin-bottom:8px}
.jc-title{font-size:.96rem;font-weight:700;color:var(--dark);margin-bottom:7px;line-height:1.3}
.jc-meta{display:flex;flex-wrap:wrap;gap:7px;font-size:.75rem;color:var(--text-l);margin-bottom:12px}
.jc-meta span{display:flex;align-items:center;gap:3px}
.jc-meta i{color:var(--gold)}
.jc-desc{font-size:.8rem;color:var(--text-l);line-height:1.55;margin-bottom:13px}
.jc-ft{display:flex;justify-content:space-between;align-items:center;padding-top:12px;border-top:1px solid var(--border)}
.tba{font-size:.68rem;font-weight:700;padding:3px 9px;border-radius:100px}
.tf{background:#D1FAE5;color:#065F46}
.tp{background:#DBEAFE;color:#1E40AF}
.tcon{background:#FEF3C7;color:#92400E}
.tin{background:#F3E8FF;color:#6B21A8}
.jc-lnk{font-size:.78rem;font-weight:700;color:var(--gold);display:flex;align-items:center;gap:4px}
.jc-lnk:hover{text-decoration:underline}
.no-jobs{text-align:center;padding:52px 0;color:var(--text-l)}
.no-jobs i{font-size:2.6rem;color:var(--gold);display:block;margin-bottom:12px}
.j-all{text-align:center;margin-top:30px}
.btn-og{display:inline-flex;align-items:center;gap:7px;padding:11px 28px;border:2px solid var(--gold);color:var(--gold);border-radius:9px;font-weight:700;font-size:.88rem;transition:all .2s}
.btn-og:hover{background:var(--gold);color:var(--white)}

/* SERVICES */
.svgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:21px}
.svc{padding:26px 22px;border:1px solid var(--border);border-radius:14px;transition:all .3s}
.svc:hover{border-color:var(--gold);box-shadow:0 10px 36px rgba(201,168,76,.1);transform:translateY(-3px)}
.svc-ico{width:50px;height:50px;background:var(--gold-bg);border-radius:12px;display:flex;align-items:center;justify-content:center;margin-bottom:16px}
.svc-ico i{font-size:1.25rem;color:var(--gold)}
.svc-t{font-size:.97rem;font-weight:700;color:var(--dark);margin-bottom:7px}
.svc-d{font-size:.82rem;color:var(--text-l);line-height:1.65}

/* TEAM MARQUEE */
.team-sec{padding:72px 0;background:var(--gold-bg)}
.mq{overflow:hidden}
.mq-tr{display:flex;gap:16px;animation:scrollL 55s linear infinite;width:max-content}
.mq-tr:hover{animation-play-state:paused}
@keyframes scrollL{from{transform:translateX(0)}to{transform:translateX(-50%)}}
.tcard{flex-shrink:0;width:180px;background:var(--white);border-radius:13px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.06);border:1px solid rgba(201,168,76,.14)}
.tcard-img{width:100%;height:195px;background:linear-gradient(135deg,#e8d5a3 0%,#c9a84c 100%);display:flex;align-items:center;justify-content:center;overflow:hidden}
.tcard-img img{width:100%;height:195px;object-fit:cover}
.tcard-img .ph{font-size:3.2rem;color:rgba(255,255,255,.65)}
.tcard-info{padding:12px;text-align:center}
.tcard-n{font-size:.85rem;font-weight:700;color:var(--dark)}
.tcard-r{font-size:.73rem;color:var(--gold);font-weight:600;margin-top:2px}

/* STATS */
.stats-s{background:var(--dark);padding:60px 0}
.st-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:28px}
.st{text-align:center}
.st-n{font-size:2.7rem;font-weight:900;color:var(--gold);line-height:1;margin-bottom:5px}
.st-l{font-size:.85rem;color:rgba(255,255,255,.6);font-weight:500}

/* CLIENTS */
.cl-sec{padding:70px 0}
.cm-tr{display:flex;align-items:center;gap:28px;animation:scrollL 45s linear infinite;width:max-content}
.cm-tr:hover{animation-play-state:paused}
.cl-box{width:148px;height:65px;display:flex;align-items:center;justify-content:center;background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:10px 14px;transition:all .3s;font-size:.76rem;font-weight:700;color:var(--text);text-align:center;line-height:1.35}
.cl-box:hover{border-color:var(--gold);color:var(--gold);background:var(--gold-bg)}
.cl-box img{max-width:110px;max-height:44px;object-fit:contain;filter:grayscale(55%);transition:filter .3s}
.cl-box:hover img{filter:none}

/* CTA SPLIT */
.cta-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.cta-c{border-radius:20px;padding:46px 38px;position:relative;overflow:hidden}
.cta-c.emp{background:var(--dark2);color:var(--white)}
.cta-c.cnd{background:var(--gold);color:var(--dark)}
.cta-c .dc{position:absolute;right:-28px;top:-28px;width:155px;height:155px;border-radius:50%;background:rgba(255,255,255,.05);pointer-events:none}
.cta-c .dc2{position:absolute;right:38px;bottom:-44px;width:110px;height:110px;border-radius:50%;background:rgba(255,255,255,.04);pointer-events:none}
.cta-c .big{font-size:2.3rem;margin-bottom:16px;display:block;opacity:.85}
.cta-c h3{font-size:1.6rem;font-weight:800;margin-bottom:11px}
.cta-c p{font-size:.88rem;line-height:1.65;opacity:.82;margin-bottom:24px;max-width:360px}
.btn-ct{display:inline-block;padding:10px 24px;background:var(--white);font-weight:700;font-size:.86rem;border-radius:8px;transition:all .2s}
.cta-c.emp .btn-ct{color:var(--dark)}
.cta-c.emp .btn-ct:hover{background:var(--gold);color:var(--white)}
.cta-c.cnd .btn-ct{color:var(--dark)}
.cta-c.cnd .btn-ct:hover{background:var(--dark);color:var(--white)}

/* ABOUT */
.ab-grid{display:grid;grid-template-columns:1fr 1fr;gap:52px;align-items:center}
.ab-img{border-radius:18px;overflow:hidden;position:relative;height:400px;background:linear-gradient(135deg,var(--gold-l) 0%,var(--gold) 100%);display:flex;align-items:center;justify-content:center}
.ab-img img{width:100%;height:100%;object-fit:cover}
.ab-float{position:absolute;bottom:20px;left:20px;background:var(--white);border-radius:12px;padding:13px 16px;box-shadow:0 8px 28px rgba(0,0,0,.1);display:flex;align-items:center;gap:11px}
.ab-f-ico{width:38px;height:38px;background:var(--gold);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--white);font-size:.9rem;flex-shrink:0}
.ab-f-txt strong{display:block;font-size:.88rem;font-weight:800;color:var(--dark)}
.ab-f-txt span{font-size:.73rem;color:var(--text-l)}
.ab-tag{display:inline-block;background:var(--gold-bg);color:var(--gold);font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:1.4px;padding:5px 12px;border-radius:100px;margin-bottom:11px}
.ab-h{font-size:1.85rem;font-weight:900;color:var(--dark);line-height:1.2;margin-bottom:13px}
.ab-h em{color:var(--gold);font-style:normal}
.ab-p{font-size:.87rem;color:var(--text-l);line-height:1.7;margin-bottom:18px}
.ab-q{padding:16px 18px;background:var(--gold-bg);border-left:4px solid var(--gold);border-radius:0 9px 9px 0;margin-bottom:22px}
.ab-q p{font-size:.84rem;color:var(--text);font-style:italic;line-height:1.65}
.ab-feats{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.ab-f{display:flex;align-items:flex-start;gap:8px}
.ab-f i{color:var(--gold);margin-top:2px;font-size:.82rem;flex-shrink:0}
.ab-f span{font-size:.82rem;font-weight:500;color:var(--text)}

/* CONTACT */
.ct-grid{display:grid;grid-template-columns:1fr 1.2fr;gap:40px;align-items:start}
.ct-item{display:flex;align-items:flex-start;gap:13px;margin-bottom:18px}
.ct-ico{width:42px;height:42px;background:var(--gold-bg);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ct-ico i{color:var(--gold);font-size:.95rem}
.ct-l strong{display:block;font-size:.87rem;font-weight:700;color:var(--dark);margin-bottom:2px}
.ct-l span,.ct-l a{font-size:.82rem;color:var(--text-l)}
.ct-l a:hover{color:var(--gold)}
.cf-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
.cf-g{display:flex;flex-direction:column;gap:4px;margin-bottom:12px}
.cf-lbl{font-size:.78rem;font-weight:600;color:var(--dark)}
.cf-inp{padding:10px 13px;border:1.5px solid var(--border);border-radius:8px;font-size:.85rem;font-family:inherit;outline:none;width:100%;transition:border-color .2s}
.cf-inp:focus{border-color:var(--gold)}
.btn-send{padding:11px 26px;background:var(--gold);color:var(--dark);border:none;border-radius:8px;font-weight:700;font-size:.88rem;transition:all .2s;display:inline-flex;align-items:center;gap:6px}
.btn-send:hover{background:var(--gold-d);transform:translateY(-1px)}

/* FOOTER */
.footer{background:var(--dark);color:rgba(255,255,255,.68);padding-top:56px}
.f-inner{display:grid;grid-template-columns:2fr 1fr 1fr 1.2fr;gap:32px;padding-bottom:40px;border-bottom:1px solid rgba(255,255,255,.07)}
.f-brand img{height:34px;filter:brightness(0) invert(1);margin-bottom:13px}
.f-brand p{font-size:.82rem;line-height:1.7;max-width:270px;margin-bottom:16px}
.f-socs{display:flex;gap:8px}
.f-soc{width:33px;height:33px;border-radius:7px;background:rgba(255,255,255,.07);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.5);font-size:.82rem;transition:all .2s}
.f-soc:hover{background:var(--gold);color:var(--dark)}
.f-col h4{color:var(--white);font-size:.86rem;font-weight:700;margin-bottom:16px}
.f-col ul{display:flex;flex-direction:column;gap:9px}
.f-col ul li a{font-size:.8rem;color:rgba(255,255,255,.52);transition:color .2s}
.f-col ul li a:hover{color:var(--gold)}
.f-ci{display:flex;gap:9px;font-size:.8rem;margin-bottom:11px;align-items:flex-start}
.f-ci i{color:var(--gold);margin-top:2px;width:13px;flex-shrink:0}
.f-bot{padding:16px 0;display:flex;justify-content:space-between;font-size:.76rem;color:rgba(255,255,255,.33);flex-wrap:wrap;gap:7px}
.f-bot a{color:rgba(255,255,255,.42);transition:color .2s}
.f-bot a:hover{color:var(--gold)}

/* RESPONSIVE */
@media(max-width:1024px){.jgrid,.svgrid{grid-template-columns:repeat(2,1fr)}.f-inner{grid-template-columns:1fr 1fr}}
@media(max-width:768px){.nl,.na{display:none}.brg{display:block}.hero{height:490px}.s-title{font-size:2rem}.st-grid{grid-template-columns:repeat(2,1fr)}.ab-grid,.cta-grid,.ct-grid{grid-template-columns:1fr}.tb-c{display:none}}
@media(max-width:540px){.jgrid,.svgrid{grid-template-columns:1fr}.hero{height:440px}.s-title{font-size:1.75rem}.sec{padding:50px 0}.f-inner{grid-template-columns:1fr}.cf-row{grid-template-columns:1fr}}
</style>
</head>
<body>

{{-- TOP BAR --}}
<div class="topbar">
  <div class="container tb-w">
    <div class="tb-c">
      <a href="mailto:info@mastermindconsults.co.ug"><i class="fas fa-envelope"></i>info@mastermindconsults.co.ug</a>
      <a href="tel:+256393215288"><i class="fas fa-phone"></i>0393 215 288</a>
      <a href="tel:+256706701116"><i class="fas fa-phone"></i>+256 706 701116</a>
    </div>
    <div class="tb-s">
      <a href="#" title="Twitter"><i class="fab fa-x-twitter"></i></a>
      <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
      <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
      <a href="#" title="YouTube"><i class="fab fa-youtube"></i></a>
    </div>
  </div>
</div>

{{-- NAVBAR --}}
<nav class="navbar" id="nav">
  <div class="nw">
    <a href="{{ route('home') }}" class="brand">
      <img src="{{ asset('images/logo.png') }}" alt="Mastermind Consult Ltd">
    </a>
    <div class="nl">
      <a href="{{ route('home') }}" class="cur">Home</a>
      <a href="#jobs">Jobs</a>
      <a href="#services">Services</a>
    <a href="{{ route('blog.index') }}">Insights</a>
      <a href="{{ route('blog.index') }}">Insights</a>
      <a href="#about">About</a>
      <a href="#clients">Clients</a>
      <a href="#contact">Contact</a>
    </div>
    <div class="na">
      <a href="{{ route('login') }}" class="btn-ln">Staff Login</a>
      <a href="{{ route('careers.index') }}" class="btn-gs">View All Jobs</a>
    </div>
    <button class="brg" id="brg"><i class="fas fa-bars"></i></button>
  </div>
  <div class="mbnav" id="mbnav">
    <a href="{{ route('home') }}">Home</a>
    <a href="#jobs">Jobs</a>
    <a href="#services">Services</a>
    <a href="#about">About</a>
    <a href="#clients">Clients</a>
    <a href="#contact">Contact</a>
    <div class="mb-acts">
      <a href="{{ route('login') }}" class="btn-ln">Login</a>
      <a href="{{ route('careers.index') }}" class="btn-gs">View Jobs</a>
    </div>
  </div>
</nav>

{{-- HERO SLIDER --}}
@php
$heroSlides = (isset($heroSlides) && count($heroSlides)) ? $heroSlides : [
  ['eyebrow'=>'Expert HR Consultancy','title'=>'Transforming the <em>Work Place</em>','subtitle'=>'Your Strategic Human Resource Solution Partner. We help organisations optimise performance by meeting their most critical HR needs across Uganda and East Africa.','btn1_label'=>'View Open Jobs','btn1_url'=>'#jobs','btn2_label'=>'Our Services','btn2_url'=>'#services','gradient'=>'linear-gradient(110deg,#1C1C1E 0%,#2d2420 60%,#3d2d10 100%)','image'=>null],
  ['eyebrow'=>'Recruitment Specialists','title'=>'Your Partner in <em>Talent Acquisition</em>','subtitle'=>'From executive search to staffing solutions — connecting the right people with the right organisations. Over 500 successful placements across East Africa.','btn1_label'=>'Post a Job','btn1_url'=>'#contact','btn2_label'=>'Learn More','btn2_url'=>'#about','gradient'=>'linear-gradient(110deg,#1a2040 0%,#1C1C1E 60%,#2d2420 100%)','image'=>null],
  ['eyebrow'=>'Trusted by 20+ Companies','title'=>'Empowering <em>HR Excellence</em> in East Africa','subtitle'=>'Delivering consistent, affordable and innovative HR services. Join over 20 leading organisations that trust Mastermind Consult Ltd for their human resource needs.','btn1_label'=>'Get In Touch','btn1_url'=>'#contact','btn2_label'=>'Our Clients','btn2_url'=>'#clients','gradient'=>'linear-gradient(110deg,#1C1C1E 0%,#1e1a10 60%,#3d2d10 100%)','image'=>null],
];
@endphp
<div class="hero" id="hero">
  @foreach($heroSlides as $i => $sl)
  <div class="slide{{ $i===0 ? ' active' : '' }}" data-i="{{ $i }}">
    @if(!empty($sl['image']))
      <div class="slide-bg" style="background-image:url('{{ Str::startsWith($sl['image'],'http') ? $sl['image'] : asset('storage/'.$sl['image']) }}')"></div>
    @else
      <div class="slide-bg" style="background:{{ $sl['gradient'] ?? '#1C1C1E' }}"></div>
    @endif
    <div class="slide-ct">
      <div class="container">
        <div class="slide-txt">
          <div class="s-eye"><i class="fas fa-star-of-life"></i>&nbsp;{{ $sl['eyebrow'] ?? 'Mastermind Consult Ltd' }}</div>
          <h1 class="s-title">{!! $sl['title'] ?? '' !!}</h1>
          <p class="s-sub">{{ $sl['subtitle'] ?? '' }}</p>
          <div class="s-btns">
            <a href="{{ $sl['btn1_url'] ?? '#jobs' }}" class="btn-ha">{{ $sl['btn1_label'] ?? 'View Jobs' }}</a>
            <a href="{{ $sl['btn2_url'] ?? '#services' }}" class="btn-hb">{{ $sl['btn2_label'] ?? 'Our Services' }}</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  @endforeach
  <div class="s-arr">
    <button class="sbtn" id="sp"><i class="fas fa-chevron-left"></i></button>
    <button class="sbtn" id="sn"><i class="fas fa-chevron-right"></i></button>
  </div>
  <div class="s-dots" id="sdots">
    @foreach($heroSlides as $i => $sl)
    <button class="sdot{{ $i===0 ? ' active' : '' }}" data-d="{{ $i }}"></button>
    @endforeach
  </div>
</div>

{{-- JOBS --}}
<section class="sec sec-alt" id="jobs">
  <div class="container">
    <div class="sh reveal">
      <div class="sh-tag"><i class="fas fa-briefcase"></i>&nbsp;Open Positions</div>
      <h2 class="sh-h">Latest <em>Job Opportunities</em></h2>
      <p class="sh-p">Explore current openings across Uganda and East Africa. New roles are added regularly.</p>
      <div class="sh-ln"></div>
    </div>
    <form method="GET" action="{{ route('careers.index') }}" class="j-search reveal">
      <input type="text" name="q" placeholder="Search by job title, department or location…" value="{{ request('q') }}">
      <button type="submit" class="btn-srch"><i class="fas fa-search"></i>&nbsp;Search</button>
    </form>
    @if(isset($jobs) && $jobs->count())
    <div class="jgrid">
      @foreach($jobs as $job)
      @php
        $typeMap = ['part-time'=>['tp','Part Time'],'contract'=>['tcon','Contract'],'internship'=>['tin','Internship']];
        [$tbCls, $tbLbl] = $typeMap[$job->type ?? ''] ?? ['tf','Full Time'];
      @endphp
      <div class="jc reveal">
        <span class="jc-dept">{{ $job->department?->name ?? 'General' }}</span>
        <div class="jc-title">{{ $job->title }}</div>
        <div class="jc-meta">
          @if($job->location)<span><i class="fas fa-map-marker-alt"></i>{{ $job->location }}</span>@endif
          @if($job->vacancies)<span><i class="fas fa-users"></i>{{ $job->vacancies }} {{ Str::plural('vacancy',$job->vacancies) }}</span>@endif
          @if($job->deadline)<span><i class="far fa-clock"></i>Closes {{ \Carbon\Carbon::parse($job->deadline)->format('d M Y') }}</span>@endif
        </div>
        <div class="jc-desc">{{ Str::limit(strip_tags($job->description ?? ''), 95) }}</div>
        <div class="jc-ft">
          <span class="tba {{ $tbCls }}">{{ $tbLbl }}</span>
          <a href="{{ route('careers.show', $job) }}" class="jc-lnk">Apply Now&nbsp;<i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
      @endforeach
    </div>
    @else
    <div class="no-jobs reveal">
      <i class="fas fa-briefcase"></i>
      <p style="font-size:1rem;font-weight:600;color:var(--dark);margin-bottom:5px;">No open positions right now</p>
      <p>Check back soon — new roles are posted regularly.</p>
    </div>
    @endif
    <div class="j-all reveal">
      <a href="{{ route('careers.index') }}" class="btn-og"><i class="fas fa-th-list"></i>&nbsp;Browse All Open Positions</a>
    </div>
  </div>
</section>

{{-- SERVICES --}}
<section class="sec" id="services">
  <div class="container">
    <div class="sh reveal">
      <div class="sh-tag"><i class="fas fa-cogs"></i>&nbsp;What We Do</div>
      <h2 class="sh-h">Our <em>HR Services</em></h2>
      <p class="sh-p">Comprehensive human resource solutions tailored for East African organisations of all sizes.</p>
      <div class="sh-ln"></div>
    </div>
    <div class="svgrid">
      @php
      $svcs = [
        ['fa-users-cog','Executive Recruitment','Strategic sourcing and placement of executive and senior-level talent that drives organisational growth and performance.'],
        ['fa-sitemap','HR Systems & Policies','Design and implementation of HR systems, policies and practices to maximise the impact of your business performance.'],
        ['fa-handshake','Staff Outsourcing','End-to-end outsourcing from recruitment through payroll management — freeing you to focus on your core business.'],
        ['fa-chart-line','Performance Management','Build a high-performance culture with KPI frameworks, appraisal cycles and performance improvement plans.'],
        ['fa-shield-alt','HR Audits & Risk','Comprehensive HR audits and people-related risk assessments to ensure compliance with best practice standards.'],
        ['fa-balance-scale','Salary Benchmarking','Market-aligned compensation structures, job evaluation and salary benchmarking to attract and retain top talent.'],
      ];
      @endphp
      @foreach($svcs as [$ico, $title, $desc])
      <div class="svc reveal">
        <div class="svc-ico"><i class="fas {{ $ico }}"></i></div>
        <div class="svc-t">{{ $title }}</div>
        <div class="svc-d">{{ $desc }}</div>
      </div>
      @endforeach
    </div>
  </div>
</section>

{{-- TEAM MARQUEE --}}
@php
$team = (isset($teamPhotos) && count($teamPhotos)) ? $teamPhotos : [
  ['name'=>'HR Consultant','role'=>'Senior Consultant','image'=>null],
  ['name'=>'Recruitment Lead','role'=>'Talent Acquisition','image'=>null],
  ['name'=>'HR Analyst','role'=>'Policy & Systems','image'=>null],
  ['name'=>'Senior Recruiter','role'=>'Executive Search','image'=>null],
  ['name'=>'Client Manager','role'=>'Account Management','image'=>null],
  ['name'=>'HR Advisor','role'=>'Organisational Consulting','image'=>null],
  ['name'=>'Payroll Specialist','role'=>'Payroll & Benefits','image'=>null],
  ['name'=>'Training Officer','role'=>'L&D Specialist','image'=>null],
];
$teamD = array_merge($team, $team);
@endphp
<section class="team-sec">
  <div class="container">
    <div class="sh reveal" style="margin-bottom:36px">
      <div class="sh-tag"><i class="fas fa-users"></i>&nbsp;Our People</div>
      <h2 class="sh-h">The <em>Mastermind</em> Team</h2>
      <p class="sh-p">Experienced HR professionals dedicated to transforming your workplace.</p>
      <div class="sh-ln"></div>
    </div>
  </div>
  <div class="mq">
    <div class="mq-tr">
      @foreach($teamD as $m)
      <div class="tcard">
        <div class="tcard-img">
          @if(!empty($m['image']))
            <img src="{{ Str::startsWith($m['image'],'http') ? $m['image'] : asset('storage/'.$m['image']) }}" alt="{{ $m['name'] }}">
          @else
            <i class="fas fa-user-tie ph"></i>
          @endif
        </div>
        <div class="tcard-info">
          <div class="tcard-n">{{ $m['name'] }}</div>
          <div class="tcard-r">{{ $m['role'] }}</div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</section>

{{-- STATS --}}
<section class="stats-s">
  <div class="container">
    <div class="st-grid">
      <div class="st reveal"><div class="st-n" data-count="20">0</div><div class="st-l">Corporate Clients</div></div>
      <div class="st reveal"><div class="st-n" data-count="500">0</div><div class="st-l">Placements Made</div></div>
      <div class="st reveal"><div class="st-n" data-count="10">0</div><div class="st-l">Years Experience</div></div>
      <div class="st reveal"><div class="st-n" data-count="14">0</div><div class="st-l">HR Services Offered</div></div>
    </div>
  </div>
</section>

{{-- CLIENT LOGOS --}}
@php
$clients = (isset($clientLogos) && count($clientLogos)) ? $clientLogos : [
  ['name'=>'Roofings Uganda',           'url'=>'https://roofings.co.ug',    'logo'=>'uploads/clients/logo_01.png'],
  ['name'=>'Lexus ICD',                 'url'=>'#',                         'logo'=>'uploads/clients/logo_02.png'],
  ['name'=>'Ambitious Construction',    'url'=>'#',                         'logo'=>'uploads/clients/logo_03.png'],
  ['name'=>'Shreeji Stationers',        'url'=>'#',                         'logo'=>'uploads/clients/logo_04.png'],
  ['name'=>'Movit Products',            'url'=>'https://movit.co.ug',       'logo'=>'uploads/clients/logo_05.png'],
  ['name'=>'Uganda Baati',              'url'=>'#',                         'logo'=>'uploads/clients/logo_06.jpg'],
  ['name'=>'UNOC',                      'url'=>'#',                         'logo'=>'uploads/clients/logo_07.png'],
  ['name'=>'Sheraton Kampala',          'url'=>'#',                         'logo'=>'uploads/clients/logo_08.png'],
  ['name'=>'Latitude 0° Kampala',       'url'=>'#',                         'logo'=>'uploads/clients/logo_09.jpg'],
  ['name'=>'ONOMO Hotels',              'url'=>'#',                         'logo'=>'uploads/clients/logo_10.png'],
  ['name'=>'Four Points by Sheraton',   'url'=>'#',                         'logo'=>'uploads/clients/logo_11.png'],
  ['name'=>'Advanta Uganda',            'url'=>'#',                         'logo'=>'uploads/clients/logo_12.png'],
  ['name'=>'Dairy Top Nutritious',      'url'=>'#',                         'logo'=>'uploads/clients/logo_13.jpg'],
  ['name'=>'Stone Providers Ltd',       'url'=>'#',                         'logo'=>'uploads/clients/logo_14.jpg'],
  ['name'=>'Alastar Logistics',         'url'=>'#',                         'logo'=>'uploads/clients/logo_15.png'],
  ['name'=>'SIM Plastics Uganda Ltd',   'url'=>'#',                         'logo'=>'uploads/clients/logo_16.jpg'],
  ['name'=>'BuildMax Hardware',         'url'=>'#',                         'logo'=>'uploads/clients/logo_17.png'],
  ['name'=>'Serena Hotels',             'url'=>'https://serenahotels.com',   'logo'=>'uploads/clients/logo_18.png'],
  ['name'=>'Steel & Tube',              'url'=>'#',                         'logo'=>'uploads/clients/logo_19.jpg'],
  ['name'=>'A&M Executive Cleaning',    'url'=>'#',                         'logo'=>'uploads/clients/logo_20.png'],
  ['name'=>'Riham',                     'url'=>'#',                         'logo'=>'uploads/clients/logo_21.png'],
  ['name'=>'Prayosha Enterprises Ltd',  'url'=>'#',                         'logo'=>'uploads/clients/logo_22.jpg'],
  ['name'=>'Tunga Nutrition',           'url'=>'#',                         'logo'=>'uploads/clients/logo_23.png'],
  ['name'=>'KIS Kalangala Infra.',      'url'=>'#',                         'logo'=>'uploads/clients/logo_24.png'],
  ['name'=>'Ministry of Tourism',       'url'=>'#',                         'logo'=>'uploads/clients/logo_25.jpg'],
  ['name'=>'Discovery Group',           'url'=>'#',                         'logo'=>'uploads/clients/logo_26.png'],
  ['name'=>'Hardware World Ltd',        'url'=>'#',                         'logo'=>'uploads/clients/logo_27.png'],
  ['name'=>'Graben Security',           'url'=>'#',                         'logo'=>'uploads/clients/logo_28.png'],
  ['name'=>'UNBS',                      'url'=>'#',                         'logo'=>'uploads/clients/logo_29.png'],
  ['name'=>'Airtel Uganda',             'url'=>'https://ug.airtel.com',     'logo'=>'uploads/clients/logo_30.png'],
  ['name'=>'Uganda Telecom',            'url'=>'#',                         'logo'=>'uploads/clients/logo_31.png'],
  ['name'=>'Hot Loaf Bakery',           'url'=>'#',                         'logo'=>'uploads/clients/logo_32.png'],
  ['name'=>'MHS Microhaem Scientifics', 'url'=>'#',                         'logo'=>'uploads/clients/logo_33.png'],
];
$clientsD = array_merge($clients, $clients);
@endphp
<section class="cl-sec" id="clients">
  <div class="container">
    <div class="sh reveal">
      <div class="sh-tag"><i class="fas fa-building"></i>&nbsp;Trusted By</div>
      <h2 class="sh-h">Our <em>Valued Clients</em></h2>
      <p class="sh-p">Over 20 leading organisations across Uganda and East Africa trust Mastermind Consult Ltd for their HR needs.</p>
      <div class="sh-ln"></div>
    </div>
  </div>
  <div class="mq" style="margin-top:12px">
    <div class="cm-tr">
      @foreach($clientsD as $cl)
      <a href="{{ $cl['url'] ?? '#' }}" target="_blank" rel="noopener">
        @if(!empty($cl['logo']))
          <div class="cl-box"><img src="{{ Str::startsWith($cl['logo'],'http') ? $cl['logo'] : asset('storage/'.$cl['logo']) }}" alt="{{ $cl['name'] }}"></div>
        @else
          <div class="cl-box">{{ $cl['name'] }}</div>
        @endif
      </a>
      @endforeach
    </div>
  </div>
</section>

{{-- EMPLOYER / CANDIDATE CTA --}}
<section class="sec sec-alt">
  <div class="container">
    <div class="sh reveal">
      <div class="sh-tag"><i class="fas fa-exchange-alt"></i>&nbsp;Get Started</div>
      <h2 class="sh-h">How Can We <em>Help You?</em></h2>
      <div class="sh-ln"></div>
    </div>
    <div class="cta-grid">
      <div class="cta-c emp reveal">
        <div class="dc"></div><div class="dc2"></div>
        <i class="fas fa-building big"></i>
        <h3>I'm an Employer</h3>
        <p>In a fast-changing business climate, Mastermind Consult Ltd is dedicated to helping your business effectively manage human capital — because people are the largest driver of organisational performance.</p>
        <a href="#contact" class="btn-ct">Contact Us Today</a>
      </div>
      <div class="cta-c cnd reveal">
        <div class="dc"></div><div class="dc2"></div>
        <i class="fas fa-user-graduate big"></i>
        <h3>I'm a Candidate</h3>
        <p>Looking for a job? We display your CV and profile to all our hiring clients. Browse our open vacancies and apply, or register to get notified about new opportunities matching your skills.</p>
        <a href="{{ route('careers.index') }}" class="btn-ct">View Open Positions</a>
      </div>
    </div>
  </div>
</section>

{{-- ABOUT --}}
<section class="sec" id="about">
  <div class="container">
    <div class="ab-grid">
      <div class="ab-img reveal">
        <img src="{{ asset('images/logo.png') }}" alt="About Mastermind Consult Ltd" style="object-fit:contain;padding:50px">
        <div class="ab-float">
          <div class="ab-f-ico"><i class="fas fa-award"></i></div>
          <div class="ab-f-txt">
            <strong>Preferred HR Partner</strong>
            <span>East Africa's Trusted Firm</span>
          </div>
        </div>
      </div>
      <div class="reveal">
        <div class="ab-tag"><i class="fas fa-info-circle"></i>&nbsp;About Us</div>
        <h2 class="ab-h">Your Strategic <em>HR Solution</em> Partner</h2>
        <p class="ab-p">Mastermind Consult Ltd is a dynamic HR consulting firm headquartered in Uganda. We combine traditional HR expertise with innovative approaches to deliver consistent, affordable and impactful human resource services across East Africa.</p>
        <div class="ab-q">
          <p>"To be the Preferred Human Resources Consulting Firm in East Africa — delivering innovative, affordable and consistent HR services that transform workplaces and elevate organisational performance."</p>
        </div>
        <div class="ab-feats">
          @foreach(['Outsourced Labour Management','Salary Surveys','Expatriate Labour Management','Organisational Restructuring','Recruitment Management','Training & Development','HR Manuals & Policies','Job Evaluation','Performance Management','HR Audits & Risk'] as $f)
          <div class="ab-f"><i class="fas fa-check-circle"></i><span>{{ $f }}</span></div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>

{{-- CONTACT --}}
<section class="sec sec-alt" id="contact">
  <div class="container">
    <div class="sh reveal">
      <div class="sh-tag"><i class="fas fa-envelope"></i>&nbsp;Contact Us</div>
      <h2 class="sh-h">Get In <em>Touch</em></h2>
      <p class="sh-p">Have an HR challenge? We'd love to help. Reach out to us today.</p>
      <div class="sh-ln"></div>
    </div>
    <div class="ct-grid reveal">
      <div>
        <div class="ct-item">
          <div class="ct-ico"><i class="fas fa-map-marker-alt"></i></div>
          <div class="ct-l"><strong>Location</strong><span>Kampala, Uganda</span></div>
        </div>
        <div class="ct-item">
          <div class="ct-ico"><i class="fas fa-envelope"></i></div>
          <div class="ct-l"><strong>Email</strong><a href="mailto:info@mastermindconsults.co.ug">info@mastermindconsults.co.ug</a></div>
        </div>
        <div class="ct-item">
          <div class="ct-ico"><i class="fas fa-phone"></i></div>
          <div class="ct-l"><strong>Phone</strong><span>0393 215 288 &nbsp;/&nbsp; +256 706 701116</span></div>
        </div>
        <div class="ct-item">
          <div class="ct-ico"><i class="fas fa-clock"></i></div>
          <div class="ct-l"><strong>Working Hours</strong><span>Mon – Fri &nbsp; 8:00am – 5:00pm</span></div>
        </div>
      </div>
      <div>
        <div class="cf-row">
          <div class="cf-g"><label class="cf-lbl">Your Name</label><input class="cf-inp" placeholder="John Doe"></div>
          <div class="cf-g"><label class="cf-lbl">Email Address</label><input type="email" class="cf-inp" placeholder="john@company.com"></div>
        </div>
        <div class="cf-g"><label class="cf-lbl">Subject</label><input class="cf-inp" placeholder="How can we help?"></div>
        <div class="cf-g"><label class="cf-lbl">Message</label><textarea rows="4" class="cf-inp" placeholder="Tell us about your HR needs…" style="resize:vertical"></textarea></div>
        <button class="btn-send" onclick="window.location='mailto:info@mastermindconsults.co.ug'">Send Message&nbsp;<i class="fas fa-paper-plane"></i></button>
      </div>
    </div>
  </div>
</section>

{{-- FOOTER --}}
<footer class="footer">
  <div class="container">
    <div class="f-inner">
      <div class="f-brand">
        <img src="{{ asset('images/logo.png') }}" alt="Mastermind Consult Ltd">
        <p>Your Strategic Human Resource Solution Partner. Transforming workplaces across Uganda and East Africa.</p>
        <div class="f-socs">
          <a href="#" class="f-soc"><i class="fab fa-x-twitter"></i></a>
          <a href="#" class="f-soc"><i class="fab fa-linkedin-in"></i></a>
          <a href="#" class="f-soc"><i class="fab fa-instagram"></i></a>
          <a href="#" class="f-soc"><i class="fab fa-youtube"></i></a>
        </div>
      </div>
      <div class="f-col">
        <h4>Quick Links</h4>
        <ul>
          <li><a href="{{ route('home') }}">Home</a></li>
          <li><a href="#jobs">Open Jobs</a></li>
          <li><a href="#services">Our Services</a></li>
          <li><a href="#about">About Us</a></li>
          <li><a href="{{ route('careers.index') }}">Job Board</a></li>
        </ul>
      </div>
      <div class="f-col">
        <h4>HR Services</h4>
        <ul>
          <li><a href="#services">Executive Recruitment</a></li>
          <li><a href="#services">Staff Outsourcing</a></li>
          <li><a href="#services">HR Systems</a></li>
          <li><a href="#services">Performance Mgt</a></li>
          <li><a href="#services">HR Audits</a></li>
          <li><a href="#services">Salary Benchmarking</a></li>
        </ul>
      </div>
      <div class="f-col">
        <h4>Contact</h4>
        <div class="f-ci"><i class="fas fa-envelope"></i><span>info@mastermindconsults.co.ug</span></div>
        <div class="f-ci"><i class="fas fa-phone"></i><span>0393 215 288</span></div>
        <div class="f-ci"><i class="fas fa-phone"></i><span>+256 706 701116</span></div>
        <div class="f-ci"><i class="fas fa-map-marker-alt"></i><span>Kampala, Uganda</span></div>
        <div style="margin-top:13px">
          <a href="{{ route('login') }}" style="display:inline-flex;align-items:center;gap:5px;padding:8px 16px;background:var(--gold);color:var(--dark);border-radius:7px;font-size:.8rem;font-weight:700">
            <i class="fas fa-sign-in-alt"></i>&nbsp;Staff Login
          </a>
        </div>
      </div>
    </div>
    <div class="f-bot">
      <span>&copy; {{ date('Y') }} Mastermind Consult Ltd. All rights reserved.</span>
      <span><a href="{{ route('privacy') }}">Privacy Policy</a>&nbsp;·&nbsp;<a href="#contact">Contact Us</a></span>
    </div>
  </div>
</footer>

<script>
(function(){
  // Navbar scroll
  const nav = document.getElementById('nav');
  window.addEventListener('scroll', () => nav.classList.toggle('sc', scrollY > 40), {passive:true});

  // Mobile menu
  const brg = document.getElementById('brg');
  const mbnav = document.getElementById('mbnav');
  brg.addEventListener('click', () => mbnav.classList.toggle('open'));
  mbnav.querySelectorAll('a').forEach(a => a.addEventListener('click', () => mbnav.classList.remove('open')));

  // Hero slider
  const slides = document.querySelectorAll('.slide');
  const dots = document.querySelectorAll('.sdot');
  let cur = 0, tmr;
  function goTo(n) {
    slides[cur].classList.remove('active'); dots[cur].classList.remove('active');
    cur = (n + slides.length) % slides.length;
    slides[cur].classList.add('active'); dots[cur].classList.add('active');
  }
  function play() { tmr = setInterval(() => goTo(cur + 1), 5500); }
  document.getElementById('sn').onclick = () => { clearInterval(tmr); goTo(cur + 1); play(); };
  document.getElementById('sp').onclick = () => { clearInterval(tmr); goTo(cur - 1); play(); };
  dots.forEach(d => d.addEventListener('click', () => { clearInterval(tmr); goTo(+d.dataset.d); play(); }));
  play();

  // Scroll reveal
  const ro = new IntersectionObserver(es => es.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('visible'); ro.unobserve(e.target); }
  }), {threshold: 0.1});
  document.querySelectorAll('.reveal').forEach(el => ro.observe(el));

  // Stat counters
  const co = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return;
    const el = e.target, t = +el.dataset.count, step = Math.ceil(t / 55);
    let n = 0;
    const tk = setInterval(() => { n = Math.min(n + step, t); el.textContent = n + '+'; if (n >= t) clearInterval(tk); }, 28);
    co.unobserve(el);
  }), {threshold: 0.5});
  document.querySelectorAll('[data-count]').forEach(el => co.observe(el));
})();
</script>
</body>
</html>
