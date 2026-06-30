<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn())      redirect(SITE_URL.'/patient/dashboard.php');
if (isAdminLoggedIn()) redirect(SITE_URL.'/admin/dashboard.php');
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CyberClinic — Secure. Smart. Caring.</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:opsz,wght@9..40,300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --navy:#0a1628;
  --primary:#1a6b8a;
  --primary-light:#e8f4f8;
  --accent:#0ea5c9;
  --white:#ffffff;
  --danger:#dc2626;
  --success:#16a34a;
  --text-muted:rgba(255,255,255,.6);
}
html,body{height:100%;font-family:'DM Sans',sans-serif;overflow-x:hidden}

/* ── HERO BACKGROUND ── */
.hero-bg{
  min-height:100vh;
  background:
    linear-gradient(135deg,rgba(10,22,40,.88) 0%,rgba(10,22,40,.72) 50%,rgba(14,60,80,.80) 100%),
    url('https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=1600&q=80&auto=format&fit=crop') center/cover no-repeat;
  display:flex;
  flex-direction:column;
  position:relative;
}

/* ── NAVBAR ── */
.nav{
  display:flex;align-items:center;justify-content:space-between;
  padding:22px 60px;
  position:relative;z-index:10;
  border-bottom:1px solid rgba(255,255,255,.08);
}
.nav-brand{display:flex;align-items:center;gap:12px;text-decoration:none}
.nav-logo{
  width:42px;height:42px;
  background:linear-gradient(135deg,var(--primary),var(--accent));
  border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.nav-logo svg{width:22px;height:22px}
.nav-name{font-family:'Playfair Display',serif;font-size:20px;font-weight:700;color:#fff;letter-spacing:.01em}
.nav-sub{font-size:10px;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase;margin-top:1px}
.nav-actions{display:flex;gap:10px;align-items:center}
.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:all .2s;text-decoration:none;white-space:nowrap}
.btn-outline-white{background:transparent;color:rgba(255,255,255,.85);border:1.5px solid rgba(255,255,255,.3)}
.btn-outline-white:hover{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.6);color:#fff}
.btn-primary{background:linear-gradient(135deg,var(--primary),#135470);color:#fff;box-shadow:0 4px 18px rgba(26,107,138,.4)}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 24px rgba(26,107,138,.5)}
.btn-lg{padding:14px 32px;font-size:15px;border-radius:10px}
.btn-block{width:100%;justify-content:center}
.btn-white{background:#fff;color:var(--navy);font-weight:700}
.btn-white:hover{background:#f0f4f8;transform:translateY(-1px)}

/* ── HERO CONTENT ── */
.hero-content{
  flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;
  text-align:center;padding:60px 24px;position:relative;z-index:5;
}
.hero-badge{
  display:inline-flex;align-items:center;gap:8px;
  padding:7px 18px;border-radius:100px;
  background:rgba(14,165,201,.2);border:1px solid rgba(14,165,201,.4);
  font-size:12px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--accent);
  margin-bottom:26px;
}
.badge-dot{width:6px;height:6px;border-radius:50%;background:var(--accent);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.8)}}
.hero-title{
  font-family:'Playfair Display',serif;
  font-size:clamp(36px,6vw,68px);font-weight:700;color:#fff;
  line-height:1.1;margin-bottom:20px;
}
.hero-title span{color:var(--accent)}
.hero-sub{font-size:18px;color:var(--text-muted);max-width:560px;line-height:1.7;margin-bottom:38px}
.hero-cta{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-bottom:56px}
.hero-stats{
  display:flex;gap:48px;justify-content:center;flex-wrap:wrap;
  padding-top:36px;border-top:1px solid rgba(255,255,255,.1);
}
.stat-item{text-align:center}
.stat-value{font-family:'Playfair Display',serif;font-size:30px;font-weight:700;color:#fff}
.stat-label{font-size:13px;color:var(--text-muted);margin-top:3px}

/* ── SECURITY BAR ── */
.sec-bar{background:rgba(0,0,0,.35);backdrop-filter:blur(10px);border-top:1px solid rgba(255,255,255,.08);padding:20px 60px}
.sec-inner{max-width:1100px;margin:0 auto;display:flex;justify-content:space-around;flex-wrap:wrap;gap:16px}
.sec-item{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.7);font-size:13px;font-weight:500}
.sec-icon{width:32px;height:32px;background:rgba(26,107,138,.35);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}

/* ── MODAL OVERLAY ── */
.modal-overlay{
  display:none;position:fixed;inset:0;z-index:1000;
  background:rgba(10,22,40,.75);backdrop-filter:blur(6px);
  align-items:center;justify-content:center;padding:20px;
}
.modal-overlay.open{display:flex}
.modal{
  background:#fff;border-radius:20px;padding:0;width:100%;max-width:460px;
  box-shadow:0 32px 80px rgba(0,0,0,.35);overflow:hidden;
  animation:slideUp .3s cubic-bezier(.34,1.56,.64,1);
}
.modal-wide{max-width:720px}
@keyframes slideUp{from{opacity:0;transform:translateY(30px) scale(.96)}to{opacity:1;transform:none}}
.modal-header{
  background:linear-gradient(135deg,var(--navy) 0%,#1e3a52 100%);
  padding:28px 32px 24px;position:relative;text-align:center;
}
.modal-logo{
  width:50px;height:50px;background:linear-gradient(135deg,var(--primary),var(--accent));
  border-radius:14px;display:flex;align-items:center;justify-content:center;
  margin:0 auto 14px;
}
.modal-title{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:4px}
.modal-sub{font-size:13px;color:rgba(255,255,255,.5)}
.modal-close{
  position:absolute;top:16px;right:16px;
  background:rgba(255,255,255,.1);border:none;border-radius:8px;
  width:32px;height:32px;cursor:pointer;color:rgba(255,255,255,.7);
  display:flex;align-items:center;justify-content:center;font-size:18px;line-height:1;
  transition:.15s;
}
.modal-close:hover{background:rgba(255,255,255,.2);color:#fff}
.modal-body{padding:28px 32px 32px}
.modal-body-wide{padding:28px 32px 32px;display:grid;grid-template-columns:1fr 1fr;gap:20px}
.role-tabs{display:flex;background:#f0f4f8;border-radius:8px;padding:4px;margin-bottom:20px;border:1px solid #dde5ed}
.role-tab{flex:1;text-align:center;padding:9px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;color:#4a6480;transition:all .2s}
.role-tab.active{background:#fff;color:var(--primary);box-shadow:0 1px 4px rgba(0,0,0,.08)}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:12px;font-weight:600;color:#4a6480;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em}
.form-control{width:100%;padding:11px 14px;border:1.5px solid #dde5ed;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px;color:#0f2233;background:#fff;transition:.2s;outline:none}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,107,138,.12)}
.form-control::placeholder{color:#8aa5be}
select.form-control{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%234a6480' stroke-width='1.5' fill='none'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 13px center;padding-right:36px;cursor:pointer}
.form-row-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.form-section{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--primary);margin:18px 0 12px;padding-bottom:7px;border-bottom:1.5px solid #e8f4f8}
.btn-login{background:linear-gradient(135deg,var(--primary),#135470);color:#fff;box-shadow:0 3px 12px rgba(26,107,138,.35);border-radius:8px;padding:12px 22px;font-size:14px;font-weight:600;border:none;cursor:pointer;width:100%;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s}
.btn-login:hover{transform:translateY(-1px);box-shadow:0 5px 18px rgba(26,107,138,.45)}
.modal-footer{text-align:center;font-size:13px;color:#4a6480;margin-top:18px}
.modal-footer a{color:var(--primary);font-weight:600;text-decoration:none}
.modal-footer a:hover{text-decoration:underline}
.pw-wrap{position:relative}
.pw-wrap .form-control{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#4a6480;padding:4px}
.alert{padding:11px 15px;border-radius:8px;font-size:13px;margin-bottom:16px;border:1px solid transparent}
.alert-error{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
.alert-success{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
.pw-strength{font-size:12px;font-weight:600;margin-top:4px}
.req{color:var(--danger)}
.demo-box{background:#f8fafb;border:1px solid #dde5ed;border-radius:8px;padding:12px 14px;font-size:12px;color:#4a6480;margin-top:14px;line-height:2}
.mfa-hint{background:#e8f4f8;border:1px solid #9ecfe0;border-radius:8px;padding:10px 13px;font-size:12px;color:#135470;margin-top:10px}
.enc-notice{display:flex;align-items:center;gap:7px;background:#e8f4f8;border:1px solid #9ecfe0;border-radius:8px;padding:10px 13px;font-size:12px;color:#135470;margin:14px 0}

/* mobile */
@media(max-width:680px){.nav{padding:16px 20px}.hero-content{padding:40px 16px}.sec-bar{padding:16px 20px}.modal-body-wide{grid-template-columns:1fr}.form-row-2,.form-row-3{grid-template-columns:1fr}}
</style>
</head>
<body>

<!-- HERO -->
<div class="hero-bg">
  <!-- NAVBAR -->
  <nav class="nav">
    <a href="#" class="nav-brand">
      <div class="nav-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
      </div>
      <div><div class="nav-name">CyberClinic</div><div class="nav-sub">Secure Medical</div></div>
    </a>
    <div class="nav-actions">
      <button class="btn btn-outline-white" onclick="openModal('loginModal')">Sign in</button>
      <button class="btn btn-primary" onclick="openModal('registerModal')">Get started free</button>
    </div>
  </nav>

  <!-- HERO CONTENT -->
  <div class="hero-content">
    <div class="hero-badge"><span class="badge-dot"></span>Secure Medical Appointment System</div>
    <h1 class="hero-title">Your Health,<br><span>Protected &amp; Scheduled.</span></h1>
    <p class="hero-sub">Book appointments with certified specialists. Your records are encrypted with AES-256, protected by two-factor authentication, and backed up automatically with Python.</p>
    <div class="hero-cta">
      <button class="btn btn-white btn-lg" onclick="openModal('registerModal')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Book an Appointment
      </button>
      <button class="btn btn-outline-white btn-lg" onclick="openModal('loginModal')">
        Sign in to Portal
      </button>
    </div>
    <div class="hero-stats">
      <div class="stat-item"><div class="stat-value">8+</div><div class="stat-label">Specialist Doctors</div></div>
      <div class="stat-item"><div class="stat-value">AES-256</div><div class="stat-label">Data Encryption</div></div>
      <div class="stat-item"><div class="stat-value">TOTP</div><div class="stat-label">Two-Factor Auth</div></div>
      <div class="stat-item"><div class="stat-value">Python</div><div class="stat-label">Auto-Backup System</div></div>
    </div>
  </div>

  <!-- SECURITY BAR -->
  <div class="sec-bar">
    <div class="sec-inner">
      <div class="sec-item"><div class="sec-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ecfe0" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>AES-256 Encryption</div>
      <div class="sec-item"><div class="sec-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ecfe0" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg></div>Google Authenticator 2FA</div>
      <div class="sec-item"><div class="sec-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ecfe0" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>Brute-Force Protection</div>
      <div class="sec-item"><div class="sec-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ecfe0" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div>Python Auto-Backup</div>
      <div class="sec-item"><div class="sec-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ecfe0" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/></svg></div>Full Audit Trail</div>
    </div>
  </div>
</div>

<!-- ═══════════════════ LOGIN MODAL ═══════════════════ -->
<div id="loginModal" class="modal-overlay" onclick="overlayClose(event,'loginModal')">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-logo"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
      <div class="modal-title">Welcome back</div>
      <div class="modal-sub">Sign in to CyberClinic</div>
      <button class="modal-close" onclick="closeModal('loginModal')">&times;</button>
    </div>
    <div class="modal-body">
      <?php if($flash&&$flash['type']==='success'): ?>
      <div class="alert alert-success"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>
      <form method="POST" action="<?= SITE_URL ?>/login.php">
        <?= csrfField() ?>
        <div class="role-tabs">
          <div class="role-tab active" onclick="setRole('patient',this)">Patient</div>
          <div class="role-tab" onclick="setRole('admin',this)">Admin</div>
        </div>
        <input type="hidden" name="role" id="roleInput" value="patient">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="you@example.com" required autofocus>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="loginPw" class="form-control" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
            <button type="button" class="pw-toggle" onclick="togglePw('loginPw')">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-login">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Sign In
        </button>
        <div class="demo-box">
          <strong>Demo credentials</strong><br>
          Admin: admin@cyberclinic.com / password<br>
          Patient: Register a new account
        </div>
      </form>
      <div class="modal-footer">
        Don't have an account? <a href="#" onclick="switchModal('loginModal','registerModal')">Create one free</a>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════ REGISTER MODAL ═══════════════════ -->
<div id="registerModal" class="modal-overlay" onclick="overlayClose(event,'registerModal')">
  <div class="modal modal-wide" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-logo"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
      <div class="modal-title">Create your account</div>
      <div class="modal-sub">All personal information is AES-256 encrypted</div>
      <button class="modal-close" onclick="closeModal('registerModal')">&times;</button>
    </div>
    <div class="modal-body">
      <form method="POST" action="<?= SITE_URL ?>/register.php">
        <?= csrfField() ?>
        <div class="form-section">Personal Information</div>
        <div class="form-row-2">
          <div class="form-group"><label>Full Name <span class="req">*</span></label><input type="text" name="full_name" class="form-control" placeholder="Juan dela Cruz" required></div>
          <div class="form-group"><label>Email <span class="req">*</span></label><input type="email" name="email" class="form-control" placeholder="you@example.com" required></div>
        </div>
        <div class="form-row-3">
          <div class="form-group"><label>Date of Birth <span class="req">*</span></label><input type="date" name="birthdate" class="form-control" max="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group"><label>Sex <span class="req">*</span></label>
            <select name="sex" class="form-control" required>
              <option value="">Select…</option>
              <option>Male</option><option>Female</option><option>Other</option><option>Prefer not to say</option>
            </select>
          </div>
          <div class="form-group"><label>Blood Type</label>
            <select name="blood_type" class="form-control">
              <option value="">Unknown</option>
              <option>A+</option><option>A−</option><option>B+</option><option>B−</option>
              <option>AB+</option><option>AB−</option><option>O+</option><option>O−</option>
            </select>
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label>Phone</label><input type="tel" name="phone" class="form-control" placeholder="+63 9XX XXX XXXX"></div>
          <div class="form-group"><label>Emergency Contact</label><input type="text" name="emergency_contact" class="form-control" placeholder="Name — Phone"></div>
        </div>
        <div class="form-row-2">
          <div class="form-group"><label>Home Address</label><input type="text" name="address" class="form-control" placeholder="Street, City, Province"></div>
          <div class="form-group"><label>Known Allergies</label><input type="text" name="allergies" class="form-control" placeholder="e.g. Penicillin — or leave blank"></div>
        </div>
        <div class="form-section">Account Security</div>
        <div class="form-row-2">
          <div class="form-group">
            <label>Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="password" id="regPw" class="form-control" placeholder="Min. 8 chars" required oninput="checkPwStr(this.value)">
              <button type="button" class="pw-toggle" onclick="togglePw('regPw')"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>
            <div class="pw-strength" id="pwStr"></div>
          </div>
          <div class="form-group">
            <label>Confirm Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="confirm_pw" id="regPw2" class="form-control" placeholder="Re-enter password" required>
              <button type="button" class="pw-toggle" onclick="togglePw('regPw2')"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
            </div>
          </div>
        </div>
        <div class="enc-notice">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Your personal information is encrypted with AES-256-CBC before being stored. Only you can see it.
        </div>
        <button type="submit" class="btn-login">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
          Create Secure Account
        </button>
      </form>
      <div class="modal-footer">
        Already have an account? <a href="#" onclick="switchModal('registerModal','loginModal')">Sign in</a>
      </div>
    </div>
  </div>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden'}
function closeModal(id){document.getElementById(id).classList.remove('open');document.body.style.overflow=''}
function overlayClose(e,id){if(e.target===document.getElementById(id))closeModal(id)}
function switchModal(from,to){closeModal(from);setTimeout(function(){openModal(to)},150)}
function setRole(r,el){document.getElementById('roleInput').value=r;document.querySelectorAll('.role-tab').forEach(function(t){t.classList.remove('active')});el.classList.add('active')}
function togglePw(id){var f=document.getElementById(id);f.type=f.type==='password'?'text':'password'}
function checkPwStr(pw){var s=0;if(pw.length>=8)s++;if(/[A-Z]/.test(pw))s++;if(/[a-z]/.test(pw))s++;if(/[0-9]/.test(pw))s++;if(/[\W_]/.test(pw))s++;var l=['','Weak','Fair','Good','Strong','Very Strong'],c=['','#ef4444','#f59e0b','#3b82f6','#22c55e','#16a34a'];var el=document.getElementById('pwStr');if(el){el.textContent=pw?l[s]:'';el.style.color=c[s]}}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeModal('loginModal');closeModal('registerModal')}})
<?php if($flash&&$flash['type']==='error'): ?>
openModal('loginModal');
<?php endif; ?>
</script>
</body>
</html>
