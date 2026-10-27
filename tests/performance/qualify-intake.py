#!/usr/bin/env python3
"""Guarded synthetic inbound/deduplication rehearsal. Builder: Shashi."""
import argparse
import concurrent.futures
import json
import secrets
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path
from math import ceil


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--config', required=True)
    p.add_argument('--output', required=True)
    p.add_argument('--execute', action='store_true')
    p.add_argument('--confirm')
    args = p.parse_args()
    c = json.loads(Path(args.config).read_text())
    url = urllib.parse.urlsplit(c['url'])
    if (url.scheme != 'http' or url.hostname != '127.0.0.1' or url.username or url.password
            or c.get('environment') != 'staging' or not c['database'].startswith('idlm_test_production_')
            or not str(Path(c['fixture']).resolve()).startswith('/private/tmp/')
            or not (Path(c['fixture']) / 'wordpress/wp-config.php').is_file()):
        p.error('Only an owned temporary loopback staging fixture is permitted')
    if not args.execute:
        print(json.dumps({'unique_submissions': 90, 'duplicate_submissions': 10, 'paced_seconds': 60,
                          'concurrency': 5, 'executed': False}))
        return 0
    if args.confirm != 'qualify-idlm-synthetic-intake' or not c.get('intake_key'):
        p.error('Require private fixture key and --confirm=qualify-idlm-synthetic-intake')
    run = secrets.token_hex(6)
    started = time.perf_counter()

    def request(index, duplicate=False):
        if not duplicate:
            delay = started + index * 60 / 90 - time.perf_counter()
            if delay > 0:
                time.sleep(delay)
        payload = {'first_name': 'SYNTHETIC-INTAKE-' + run + '-' + str(index),
                   'email': 'qualification+' + run + '-' + str(index) + '@performance.invalid',
                   'platform': 'website'}
        req = urllib.request.Request(c['url'] + '/?rest_route=/interior-design-lead-manager/v1/webhook',
                                     data=json.dumps(payload).encode(),
                                     headers={'Content-Type': 'application/json', 'X-IDLM-API-Key': c['intake_key']})
        t = time.perf_counter()
        try:
            with urllib.request.urlopen(req, timeout=20) as response:
                body = json.loads(response.read(65536))
                passed = response.status == 200 and body.get('success') is True
                passed = passed and (body.get('matched_existing') is True if duplicate else body.get('created') is True)
                return passed, round((time.perf_counter() - t) * 1000, 3), body.get('lead_id') if passed else None
        except (urllib.error.URLError, ValueError, OSError):
            return False, round((time.perf_counter() - t) * 1000, 3), None

    with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
        unique = list(pool.map(request, range(90)))
        repeated = list(pool.map(lambda i: request(i, True), range(10)))
    samples = unique + repeated
    times = sorted(s[1] for s in samples)
    result = {'builder': 'Shashi', 'run': run, 'artifact_sha256': c['artifact_sha256'],
              'php_server': c['php_server'], 'wordpress': c['wordpress'], 'synthetic_only': True,
              'unique_submissions': 90, 'duplicate_submissions': 10,
              'accepted_unique': sum(s[0] for s in unique), 'matched_duplicates': sum(s[0] for s in repeated),
              'distinct_accepted_ids': len({s[2] for s in samples if s[0]}),
              'errors': sum(not s[0] for s in samples), 'duration_seconds': round(time.perf_counter() - started, 3),
              'p95_ms': times[ceil(len(times) * .95) - 1], 'p99_ms': times[ceil(len(times) * .99) - 1],
              'credentials_or_response_bodies_in_report': False, 'provider_delivery_tested': False}
    Path(args.output).write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps(result, indent=2))
    return 1 if result['errors'] or result['distinct_accepted_ids'] != 90 else 0


if __name__ == '__main__':
    raise SystemExit(main())
