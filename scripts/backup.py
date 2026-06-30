#!/usr/bin/env python3
"""CyberClinic Secure Backup System v4.0 - Windows XAMPP compatible"""
import argparse, datetime, gzip, hashlib, json, os, platform, shutil, subprocess, sys, time

RETENTION_DAYS = 30

def log(msg, level='INFO'):
    ts = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    print(f'[{ts}] [{level}] {msg}')
    sys.stdout.flush()

def find_mysqldump():
    if platform.system() == 'Windows':
        paths = [
            r'C:\xampp\mysql\bin\mysqldump.EXE',
            r'C:\xampp\mysql\bin\mysqldump.exe',
            r'D:\xampp\mysql\bin\mysqldump.exe',
            r'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe',
        ]
        for p in paths:
            if os.path.isfile(p):
                log(f'Found mysqldump: {p}')
                return p
        found = shutil.which('mysqldump')
        if found:
            log(f'Found mysqldump in PATH: {found}')
            return found
    else:
        found = shutil.which('mysqldump')
        if found:
            log(f'Found mysqldump: {found}')
            return found
    log('mysqldump NOT found!', 'ERROR')
    return None

def dump_database(host, db, user, password, output_file):
    mysqldump = find_mysqldump()
    if not mysqldump:
        return False

    # Use simple format: mysqldump [options] database_name
    # This works with all MySQL/MariaDB versions including older XAMPP
    cmd = [
        mysqldump,
        f'--host={host}',
        f'--user={user}',
        '--single-transaction',
        '--routines',
        '--triggers',
        '--hex-blob',
        '--default-character-set=utf8mb4',
        db,   # <-- database name at the end, no --databases flag
    ]
    if password:
        cmd.append(f'--password={password}')

    log(f'Dumping database: {db}@{host}')
    try:
        with open(output_file, 'w', encoding='utf-8', errors='replace') as f:
            result = subprocess.run(cmd, stdout=f, stderr=subprocess.PIPE, timeout=300)
        if result.returncode != 0:
            err = result.stderr.decode('utf-8', errors='replace')
            log(f'mysqldump error: {err}', 'ERROR')
            return False
        size = os.path.getsize(output_file)
        if size < 100:
            log(f'Dump too small ({size} bytes) - check DB name and credentials', 'ERROR')
            return False
        log(f'Dump OK: {size:,} bytes')
        return True
    except subprocess.TimeoutExpired:
        log('Timeout after 5 minutes', 'ERROR')
        return False
    except Exception as e:
        log(f'Dump error: {e}', 'ERROR')
        return False

def compress_file(input_file, output_file):
    log('Compressing...')
    try:
        with open(input_file, 'rb') as fi, gzip.open(output_file, 'wb', compresslevel=9) as fo:
            shutil.copyfileobj(fi, fo)
        log(f'Compressed: {os.path.getsize(output_file):,} bytes')
        return True
    except Exception as e:
        log(f'Compress error: {e}', 'ERROR')
        return False

def compute_sha256(file_path):
    sha256 = hashlib.sha256()
    try:
        with open(file_path, 'rb') as f:
            for chunk in iter(lambda: f.read(65536), b''):
                sha256.update(chunk)
        return sha256.hexdigest()
    except Exception as e:
        log(f'Hash error: {e}', 'ERROR')
        return ''

def encrypt_file(input_file, output_file, key):
    try:
        from cryptography.hazmat.primitives.ciphers import Cipher, algorithms, modes
        from cryptography.hazmat.backends import default_backend
        import secrets
        key_bytes = key.encode()[:32].ljust(32, b'\0')
        iv = secrets.token_bytes(16)
        with open(input_file, 'rb') as f:
            data = f.read()
        pad = 16 - len(data) % 16
        data += bytes([pad] * pad)
        enc = Cipher(algorithms.AES(key_bytes), modes.CBC(iv), backend=default_backend()).encryptor()
        encrypted = enc.update(data) + enc.finalize()
        with open(output_file, 'wb') as f:
            f.write(b'CYBERCLINIC_ENC_V1\n')
            f.write(iv)
            f.write(encrypted)
        log('AES-256 encrypted OK')
        return True
    except ImportError:
        log('cryptography not installed - saving without encryption', 'WARN')
        log('Run: pip install cryptography', 'WARN')
        shutil.copy2(input_file, output_file)
        return True
    except Exception as e:
        log(f'Encrypt error: {e}', 'WARN')
        shutil.copy2(input_file, output_file)
        return True

def write_hash_file(outdir, filename, file_hash):
    try:
        with open(os.path.join(outdir, filename + '.sha256'), 'w') as f:
            f.write(f'# CyberClinic Integrity\n# {datetime.datetime.now().isoformat()}\nSHA256: {file_hash}\n')
        log('SHA-256 integrity file saved')
    except Exception as e:
        log(f'Hash file error: {e}', 'WARN')

def cleanup_old(outdir, days):
    cutoff = time.time() - (days * 86400)
    removed = 0
    try:
        for f in os.listdir(outdir):
            if f.startswith('backup_'):
                fp = os.path.join(outdir, f)
                if os.path.isfile(fp) and os.path.getmtime(fp) < cutoff:
                    os.remove(fp)
                    removed += 1
    except Exception as e:
        log(f'Cleanup error: {e}', 'WARN')
    log(f'Cleanup: {removed} old file(s) removed')

def update_manifest(outdir, entry):
    mp = os.path.join(outdir, 'backup_manifest.json')
    data = []
    if os.path.exists(mp):
        try:
            with open(mp) as f:
                data = json.load(f)
        except:
            data = []
    data.append(entry)
    if len(data) > 50:
        data = data[-50:]
    try:
        with open(mp, 'w') as f:
            json.dump(data, f, indent=2)
    except:
        pass

def main():
    p = argparse.ArgumentParser(description='CyberClinic Backup')
    p.add_argument('--host',        default='localhost')
    p.add_argument('--db',          default='cyberclinic_secure')
    p.add_argument('--user',        default='root')
    p.add_argument('--pass',        default='', dest='password')
    p.add_argument('--outdir',      default='../backup')
    p.add_argument('--type',        default='manual',
                   choices=['manual', 'scheduled', 'pre_update'])
    p.add_argument('--encrypt-key', default='CyberClinic@2024_Backup!')
    args = p.parse_args()

    log('=' * 60)
    log('CyberClinic Secure Backup System v4.0')
    log(f'Python {sys.version.split()[0]} on {platform.system()} {platform.release()}')
    log(f'Type: {args.type} | DB: {args.db}@{args.host}')
    log('=' * 60)

    os.makedirs(args.outdir, exist_ok=True)

    ts       = datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
    base     = f'backup_{args.db}_{ts}'
    sql_file = os.path.join(args.outdir, base + '.sql')
    gz_file  = os.path.join(args.outdir, base + '.sql.gz')
    enc_file = os.path.join(args.outdir, base + '.sql.gz.enc')
    start    = time.time()

    log('Step 1/4: Dumping database...')
    if not dump_database(args.host, args.db, args.user, args.password, sql_file):
        log('BACKUP FAILED at Step 1', 'ERROR')
        sys.exit(1)

    log('Step 2/4: Compressing...')
    if not compress_file(sql_file, gz_file):
        log('BACKUP FAILED at Step 2', 'ERROR')
        sys.exit(1)
    os.remove(sql_file)

    log('Step 3/4: Encrypting...')
    encrypt_file(gz_file, enc_file, args.encrypt_key)
    if os.path.exists(gz_file):
        os.remove(gz_file)

    log('Step 4/4: SHA-256 integrity hash...')
    fhash = compute_sha256(enc_file)
    if fhash:
        log(f'SHA-256: {fhash}')
        write_hash_file(args.outdir, os.path.basename(enc_file), fhash)

    cleanup_old(args.outdir, RETENTION_DAYS)

    elapsed = round(time.time() - start, 2)
    fsize   = os.path.getsize(enc_file) if os.path.exists(enc_file) else 0

    update_manifest(args.outdir, {
        'timestamp'  : datetime.datetime.now().isoformat(),
        'filename'   : os.path.basename(enc_file),
        'file_size'  : fsize,
        'sha256'     : fhash,
        'type'       : args.type,
        'database'   : args.db,
        'elapsed_sec': elapsed,
        'status'     : 'success'
    })

    log('=' * 60)
    log(f'BACKUP SUCCESSFUL in {elapsed}s')
    log(f'File: {enc_file}')
    log(f'Size: {fsize:,} bytes')
    log(f'Hash: {fhash}')
    log('=' * 60)
    print(os.path.basename(enc_file))

if __name__ == '__main__':
    main()
