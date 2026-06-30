<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn())      redirect(SITE_URL.'/patient/dashboard.php');
if (isAdminLoggedIn()) redirect(SITE_URL.'/admin/dashboard.php');
if (isset($_GET['cancel_mfa'])) { unset($_SESSION['mfa_pending'],$_SESSION['mfa_pending_role']); redirect(SITE_URL.'/login.php'); }

$error = ''; $role = $_POST['role'] ?? 'patient';
$step  = $_SESSION['mfa_pending_role'] ?? null;

if ($step==='mfa' && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['step']??'')==='mfa') {
    verifyCsrf();
    $code=str_replace(' ','',trim($_POST['mfa_code']??'')); $pending=$_SESSION['mfa_pending']??[]; $valid=false;
    if (!empty($pending['secret'])&&verifyTotp($pending['secret'],$code)) { $valid=true; }
    elseif (!empty($pending['backup_codes'])) {
        $hashed=json_decode($pending['backup_codes'],true);
        if (is_array($hashed)) { $idx=verifyBackupCode($code,$hashed); if ($idx>=0) { array_splice($hashed,$idx,1); $tbl=($pending['role']==='admin')?'admins':'users'; getDB()->prepare("UPDATE $tbl SET mfa_backup_codes=? WHERE id=?")->execute([json_encode($hashed),$pending['id']]); $valid=true; } }
    }
    if ($valid) {
        if ($pending['role']==='admin') { $_SESSION['admin_id']=$pending['id']; $_SESSION['admin_name']=$pending['name']; $_SESSION['admin_email']=$pending['email']; $_SESSION['role']='admin'; }
        else { $_SESSION['user_id']=$pending['id']; $_SESSION['user_name']=$pending['name']; $_SESSION['user_email']=$pending['email']; $_SESSION['role']='patient'; }
        unset($_SESSION['mfa_pending'],$_SESSION['mfa_pending_role']);
        clearRateLimit($pending['email']); auditLog($pending['role'],(int)$pending['id'],'login_mfa_success');
        redirect($pending['role']==='admin'?SITE_URL.'/admin/dashboard.php':SITE_URL.'/patient/dashboard.php');
    } else { auditLog($pending['role']??'patient',(int)($pending['id']??0),'login_mfa_fail'); $error='Invalid authentication code. Please try again.'; }
}

if (!$step && $_SERVER['REQUEST_METHOD']==='POST' && (($_POST['step']??'')==='')) {
    verifyCsrf(); $email=$_POST['email']??''; $password=$_POST['password']??''; $role=$_POST['role']??'patient';
    if (empty($email)||empty($password)) { $error='Please fill in all fields.'; }
    elseif (!checkRateLimit($email)) { $error='Too many failed attempts. Please wait 15 minutes.'; }
    else {
        $db=getDB();
        if ($role==='admin') { $s=$db->prepare('SELECT * FROM admins WHERE email=? LIMIT 1'); $s->execute([$email]); }
        else { $s=$db->prepare('SELECT * FROM users WHERE email=? AND deleted_at IS NULL LIMIT 1'); $s->execute([$email]); }
        $user=$s->fetch();
        if ($user&&password_verify($password,$user['password'])) {
            clearRateLimit($email);
            $displayName=($role==='admin')?$user['full_name']:decryptField($user['full_name_enc']);
            if (!empty($user['mfa_enabled'])) {
                $_SESSION['mfa_pending']=['id'=>$user['id'],'name'=>$displayName,'email'=>$user['email'],'role'=>$role,'secret'=>!empty($user['mfa_secret'])?decryptField($user['mfa_secret']):'','backup_codes'=>$user['mfa_backup_codes']??''];
                $_SESSION['mfa_pending_role']='mfa'; auditLog($role,(int)$user['id'],'login_mfa_prompted'); redirect(SITE_URL.'/login.php');
            } else {
                if ($role==='admin') { $_SESSION['admin_id']=$user['id']; $_SESSION['admin_name']=$displayName; $_SESSION['admin_email']=$user['email']; $_SESSION['role']='admin'; auditLog('admin',(int)$user['id'],'login_success'); redirect(SITE_URL.'/admin/dashboard.php'); }
                else { $_SESSION['user_id']=$user['id']; $_SESSION['user_name']=$displayName; $_SESSION['user_email']=$user['email']; $_SESSION['role']='patient'; auditLog('patient',(int)$user['id'],'login_success'); redirect(SITE_URL.'/patient/dashboard.php'); }
            }
        } else { recordFailedLogin($email); auditLog($role,null,'login_fail'); $error='Invalid email or password.'; usleep(random_int(200000,500000)); }
    }
}
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — CyberClinic</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:opsz,wght@9..40,300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--navy:#0a1628;--primary:#1a6b8a;--primary-dark:#135470;--accent:#0ea5c9;--danger:#dc2626;--success:#16a34a}
html,body{height:100%;font-family:'DM Sans',sans-serif;overflow:hidden}

/* Full-screen hospital background */
.page{
  min-height:100vh;height:100vh;
  background:
    linear-gradient(135deg,rgba(10,22,40,.90) 0%,rgba(10,22,40,.75) 40%,rgba(14,60,80,.82) 100%),
    url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=1600&q=80&auto=format&fit=crop') center/cover no-repeat;
  display:flex;align-items:center;justify-content:center;padding:20px;position:relative;
}

/* Floating brand top-left */
.page-brand{
  position:absolute;top:28px;left:36px;
  display:flex;align-items:center;gap:12px;text-decoration:none;
  z-index:10;
}
.brand-logo{
  width:40px;height:40px;background:linear-gradient(135deg,var(--primary),var(--accent));
  border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
}
.brand-name{font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:#fff}
.brand-sub{font-size:10px;color:rgba(255,255,255,.4);letter-spacing:.08em;text-transform:uppercase}
.back-link{
  position:absolute;top:28px;right:36px;z-index:10;
  font-size:13px;color:rgba(255,255,255,.6);text-decoration:none;
  display:flex;align-items:center;gap:6px;transition:.15s;
}
.back-link:hover{color:#fff}

/* The card */
.login-card{
  background:#fff;border-radius:22px;
  width:100%;max-width:440px;
  box-shadow:0 32px 80px rgba(0,0,0,.4);
  overflow:hidden;
  animation:rise .4s cubic-bezier(.34,1.56,.64,1);
}
@keyframes rise{from{opacity:0;transform:translateY(24px) scale(.97)}to{opacity:1;transform:none}}

.card-header{
  background:linear-gradient(135deg,var(--navy) 0%,#1e3a52 100%);
  padding:30px 32px 26px;text-align:center;position:relative;
}
.card-header-logo{
  width:52px;height:52px;
  background:linear-gradient(135deg,var(--primary),var(--accent));
  border-radius:15px;display:flex;align-items:center;justify-content:center;
  margin:0 auto 14px;
}
.card-title{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:#fff;margin-bottom:4px}
.card-sub{font-size:13px;color:rgba(255,255,255,.5)}

/* MFA header override */
.mfa-icon{
  width:52px;height:52px;background:rgba(14,165,201,.25);border:2px solid rgba(14,165,201,.5);
  border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;
}

.card-body{padding:28px 32px 32px}

.role-tabs{display:flex;background:#f0f4f8;border-radius:8px;padding:4px;margin-bottom:22px;border:1px solid #dde5ed}
.role-tab{flex:1;text-align:center;padding:9px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;color:#4a6480;transition:all .2s}
.role-tab.active{background:#fff;color:var(--primary);box-shadow:0 1px 4px rgba(0,0,0,.08)}

.form-group{margin-bottom:17px}
.form-group label{display:block;font-size:12px;font-weight:600;color:#4a6480;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em}
.form-control{width:100%;padding:11px 14px;border:1.5px solid #dde5ed;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:14px;color:#0f2233;background:#fff;transition:.2s;outline:none}
.form-control:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(26,107,138,.12)}
.form-control::placeholder{color:#8aa5be}

.pw-wrap{position:relative}
.pw-wrap .form-control{padding-right:44px}
.pw-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#4a6480;padding:4px;transition:.15s}
.pw-toggle:hover{color:var(--primary)}

.btn-submit{
  width:100%;padding:13px 22px;border:none;border-radius:9px;
  background:linear-gradient(135deg,var(--primary),var(--primary-dark));
  color:#fff;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;
  cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;
  box-shadow:0 4px 16px rgba(26,107,138,.35);transition:all .2s;
}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 6px 22px rgba(26,107,138,.45)}

.alert{padding:11px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;border:1px solid transparent;display:flex;align-items:flex-start;gap:8px}
.alert-error{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
.alert-success{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
.alert-info{background:#e8f4f8;color:#135470;border-color:#9ecfe0}

.demo-box{background:#f8fafb;border:1px solid #dde5ed;border-radius:8px;padding:12px 14px;font-size:12px;color:#4a6480;margin-top:14px;line-height:2.1}

.divider{text-align:center;margin:18px 0 4px;font-size:13px;color:#4a6480}
.divider a{color:var(--primary);font-weight:600;text-decoration:none}
.divider a:hover{text-decoration:underline}

.mfa-input{
  font-size:28px;font-weight:700;text-align:center;letter-spacing:10px;
  padding:14px;font-family:'Courier New',monospace;
  color:var(--navy);border:2px solid #dde5ed;border-radius:10px;width:100%;
  transition:.2s;outline:none;
}
.mfa-input:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(26,107,138,.12)}
.mfa-hint{background:#e8f4f8;border:1px solid #9ecfe0;border-radius:8px;padding:10px 13px;font-size:12px;color:#135470;margin:12px 0}

/* Security badges at bottom of card */
.card-footer{
  background:#f8fafb;border-top:1px solid #eef2f6;
  padding:12px 32px;display:flex;justify-content:center;gap:20px;flex-wrap:wrap;
}
.sec-badge{display:flex;align-items:center;gap:5px;font-size:11px;color:#8aa5be;font-weight:500}
.sec-badge svg{flex-shrink:0}
</style>
</head>
<body>

<div class="page">
  <!-- Brand top-left -->
  <a href="<?= SITE_URL ?>/index.php" class="page-brand">
    <div class="brand-logo"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
    <div><div class="brand-name">CyberClinic</div><div class="brand-sub">Secure Medical</div></div>
  </a>
  <!-- Back link -->
  <a href="<?= SITE_URL ?>/index.php" class="back-link">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
    Back to home
  </a>

  <div class="login-card">
    <!-- CARD HEADER -->
    <?php if($step==='mfa'): ?>
    <div class="card-header">
      <div class="mfa-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0ea5c9" stroke-width="2.5"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18" stroke-width="3"/></svg></div>
      <div class="card-title">Verify Identity</div>
      <div class="card-sub">Enter the 6-digit code from your authenticator app</div>
    </div>
    <?php else: ?>
    <div class="card-header">
      <div class="card-header-logo"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
      <div class="card-title">Welcome back</div>
      <div class="card-sub">Sign in to your CyberClinic account</div>
    </div>
    <?php endif; ?>

    <!-- CARD BODY -->
    <div class="card-body">
      <?php if($error): ?>
      <div class="alert alert-error">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= sanitize($error) ?>
      </div>
      <?php endif; ?>
      <?php if($flash): ?>
      <div class="alert alert-<?= $flash['type'] ?>"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <?php if($step==='mfa'): ?>
      <!-- MFA FORM -->
      <form method="POST" autocomplete="off">
        <?= csrfField() ?><input type="hidden" name="step" value="mfa">
        <div class="form-group">
          <label>Authentication Code</label>
          <input type="text" name="mfa_code" class="mfa-input" placeholder="000 000"
            maxlength="7" autofocus inputmode="numeric" autocomplete="one-time-code">
        </div>
        <div class="mfa-hint">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:5px"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18" stroke-width="3"/></svg>
          Open Google Authenticator or Authy on your phone, or enter an 8-character backup code.
        </div>
        <button type="submit" class="btn-submit">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Verify &amp; Sign In
        </button>
      </form>
      <div class="divider" style="margin-top:16px">
        <a href="<?= SITE_URL ?>/login.php?cancel_mfa=1">&larr; Use a different account</a>
      </div>

      <?php else: ?>
      <!-- LOGIN FORM -->
      <form method="POST">
        <?= csrfField() ?>
        <div class="role-tabs">
          <div class="role-tab <?= $role==='patient'?'active':'' ?>" onclick="setRole('patient',this)">Patient</div>
          <div class="role-tab <?= $role==='admin'?'active':'' ?>" onclick="setRole('admin',this)">Admin</div>
        </div>
        <input type="hidden" name="role" id="roleInput" value="<?= sanitize($role) ?>">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" class="form-control" placeholder="you@example.com"
            value="<?= sanitize($_POST['email']??'') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label>Password</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="pwField" class="form-control"
              placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" required>
            <button type="button" class="pw-toggle" onclick="togglePw()">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-submit">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Sign In to CyberClinic
        </button>
        <div class="demo-box">
          <strong>Demo credentials</strong><br>
          Admin &mdash; admin@cyberclinic.com / password<br>
          Patient &mdash; Register a new account to test
        </div>
      </form>
      <div class="divider">
        Don't have an account? <a href="<?= SITE_URL ?>/register.php">Create one free</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- CARD FOOTER — security badges -->
    <div class="card-footer">
      <div class="sec-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>AES-256 Encrypted</div>
      <div class="sec-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18" stroke-width="2.5"/></svg>2FA Protected</div>
      <div class="sec-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Brute-Force Protected</div>
    </div>
  </div>
</div>

<script>
function setRole(r,el){document.getElementById('roleInput').value=r;document.querySelectorAll('.role-tab').forEach(function(t){t.classList.remove('active')});el.classList.add('active')}
function togglePw(){var f=document.getElementById('pwField');f.type=f.type==='password'?'text':'password'}
var mi=document.querySelector('.mfa-input');
if(mi)mi.addEventListener('input',function(){var v=this.value.replace(/\D/g,'');if(v.length>3)v=v.slice(0,3)+' '+v.slice(3,6);this.value=v});
</script>
</body>
</html>
