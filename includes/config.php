<?php
// ================================================================
//  CyberClinic Secure System | includes/config.php | v4.0
// ================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('SITE_URL',       'http://localhost/cyberclinic');
define('SITE_NAME',      'CyberClinic');
define('SITE_TAGLINE',   'Secure. Smart. Caring.');
define('DB_HOST',        'localhost');
define('DB_NAME',        'cyberclinic_secure');
define('DB_USER',        'root');
define('DB_PASS',        '');
define('APP_ENC_KEY',    'CyberClinic@2024_SecureKey32Chr!');
define('APP_ENC_CIPHER', 'AES-256-CBC');
define('SESSION_LIFETIME',   3600);
define('RL_MAX_ATTEMPTS',    5);
define('RL_WINDOW_SECONDS',  900);
define('BACKUP_DIR',    __DIR__ . '/../backup/');
define('BACKUP_PYTHON', 'C:/Users/Stela/AppData/Local/Programs/Python/Python314/python.exe');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('[CyberClinic] DB: '.$e->getMessage());
            die('<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>Service Unavailable</h2><p>Database connection failed. Check <code>includes/config.php</code></p></div>');
        }
    }
    return $pdo;
}

function secureSessionStart(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>false,'httponly'=>true,'samesite'=>'Strict']);
        session_name('CCSS');
        session_start();
        if (empty($_SESSION['_init'])) { session_regenerate_id(true); $_SESSION['_init']=true; }
        if (!empty($_SESSION['_last']) && time()-$_SESSION['_last'] > SESSION_LIFETIME) {
            session_unset(); session_destroy(); secureSessionStart(); return;
        }
        $_SESSION['_last'] = time();
        $uaHash = hash('sha256', $_SERVER['HTTP_USER_AGENT']??'');
        if (!empty($_SESSION['_ua']) && $_SESSION['_ua'] !== $uaHash) {
            session_unset(); session_destroy(); secureSessionStart(); return;
        }
        $_SESSION['_ua'] = $uaHash;
    }
}
secureSessionStart();

// AES-256-CBC
function encryptField(string $plain): string {
    if ($plain==='') return '';
    $iv=$iv=random_bytes(16);
    $c=openssl_encrypt($plain, APP_ENC_CIPHER, APP_ENC_KEY, OPENSSL_RAW_DATA, $iv);
    return $c===false?'':base64_encode($iv.$c);
}
function decryptField(string $enc): string {
    if ($enc==='') return '';
    $raw=base64_decode($enc,true);
    if ($raw===false||strlen($raw)<17) return '';
    $p=openssl_decrypt(substr($raw,16), APP_ENC_CIPHER, APP_ENC_KEY, OPENSSL_RAW_DATA, substr($raw,0,16));
    return $p!==false?$p:'';
}

// CSRF
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token']=bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrfField(): string { return '<input type="hidden" name="csrf_token" value="'.csrfToken().'">'; }
function verifyCsrf(): void {
    if (!hash_equals(csrfToken(), $_POST['csrf_token']??'')) {
        auditLog('system',null,'csrf_fail');
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>Invalid Request</h2><p>Security token mismatch. Please go back and try again.</p></div>');
    }
}

// Rate Limiting
function checkRateLimit(string $email): bool {
    $db=getDB(); $key=hash('sha256',$email.':'.getClientIP());
    $db->prepare('DELETE FROM rate_limit WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)')->execute([RL_WINDOW_SECONDS]);
    $r=$db->prepare('SELECT attempts FROM rate_limit WHERE identifier=?'); $r->execute([$key]); $row=$r->fetch();
    return !($row && $row['attempts']>=RL_MAX_ATTEMPTS);
}
function recordFailedLogin(string $email): void {
    $db=getDB(); $key=hash('sha256',$email.':'.getClientIP());
    $db->prepare('INSERT INTO rate_limit (identifier,attempts,window_start) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(window_start<DATE_SUB(NOW(),INTERVAL ? SECOND),1,attempts+1),window_start=IF(window_start<DATE_SUB(NOW(),INTERVAL ? SECOND),NOW(),window_start)')->execute([$key,RL_WINDOW_SECONDS,RL_WINDOW_SECONDS]);
}
function clearRateLimit(string $email): void {
    $db=getDB(); $key=hash('sha256',$email.':'.getClientIP());
    $db->prepare('DELETE FROM rate_limit WHERE identifier=?')->execute([$key]);
}

// Audit Log
function auditLog(string $actorType, ?int $actorId=null, string $action='', ?string $targetType=null, ?int $targetId=null, ?string $detail=null): void {
    try {
        getDB()->prepare('INSERT INTO audit_log (actor_type,actor_id,action,target_type,target_id,detail,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?)')->execute([$actorType,$actorId,$action,$targetType,$targetId,$detail,getClientIP(),substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);
    } catch (Exception $e) { error_log('[CyberClinic] AuditLog: '.$e->getMessage()); }
}

// TOTP MFA — Fixed buffer overflow for 32-char secrets
function generateTotpSecret(): string {
    $chars='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s='';
    for ($i=0;$i<32;$i++) $s.=$chars[random_int(0,31)];
    return $s;
}
function totpUri(string $secret, string $email, string $issuer=SITE_NAME): string {
    return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($email).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
}
function generateTotpCode(string $secret, int $timeStep): string {
    $b32='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $secret=strtoupper(trim($secret));
    $key=''; $buf=0; $bits=0;
    foreach (str_split($secret) as $c) {
        $pos=strpos($b32,$c); if ($pos===false) continue;
        $buf=(($buf & 0x1FFF)<<5)|$pos; $bits+=5;
        if ($bits>=8) { $bits-=8; $key.=chr(($buf>>$bits)&0xFF); }
    }
    $hi=(int)($timeStep/0x100000000); $lo=$timeStep&0xFFFFFFFF;
    $hash=hash_hmac('sha1',pack('NN',$hi,$lo),$key,true);
    $off=ord($hash[19])&0x0F;
    $code=(((ord($hash[$off])&0x7F)<<24)|((ord($hash[$off+1])&0xFF)<<16)|((ord($hash[$off+2])&0xFF)<<8)|(ord($hash[$off+3])&0xFF))%1000000;
    return str_pad((string)$code,6,'0',STR_PAD_LEFT);
}
function verifyTotp(string $secret, string $code): bool {
    $code=preg_replace('/\s+/','',$code);
    if (!ctype_digit($code)||strlen($code)!==6) return false;
    $t=(int)floor(time()/30);
    for ($i=-1;$i<=1;$i++) { if (hash_equals(generateTotpCode($secret,$t+$i),$code)) return true; }
    return false;
}
function generateBackupCodes(): array {
    $plain=[]; $hashed=[];
    for ($i=0;$i<8;$i++) { $c=strtoupper(bin2hex(random_bytes(4))); $plain[]=$c; $hashed[]=password_hash($c,PASSWORD_BCRYPT,['cost'=>10]); }
    return ['plain'=>$plain,'hashed'=>$hashed];
}
function verifyBackupCode(string $code, array $hashed): int {
    $code=strtoupper(preg_replace('/\s+/','',$code));
    foreach ($hashed as $i=>$h) { if (password_verify($code,$h)) return $i; }
    return -1;
}

// Python Backup
function runPythonBackup(string $type='manual', string $adminName='system'): array {
    $script=__DIR__.'/../scripts/backup.py';
    if (!file_exists($script)) return ['success'=>false,'message'=>'Backup script not found.'];
    $backupDir=rtrim(BACKUP_DIR,'/');
    $cmd=escapeshellcmd(BACKUP_PYTHON).' '.escapeshellarg($script).' --host '.escapeshellarg(DB_HOST).' --db '.escapeshellarg(DB_NAME).' --user '.escapeshellarg(DB_USER).' --pass '.escapeshellarg(DB_PASS).' --outdir '.escapeshellarg($backupDir).' --type '.escapeshellarg($type).' 2>&1';
    $output=[]; $exitCode=0;
    exec($cmd,$output,$exitCode);
    $success=($exitCode===0); $msg=implode("\n",$output);
    try {
        $filename=''; foreach ($output as $line) { if (preg_match('/backup_\S+\.sql\.gz\.enc/',$line,$m)) $filename=$m[0]; }
        $fileSize=0; $fullPath=$backupDir.'/'.$filename;
        if ($filename&&file_exists($fullPath)) $fileSize=filesize($fullPath);
        getDB()->prepare('INSERT INTO backup_log (filename,file_size,backup_type,status,created_by) VALUES (?,?,?,?,?)')->execute([$filename?:'unknown',$fileSize,$type,$success?'success':'failed',$adminName]);
    } catch (Exception $e) {}
    return ['success'=>$success,'message'=>$msg];
}
function checkPythonAvailable(): array {
    foreach (['python3','python'] as $cmd) {
        $ver=shell_exec(escapeshellcmd($cmd).' --version 2>&1');
        if ($ver&&stripos($ver,'python')!==false) return ['available'=>true,'command'=>$cmd,'version'=>trim($ver)];
    }
    return ['available'=>false,'command'=>'','version'=>''];
}

// Notifications
function createNotification(int $userId, string $type, string $title, string $message): void {
    try { getDB()->prepare('INSERT INTO notifications (user_id,type,title,message) VALUES (?,?,?,?)')->execute([$userId,$type,$title,$message]); } catch (Exception $e) {}
}
function getUnreadCount(int $userId): int {
    $r=getDB()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0'); $r->execute([$userId]); return (int)$r->fetchColumn();
}

// Password & Auth
function isStrongPassword(string $pw): bool {
    return strlen($pw)>=8&&preg_match('/[A-Z]/',$pw)&&preg_match('/[a-z]/',$pw)&&preg_match('/[0-9]/',$pw)&&preg_match('/[\W_]/',$pw);
}
function isLoggedIn(): bool      { return !empty($_SESSION['user_id'])  && $_SESSION['role']==='patient'; }
function isAdminLoggedIn(): bool { return !empty($_SESSION['admin_id']) && $_SESSION['role']==='admin'; }
function requireLogin(): void  { if (!isLoggedIn())      redirect(SITE_URL.'/login.php'); }
function requireAdmin(): void  { if (!isAdminLoggedIn()) redirect(SITE_URL.'/login.php'); }
function redirect(string $url): void { header('Location: '.$url); exit; }
function sanitize($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function getClientIP(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) { $ip=trim(explode(',',$_SERVER[$k])[0]); if (filter_var($ip,FILTER_VALIDATE_IP)) return $ip; }
    }
    return '0.0.0.0';
}
function flashMessage(string $type, string $msg): void { $_SESSION['flash']=['type'=>$type,'message'=>$msg]; }
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) { $f=$_SESSION['flash']; unset($_SESSION['flash']); return $f; } return null;
}
function statusBadge(string $s): string {
    $map=['pending'=>'badge-pending','approved'=>'badge-approved','completed'=>'badge-completed','cancelled'=>'badge-cancelled','active'=>'badge-approved'];
    return '<span class="badge '.($map[$s]??'badge').'">'.ucfirst(sanitize($s)).'</span>';
}
function computeAge(?string $dob): ?int {
    if (!$dob) return null; return (new DateTime($dob))->diff(new DateTime('today'))->y;
}
function formatFileSize(int $bytes): string {
    if ($bytes<1024) return $bytes.' B'; if ($bytes<1048576) return round($bytes/1024,1).' KB'; return round($bytes/1048576,2).' MB';
}
