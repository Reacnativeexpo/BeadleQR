
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>BeadleQR | Teacher Attendance System</title>

<meta name="description" content="A secure, QR-based attendance platform that helps administrators, teachers, and section Beadles record and monitor teacher attendance with speed and accuracy.">
<meta property="og:title" content="BeadleQR | Teacher Attendance System">
<meta property="og:description" content="A secure, QR-based teacher attendance platform for administrators, teachers, and section Beadles.">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Sora:wght@400;600;700&display=swap" rel="stylesheet">

<style>
:root {
  --background: oklch(1 0 0);
  --foreground: oklch(.235 .008 60);
  --primary: oklch(.705 .213 47.604);
  --primary-foreground: oklch(1 0 0);
  --secondary: oklch(.975 .005 60);
  --muted: oklch(.97 .002 60);
  --muted-foreground: oklch(.53 .015 60);
  --accent: oklch(.96 .025 65);
  --border: oklch(.91 .007 60);
  --success: oklch(.65 .16 155);
  --shadow-float: 0 8px 28px oklch(.25 .02 60 / .08);
  --font-sans: 'Inter', sans-serif;
  --font-display: 'Sora', sans-serif;
}

* {
  box-sizing: border-box;
  border-color: var(--border);
}

html {
  scroll-behavior: smooth;
  scroll-padding-top: 100px;
}

body {
  margin: 0;
  background: var(--background);
  color: var(--foreground);
  font-family: var(--font-sans);
  -webkit-font-smoothing: antialiased;
}

button, a {
  -webkit-tap-highlight-color: transparent;
}

a {
  text-decoration: none;
}

img {
  max-width: 100%;
}

/* BUTTONS */

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  white-space: nowrap;
  border-radius: 999px;
  height: 48px;
  padding: 0 24px;
  font-size: 18px;
  font-weight: 500;
  cursor: pointer;
}

.btn svg {
  width: 20px;
  height: 20px;
  pointer-events: none;
  flex-shrink: 0;
}

.btn-lg {
  height: 56px;
  padding: 0 38px;
  font-size: 18px;
}

.btn-brand {
  background: var(--primary);
  color: var(--primary-foreground);
}

.btn-brand:hover {
  background: color-mix(in srgb, var(--primary) 88%, var(--primary-foreground));
}

.btn-brandOutline {
  background: var(--background);
  color: var(--foreground);
  border: 1px solid var(--border);
}

.btn-brandOutline:hover {
  background: var(--accent);
}

.btn-brandWhite {
  background: var(--primary-foreground);
  color: var(--primary);
}

.btn-brandWhite:hover {
  background: var(--secondary);
}

.btn-nav {
  color: var(--primary-foreground);
  border: 1px solid color-mix(in srgb, var(--primary-foreground) 60%, transparent);
  font-size: 16px;
  height: 44px;
  padding: 0 20px;
}

.btn-nav:hover {
  background: color-mix(in srgb, var(--primary-foreground) 15%, transparent);
}

/* HEADER */

.page-shell {
  width: 100%;
  overflow: hidden;
}

.site-header {
  position: absolute;
  z-index: 10;
  top: 0;
  left: 0;
  width: 100%;
}

.nav-inner {
  max-width: 1240px;
  margin: auto;
  height: 130px;
  padding: 0 48px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
}

/* INCREASED DESKTOP HEADER LOGO */
.site-header .brand-logo {
  display: block;
  width: auto;
  height: 94px;
  max-width: 340px;
  object-fit: contain;
  object-position: left center;
}

.brand {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  min-width: 0;
}

.nav-links {
  display: flex;
  align-items: center;
  gap: 36px;
}

.nav-links a {
  font-size: 16px;
  font-weight: 500;
  color: var(--primary-foreground);
}

/* HERO */

.hero {
  position: relative;
  min-height: 860px;
  background: var(--background);
}

.hero-orange {
  position: absolute;
  inset: 0;
  background: var(--primary);
  clip-path: ellipse(55% 80% at 100% 15%);
}

.hero-bottom-curve {
  position: absolute;
  width: 480px;
  height: 140px;
  bottom: 0;
  left: -100px;
  background: var(--primary);
  border-radius: 0 100% 0 0;
  clip-path: ellipse(65% 65% at 35% 100%);
}

.hero-inner {
  max-width: 1240px;
  margin: auto;
  padding: 190px 48px 110px;
  position: relative;
}

.hero-copy {
  position: relative;
  z-index: 2;
  width: 58%;
  max-width: 720px;
}

.status {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--muted-foreground);
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  margin-bottom: 24px;
}

.status-dot {
  width: 9px;
  height: 9px;
  background: var(--success);
  border-radius: 50%;
  flex-shrink: 0;
}

h1 {
  font-family: var(--font-display);
  font-size: 64px;
  font-weight: 700;
  line-height: 1.15;
  letter-spacing: -0.02em;
  margin: 0 0 24px;
}

h1 span {
  color: var(--primary);
}

.hero-description {
  color: var(--muted-foreground);
  font-size: 18px;
  line-height: 1.8;
  max-width: 580px;
  margin: 0 0 32px;
}

.feature-list {
  display: flex;
  gap: 28px;
  flex-wrap: wrap;
  margin-bottom: 36px;
  font-size: 16px;
  font-weight: 500;
}

.feature-list span {
  display: flex;
  align-items: center;
  gap: 9px;
}

.feature-list svg {
  width: 20px;
  height: 20px;
  color: var(--primary);
  flex-shrink: 0;
}

.hero-actions {
  display: flex;
  gap: 16px;
}

.hero-stats {
  display: flex;
  gap: 0;
  margin-top: 45px;
}

.stat {
  padding: 0 32px;
  border-right: 1px solid var(--border);
}

.stat:first-child {
  padding-left: 0;
}

.stat:last-child {
  border: 0;
}

.stat strong {
  font-family: var(--font-display);
  font-size: 30px;
  font-weight: 700;
}

.stat small {
  display: block;
  color: var(--muted-foreground);
  font-size: 14px;
  margin-top: 6px;
}

.hero-art {
  position: absolute;
  width: 660px;
  right: -25px;
  top: 130px;
  z-index: 1;
}

.hero-art img {
  display: block;
  width: 100%;
  height: auto;
}

.scan-label {
  position: absolute;
  display: flex;
  gap: 10px;
  align-items: center;
  background: var(--background);
  padding: 14px 20px;
  border-radius: 10px;
  box-shadow: var(--shadow-float);
  font-size: 14px;
  font-weight: 600;
}

.scan-label svg {
  width: 18px;
  height: 18px;
  color: var(--primary);
}

.instant {
  top: 85px;
  right: 42px;
}

.recorded {
  bottom: 125px;
  left: 80px;
}

/* HOW IT WORKS */

.process-section {
  background: var(--secondary);
  padding: 90px 48px 100px;
}

.section-inner {
  max-width: 1144px;
  margin: auto;
}

.section-heading {
  text-align: center;
  max-width: 700px;
  margin: 0 auto 54px;
}

.eyebrow {
  color: var(--primary);
  font-size: 14px;
  text-transform: uppercase;
  font-weight: 600;
  margin: 0 0 14px;
}

h2 {
  font-family: var(--font-display);
  font-size: 40px;
  line-height: 1.25;
  font-weight: 600;
  margin: 0 0 18px;
}

.section-heading p:last-child {
  font-size: 16px;
  line-height: 1.8;
  color: var(--muted-foreground);
  max-width: 620px;
  margin: auto;
}

.steps {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 28px;
}

.step {
  background: var(--background);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 34px;
}

.step-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 28px;
}

.step-top svg {
  width: 30px;
  height: 30px;
  color: var(--primary);
}

.step-number {
  font-family: var(--font-display);
  font-size: 32px;
  color: var(--border);
  font-weight: 600;
}

.step h3 {
  font-size: 20px;
  font-family: var(--font-display);
  font-weight: 600;
  margin-bottom: 14px;
}

.step p {
  color: var(--muted-foreground);
  font-size: 15px;
  line-height: 1.8;
  margin: 0;
}

/* CALL TO ACTION */

.cta-section {
  padding: 75px 48px;
  background: var(--primary);
  color: var(--primary-foreground);
}

.cta-inner {
  max-width: 1144px;
  margin: auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 40px;
}

.cta-inner h2 {
  font-size: 34px;
  margin-bottom: 10px;
}

.cta-inner p {
  font-size: 16px;
  opacity: .95;
  margin: 0;
  line-height: 1.7;
}

/* FOOTER */

.footer-inner {
  max-width: 1240px;
  padding: 42px 48px;
  margin: auto;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
}

/* INCREASED DESKTOP FOOTER LOGO */
.footer-inner .brand-logo {
  display: block;
  width: auto;
  height: 78px;
  max-width: 320px;
  object-fit: contain;
  object-position: left center;
}

.footer-links {
  display: flex;
  gap: 30px;
  font-size: 15px;
  color: var(--primary);
}

.footer-links a {
  color: var(--primary);
  transition: opacity .2s ease;
}

.footer-links a:hover {
  opacity: .78;
}

.footer-copy {
  font-size: 13px;
  color: var(--muted-foreground);
  margin: 0;
}

/* TABLETS */

@media (min-width: 1500px) {
  .hero-orange {
    clip-path: ellipse(52% 85% at 100% 10%);
  }
}

@media (max-width: 1000px) {
  .nav-inner {
    padding: 0 28px;
    gap: 18px;
  }

  .site-header .brand-logo {
    height: 80px;
    max-width: 280px;
  }

  .nav-links {
    gap: 24px;
  }

  .hero-inner {
    padding-left: 28px;
    padding-right: 28px;
  }

  .hero-art {
    width: 540px;
    right: -85px;
    top: 160px;
  }

  h1 {
    font-size: 48px;
  }

  .hero-copy {
    width: 60%;
  }

  .hero-orange {
    clip-path: ellipse(52% 75% at 100% 15%);
  }

  .instant {
    right: 85px;
  }

  .recorded {
    left: 100px;
    bottom: 70px;
  }

  .footer-inner .brand-logo {
    height: 68px;
  }
}


/* MOBILE */

@media (max-width: 700px) {
  html {
    scroll-padding-top: 76px;
  }

  body {
    overflow-x: hidden;
  }

  .page-shell {
    width: 100%;
    overflow: clip;
  }

  /* HEADER: CENTER LOGO, SIGN IN RIGHT */
  .site-header .nav-inner {
    height: 88px;
    padding: 0 14px;
    gap: 0;
    position: relative;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .site-header .nav-inner > .brand {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    align-items: center;
    justify-content: center;
    width: auto;
    max-width: calc(100% - 125px);
    min-width: 0;
    margin: 0;
    overflow: hidden;
    z-index: 1;
  }

  /* LARGER, CENTERED MOBILE HEADER LOGO */
  .site-header .nav-inner > .brand .brand-logo {
    display: block;
    height: 62px;
    width: auto;
    max-width: 100%;
    object-fit: contain;
    object-position: center;
    flex-shrink: 1;
  }

  .site-header .nav-links {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0;
    margin: 0;
    min-width: 0;
    z-index: 2;
  }

  .site-header .nav-links a:first-child,
  .site-header .nav-links a:nth-child(2) {
    display: none;
  }

  .site-header .nav-links .btn-nav {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    height: 40px;
    padding: 0 14px;
    gap: 7px;
    font-size: 14px;
    white-space: nowrap;
  }

  .site-header .nav-links .btn-nav svg {
    width: 17px;
    height: 17px;
  }

  /* HERO */
  .hero {
    min-height: 0;
  }

  .hero-orange {
    clip-path: ellipse(105% 30% at 100% 94%);
  }

  .hero-inner {
    padding: 112px 22px 0;
  }

  .hero-copy {
    width: 100%;
    max-width: none;
  }

  .status {
    gap: 8px;
    font-size: 11px;
    margin-bottom: 17px;
  }

  .status-dot {
    width: 8px;
    height: 8px;
    flex: 0 0 8px;
  }

  h1 {
    font-size: clamp(30px, 8.2vw, 38px);
    line-height: 1.12;
    letter-spacing: -0.035em;
    margin-bottom: 18px;
    overflow-wrap: normal;
  }

  h1 br {
    display: none;
  }

  .hero-description {
    font-size: 15px;
    line-height: 1.7;
    margin-bottom: 23px;
  }

  .feature-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 13px 10px;
    margin-bottom: 25px;
    font-size: 13px;
  }

  .feature-list span {
    gap: 7px;
    min-width: 0;
  }

  .feature-list svg {
    width: 18px;
    height: 18px;
    flex: 0 0 18px;
  }

  .hero-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }

  .btn-lg {
    width: 100%;
    box-sizing: border-box;
    min-width: 0;
    height: 48px;
    padding: 0 12px;
    gap: 7px;
    font-size: 14px;
    white-space: nowrap;
  }

  .btn-lg svg {
    width: 17px;
    height: 17px;
  }

  .hero-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    margin-top: 27px;
    padding-bottom: 24px;
    position: relative;
    z-index: 2;
  }

  .stat {
    min-width: 0;
    padding: 0 8px;
    text-align: center;
  }

  .stat:first-child {
    padding-left: 0;
    text-align: left;
  }

  .stat:last-child {
    padding-right: 0;
    text-align: right;
  }

  .stat strong {
    font-size: clamp(18px, 5.4vw, 25px);
    letter-spacing: -0.04em;
  }

  .stat small {
    font-size: 11px;
    line-height: 1.35;
    margin-top: 5px;
  }

  .hero-art {
    position: relative;
    width: min(100%, 390px);
    max-width: 100%;
    top: auto;
    right: auto;
    bottom: auto;
    margin: 0 auto;
    z-index: 1;
  }

  .hero-art img {
    display: block;
    width: 100%;
    height: auto;
  }

  .scan-label {
    padding: 10px 12px;
    gap: 7px;
    font-size: 11px;
    border-radius: 9px;
  }

  .scan-label svg {
    width: 15px;
    height: 15px;
  }

  .recorded {
    left: 3%;
    bottom: 15%;
  }

  .instant {
    right: 3%;
    top: 12%;
  }

  .hero-bottom-curve {
    display: none;
  }

  /* PROCESS */
  .process-section {
    padding: 52px 20px 58px;
  }

  .section-heading {
    margin-bottom: 30px;
  }

  .eyebrow {
    font-size: 12px;
    margin-bottom: 10px;
  }

  h2 {
    font-size: clamp(26px, 7vw, 30px);
    line-height: 1.2;
    margin-bottom: 14px;
  }

  .section-heading p:last-child {
    font-size: 14px;
    line-height: 1.7;
  }

  .steps {
    grid-template-columns: minmax(0, 1fr);
    gap: 14px;
  }

  .step {
    padding: 22px;
    border-radius: 14px;
  }

  .step-top {
    margin-bottom: 15px;
  }

  .step-top svg {
    width: 26px;
    height: 26px;
  }

  .step-number {
    font-size: 26px;
  }

  .step h3 {
    font-size: 18px;
    line-height: 1.35;
    margin: 0 0 9px;
  }

  .step p {
    font-size: 14px;
    line-height: 1.7;
  }

  /* CTA */
  .cta-section {
    padding: 42px 22px;
  }

  .cta-inner {
    flex-direction: column;
    align-items: stretch;
    gap: 22px;
  }

  .cta-inner h2 {
    font-size: clamp(25px, 6.8vw, 29px);
    line-height: 1.2;
    margin-bottom: 12px;
  }

  .cta-inner p {
    font-size: 14px;
    line-height: 1.7;
  }

  .cta-inner > a {
    width: 100%;
    align-self: stretch;
  }

  /* FOOTER: CENTER LOGO AND RESPONSIVE LINKS */
  .footer-inner {
    width: 100%;
    box-sizing: border-box;
    padding: 30px 22px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 20px;
    text-align: center;
  }

  .footer-inner > .brand {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-width: 0;
    margin: 0 auto;
  }

  /* LARGER, CENTERED MOBILE FOOTER LOGO */
  .footer-inner .brand-logo {
    display: block;
    width: auto;
    height: 66px;
    max-width: min(100%, 300px);
    margin: 0 auto;
    object-fit: contain;
    object-position: center;
  }

  .footer-links {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    width: 100%;
    max-width: 100%;
    gap: 12px 22px;
    margin: 0 auto;
    font-size: 13px;
    text-align: center;
  }

  .footer-links a {
    display: inline-block;
    max-width: 100%;
    white-space: normal;
    overflow-wrap: anywhere;
    line-height: 1.6;
  }

  .footer-copy {
    width: 100%;
    margin: 0;
    font-size: 12px;
    line-height: 1.7;
    text-align: center;
    overflow-wrap: anywhere;
  }
}

/* SMALL MOBILE PHONES */
@media (max-width: 380px) {
  .site-header .nav-inner {
    height: 82px;
    padding: 0 10px;
    gap: 0;
  }

  .site-header .nav-inner > .brand {
    max-width: calc(100% - 105px);
  }

  .site-header .nav-inner > .brand .brand-logo {
    height: 54px;
    max-width: 100%;
  }

  .site-header .nav-links {
    right: 10px;
  }

  .site-header .nav-links .btn-nav {
    height: 36px;
    padding: 0 10px;
    font-size: 12px;
  }

  .hero-inner {
    padding: 102px 17px 0;
  }

  h1 {
    font-size: 30px;
  }

  .hero-description {
    font-size: 14px;
  }

  .feature-list {
    font-size: 12px;
    gap: 11px 7px;
  }

  .btn-lg {
    padding: 0 8px;
    font-size: 13px;
  }

  .stat {
    padding: 0 5px;
  }

  .stat strong {
    font-size: 18px;
  }

  .stat small {
    font-size: 10px;
  }

  .process-section {
    padding-left: 17px;
    padding-right: 17px;
  }

  .step {
    padding: 19px;
  }

  .cta-section {
    padding-left: 18px;
    padding-right: 18px;
  }

  .footer-inner {
    padding: 26px 14px;
    gap: 17px;
  }

  .footer-inner .brand-logo {
    height: 58px;
    max-width: 100%;
  }

  .footer-links {
    gap: 10px 15px;
    font-size: 12px;
  }

  .footer-copy {
    font-size: 11px;
  }
}

/* ACCESSIBILITY */
@media (prefers-reduced-motion: reduce) {
  html {
    scroll-behavior: auto;
  }

  * {
    transition: none !important;
  }
}

</style>
</head>

<body>
<div class="page-shell">

  <!-- HEADER -->
  <header class="site-header">
    <nav class="nav-inner" aria-label="Main navigation">

      <a href="#home" class="brand" aria-label="BeadleQR home">
        <img
          src="images/beadle.png"
          alt="BeadleQR Logo"
          class="brand-logo"
        >
      </a>

      <div class="nav-links">
        <a href="#how-it-works">How It Works</a>
        <a href="#get-started">Get Started</a>

        <a href="login.php" class="btn btn-nav">
          Sign In
          <svg viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"
               aria-hidden="true">
            <path d="M5 12h14"/>
            <path d="m12 5 7 7-7 7"/>
          </svg>
        </a>
      </div>

    </nav>
  </header>

  <main>

    <!-- HERO SECTION -->
    <section class="hero" id="home">

      <div class="hero-orange" aria-hidden="true"></div>
      <div class="hero-bottom-curve" aria-hidden="true"></div>

      <div class="hero-inner">

        <div class="hero-copy">

          <div class="status">
            <span class="status-dot"></span>
            System Operational
          </div>

          <h1>
            Teacher Attendance<br>
            <span>Monitoring System</span>
          </h1>

          <p class="hero-description">
            A secure, QR-based attendance platform that helps administrators,
            teachers, and section Beadles record and monitor teacher attendance
            with speed and accuracy.
          </p>

          <div class="feature-list">

            <span>
              <svg viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true">
                <rect width="5" height="5" x="3" y="3" rx="1"/>
                <rect width="5" height="5" x="16" y="3" rx="1"/>
                <rect width="5" height="5" x="3" y="16" rx="1"/>
                <path d="M21 16h-3a2 2 0 0 0-2 2v3"/>
                <path d="M21 21v.01"/>
                <path d="M12 7v3a2 2 0 0 1-2 2H7"/>
                <path d="M3 12h.01"/>
                <path d="M12 3h.01"/>
                <path d="M12 16v.01"/>
                <path d="M16 12h1"/>
                <path d="M21 12v.01"/>
                <path d="M12 21v-1"/>
              </svg>
              QR Attendance
            </span>

            <span>
              <svg viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 6v6l4 2"/>
              </svg>
              Time Tracking
            </span>

            <span>
              <svg viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true">
                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                <path d="M8 13v2"/>
                <path d="M12 13v4"/>
                <path d="M16 13v6"/>
              </svg>
              Reports
            </span>

          </div>

          <div class="hero-actions">

            <a href="login.php" class="btn btn-brand btn-lg">
              Get Started
              <svg viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true">
                <path d="M5 12h14"/>
                <path d="m12 5 7 7-7 7"/>
              </svg>
            </a>

            <a href="#how-it-works" class="btn btn-brandOutline btn-lg">
              Learn More
            </a>

          </div>

          <div class="hero-stats">

            <div class="stat">
              <strong>99.9%</strong>
              <small>Uptime</small>
            </div>

            <div class="stat">
              <strong>&lt; 2s</strong>
              <small>Scan Time</small>
            </div>

            <div class="stat">
              <strong>100%</strong>
              <small>Secure</small>
            </div>

          </div>
        </div>

        <div class="hero-art">

          <img
            src="images/holding.png"
            alt="Holding phone with QR code"
          >

          <div class="scan-label recorded">
            <span class="status-dot"></span>
            Attendance Recorded
          </div>

        </div>

      </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="process-section" id="how-it-works">

      <div class="section-inner">

        <div class="section-heading">

          <p class="eyebrow">Process</p>

          <h2>How BeadleQR Works</h2>

          <p>
            Teacher attendance becomes faster and easier with a simple
            QR-based process handled by the designated Beadle of each section.
          </p>

        </div>

        <div class="steps">

          <article class="step">

            <div class="step-top">
              <svg viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true">
                <path d="M2 21a8 8 0 0 1 13.292-6"/>
                <circle cx="10" cy="8" r="5"/>
                <path d="M19 16v6"/>
                <path d="M22 19h-6"/>
              </svg>

              <span class="step-number">01</span>
            </div>

            <h3>Beadle Access</h3>

            <p>
              The designated Beadle logs into BeadleQR using their assigned
              account and accesses the attendance scanner for their section.
            </p>

          </article>

          <article class="step">

            <div class="step-top">
              <svg viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true">
                <rect width="5" height="5" x="3" y="3" rx="1"/>
                <rect width="5" height="5" x="16" y="3" rx="1"/>
                <rect width="5" height="5" x="3" y="16" rx="1"/>
                <path d="M21 16h-3a2 2 0 0 0-2 2v3"/>
                <path d="M21 21v.01"/>
                <path d="M12 7v3a2 2 0 0 1-2 2H7"/>
                <path d="M3 12h.01"/>
                <path d="M12 3h.01"/>
                <path d="M12 16v.01"/>
                <path d="M16 12h1"/>
                <path d="M21 12v.01"/>
                <path d="M12 21v-1"/>
              </svg>

              <span class="step-number">02</span>
            </div>

            <h3>Scan Teacher QR</h3>

            <p>
              The Beadle scans the teacher's generated QR code to record the
              teacher's time-in or time-out attendance for the scheduled class.
            </p>

          </article>

          <article class="step">

            <div class="step-top">
              <svg viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round"
                   aria-hidden="true">
                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/>
                <path d="M14 2v4a2 2 0 0 0 2 2h4"/>
                <path d="M8 13v2"/>
                <path d="M12 13v4"/>
                <path d="M16 13v6"/>
              </svg>

              <span class="step-number">03</span>
            </div>

            <h3>Monitor Attendance</h3>

            <p>
              Administrators and teachers can monitor attendance records,
              view time-in and time-out activity, and generate reports for
              better attendance management.
            </p>

          </article>

        </div>
      </div>
    </section>

    <!-- GET STARTED -->
    <section class="cta-section" id="get-started">

      <div class="cta-inner">

        <div>
          <h2>Ready to Simplify Teacher Attendance?</h2>

          <p>
            Access BeadleQR and manage teacher attendance faster,
            smarter, and more efficiently.
          </p>
        </div>

        <a href="login.php" class="btn btn-brandWhite btn-lg">
          Access BeadleQR

          <svg viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round"
               aria-hidden="true">
            <path d="M5 12h14"/>
            <path d="m12 5 7 7-7 7"/>
          </svg>
        </a>

      </div>
    </section>

  </main>

  <!-- FOOTER -->
  <footer>

    <div class="footer-inner">

      <a href="#home" class="brand" aria-label="BeadleQR home">
        <img
          src="images/beadle.png"
          alt="BeadleQR Logo"
          class="brand-logo"
        >
      </a>

      <div class="footer-links">
        <a href="#home">Home</a>
        <a href="#how-it-works">How It Works</a>
        <a href="login.php">Sign In</a>
      </div>

      <p class="footer-copy">
        © 2026 BeadleQR. All rights reserved.
      </p>

    </div>

  </footer>

</div>
</body>
</html>
