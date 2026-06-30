#!/usr/bin/env python3
"""
CyberClinic Security Scanner
==============================
Python-based audit log analyzer that detects:
- Brute-force login attempts by IP
- MFA code abuse
- Off-hours access patterns
- Rate limit violations
- Sustained attack patterns

Requirements: Python 3.6+
    pip install mysql-connector-python

Usage:
    python3 security_scan.py --host localhost --db cyberclinic_secure
                              --user root --pass "" --hours 24 --report security_report.json
"""

import argparse
import datetime
import json
import sys
from collections import defaultdict


def log(msg, level='INFO'):
    ts = datetime.datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    icons = {'INFO': '✓', 'WARN': '⚠', 'ERROR': '✗', 'ALERT': '🚨'}
    print(f'[{ts}] [{level}] {icons.get(level, "·")} {msg}')


def connect_db(host, db, user, password):
    try:
        import mysql.connector
        conn = mysql.connector.connect(host=host, database=db, user=user,
                                       password=password, charset='utf8mb4')
        log(f'Connected to {db}@{host}')
        return conn
    except ImportError:
        log('mysql-connector-python not installed — using demo data.', 'WARN')
        log('Install with: pip install mysql-connector-python', 'WARN')
        return None
    except Exception as e:
        log(f'DB connection failed: {e}', 'ERROR')
        return None


def get_demo_logs():
    """Return sample log data when DB is unavailable."""
    now = datetime.datetime.now()
    return [
        {'id':1,'actor_type':'patient','actor_id':5,'action':'login_fail','ip_address':'192.168.1.100','created_at':now-datetime.timedelta(minutes=8)},
        {'id':2,'actor_type':'patient','actor_id':5,'action':'login_fail','ip_address':'192.168.1.100','created_at':now-datetime.timedelta(minutes=6)},
        {'id':3,'actor_type':'patient','actor_id':5,'action':'login_fail','ip_address':'192.168.1.100','created_at':now-datetime.timedelta(minutes=4)},
        {'id':4,'actor_type':'system','actor_id':None,'action':'login_rate_limited','ip_address':'192.168.1.100','created_at':now-datetime.timedelta(minutes=3)},
        {'id':5,'actor_type':'patient','actor_id':3,'action':'login_success','ip_address':'10.0.0.5','created_at':now-datetime.timedelta(minutes=2)},
        {'id':6,'actor_type':'patient','actor_id':3,'action':'mfa_enabled','ip_address':'10.0.0.5','created_at':now-datetime.timedelta(minutes=1)},
    ]


def fetch_logs(conn, hours):
    if conn is None:
        return get_demo_logs()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL %s HOUR) ORDER BY created_at DESC", (hours,))
    return cursor.fetchall()


def analyze_brute_force(logs):
    ip_fails   = defaultdict(list)
    user_fails = defaultdict(list)
    alerts     = []
    for e in logs:
        if e['action'] in ('login_fail', 'login_mfa_fail'):
            ip_fails[e.get('ip_address', 'unknown')].append(e)
            if e.get('actor_id'):
                user_fails[e['actor_id']].append(e)
    for ip, fails in ip_fails.items():
        if len(fails) >= 5:
            alerts.append({'severity':'HIGH','type':'brute_force_ip','message':f'Brute-force from IP {ip}: {len(fails)} failed logins','ip':ip,'count':len(fails)})
    for uid, fails in user_fails.items():
        if len(fails) >= 3:
            alerts.append({'severity':'MEDIUM','type':'user_targeted','message':f'Account #{uid} targeted: {len(fails)} failures','user_id':uid,'count':len(fails)})
    return {'ip_failure_counts':{ip:len(v) for ip,v in ip_fails.items()},'user_failure_counts':{str(k):len(v) for k,v in user_fails.items()},'alerts':alerts}


def analyze_mfa(logs):
    mfa_fails = [e for e in logs if e.get('action')=='login_mfa_fail']
    mfa_ok    = [e for e in logs if e.get('action')=='login_mfa_success']
    alerts    = []
    ip_mfa    = defaultdict(int)
    for e in mfa_fails:
        ip_mfa[e.get('ip_address','unknown')] += 1
    for ip, count in ip_mfa.items():
        if count >= 3:
            alerts.append({'severity':'HIGH','type':'mfa_brute_force','message':f'MFA brute-force from IP {ip}: {count} failures','ip':ip,'count':count})
    return {'total_mfa_events':len(mfa_fails)+len(mfa_ok),'mfa_failures':len(mfa_fails),'mfa_successes':len(mfa_ok),'alerts':alerts}


def analyze_patterns(logs):
    alerts    = []
    off_hours = []
    for e in logs:
        if e.get('action')=='login_success':
            ts = e.get('created_at')
            if isinstance(ts, datetime.datetime) and (ts.hour < 6 or ts.hour >= 22):
                off_hours.append({'actor_id':e.get('actor_id'),'ip':e.get('ip_address'),'time':ts.strftime('%H:%M')})
    if len(off_hours) >= 3:
        alerts.append({'severity':'LOW','type':'off_hours_access','message':f'{len(off_hours)} logins outside business hours (06:00-22:00)','details':off_hours[:5]})
    rate_limited = [e for e in logs if e.get('action')=='login_rate_limited']
    if len(rate_limited) >= 5:
        alerts.append({'severity':'HIGH','type':'sustained_attack','message':f'Sustained attack: {len(rate_limited)} rate-limit events','count':len(rate_limited)})
    return {'off_hours_logins':len(off_hours),'rate_limited_events':len(rate_limited),'alerts':alerts}


def security_score(brute, mfa, patterns):
    score = 100
    deductions = {'HIGH':20,'MEDIUM':10,'LOW':5}
    for alert in brute['alerts']+mfa['alerts']+patterns['alerts']:
        score -= deductions.get(alert.get('severity','LOW'),5)
    return max(0, score)


def build_report(args, logs, brute, mfa, patterns):
    all_alerts = brute['alerts']+mfa['alerts']+patterns['alerts']
    score      = security_score(brute, mfa, patterns)
    grade      = 'A' if score>=90 else 'B' if score>=75 else 'C' if score>=60 else 'D' if score>=40 else 'F'
    recs       = []
    if brute['alerts']:       recs.append('Consider IP-based blocking for repeated failed logins.')
    if mfa['mfa_failures']>0: recs.append('Verify users have properly synced their authenticator clocks.')
    if score < 70:            recs.append('Security score is LOW. Review all HIGH alerts immediately.')
    if not all_alerts:        recs.append('No active threats detected. Continue regular monitoring.')
    return {
        'generated_at'    : datetime.datetime.now().isoformat(),
        'database'        : args.db,
        'analysis_period' : f'Last {args.hours} hours',
        'security_score'  : score,
        'security_grade'  : grade,
        'summary'         : {'total_events':len(logs),'total_alerts':len(all_alerts),'high_alerts':sum(1 for a in all_alerts if a.get('severity')=='HIGH'),'medium_alerts':sum(1 for a in all_alerts if a.get('severity')=='MEDIUM'),'low_alerts':sum(1 for a in all_alerts if a.get('severity')=='LOW')},
        'brute_force_analysis': brute,
        'mfa_analysis'        : mfa,
        'access_pattern_analysis': patterns,
        'all_alerts'      : all_alerts,
        'recommendations' : recs,
    }


def print_report(report):
    score = report['security_score']
    grade = report['security_grade']
    color = {'A':'\033[92m','B':'\033[92m','C':'\033[93m','D':'\033[91m','F':'\033[91m'}.get(grade,'')
    reset = '\033[0m'
    print('\n' + '='*60)
    print('  CyberClinic Security Scan Report')
    print(f'  Generated: {report["generated_at"]}')
    print(f'  Period   : {report["analysis_period"]}')
    print('='*60)
    print(f'\n  Security Score: {color}{score}/100  Grade: {grade}{reset}\n')
    s = report['summary']
    print(f'  Events analyzed : {s["total_events"]}')
    print(f'  Alerts found    : {s["total_alerts"]}  (HIGH: {s["high_alerts"]}  MEDIUM: {s["medium_alerts"]}  LOW: {s["low_alerts"]})')
    if report['all_alerts']:
        print('\n  ALERTS:')
        for a in report['all_alerts']:
            sev = a.get('severity','INFO')
            ico = {'HIGH':'🚨','MEDIUM':'⚠️ ','LOW':'ℹ️ '}.get(sev,'·')
            print(f'  {ico} [{sev}] {a["message"]}')
    if report['recommendations']:
        print('\n  RECOMMENDATIONS:')
        for r in report['recommendations']:
            print(f'  → {r}')
    print('\n' + '='*60)


def main():
    parser = argparse.ArgumentParser(description='CyberClinic Security Scanner')
    parser.add_argument('--host',   default='localhost',            help='MySQL host')
    parser.add_argument('--db',     default='cyberclinic_secure',  help='Database name')
    parser.add_argument('--user',   default='root',                 help='MySQL user')
    parser.add_argument('--pass',   default='', dest='password',   help='MySQL password')
    parser.add_argument('--hours',  default=24, type=int,           help='Hours to analyze')
    parser.add_argument('--report', default='security_report.json', help='Report output file')
    args = parser.parse_args()

    log('CyberClinic Security Scanner v4.0')
    log(f'Analyzing last {args.hours} hours of activity...')

    conn    = connect_db(args.host, args.db, args.user, args.password)
    logs    = fetch_logs(conn, args.hours)
    log(f'Fetched {len(logs)} audit log entries')

    brute    = analyze_brute_force(logs)
    mfa      = analyze_mfa(logs)
    patterns = analyze_patterns(logs)
    report   = build_report(args, logs, brute, mfa, patterns)

    try:
        with open(args.report, 'w') as f:
            json.dump(report, f, indent=2, default=str)
        log(f'Report saved: {args.report}')
    except Exception as e:
        log(f'Could not save report: {e}', 'WARN')

    print_report(report)
    if conn:
        conn.close()

    high_count = report['summary']['high_alerts']
    if high_count > 0:
        log(f'{high_count} HIGH severity alert(s) require immediate attention!', 'ALERT')
        sys.exit(2)
    sys.exit(0)


if __name__ == '__main__':
    main()
