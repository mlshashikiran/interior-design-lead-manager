#!/usr/bin/env python3
"""Guarded multi-user reads against an owned loopback fixture. Builder: Shashi."""
import argparse
import concurrent.futures
import hashlib
import json
import math
import statistics
import time
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path


def percentile(values, fraction):
    ordered = sorted(values)
    return ordered[max(0, math.ceil(len(ordered) * fraction) - 1)] if ordered else None


def summarize(samples):
    times = [s['duration_ms'] for s in samples]
    errors = sum(s['outcome'] != 'success' for s in samples)
    return {'requests': len(samples), 'errors': errors,
            'error_rate_percent': round(errors * 100 / len(samples), 4) if samples else 100,
            'latency_ms': {'p50': percentile(times, .50), 'p95': percentile(times, .95),
                           'p99': percentile(times, .99), 'max': max(times) if times else None},
            'response_bytes_max': max((s['bytes'] for s in samples), default=0)}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--config', required=True)
    parser.add_argument('--profile', required=True)
    parser.add_argument('--requests', type=int, default=200)
    parser.add_argument('--concurrency', type=int, default=10)
    parser.add_argument('--action', choices=['mixed', 'idlm_get_leads_table', 'idlm_get_rep_tasks', 'idlm_get_lead_details', 'idlm_get_lead_activity', 'idlm_get_kanban_leads', 'idlm_get_dashboard_stats', 'idlm_filter_quotation_list'], default='mixed')
    parser.add_argument('--duration-seconds', type=int, default=0, help='Pace requests over 0..600 seconds')
    parser.add_argument('--execute', action='store_true')
    parser.add_argument('--confirm')
    parser.add_argument('--output', required=True)
    args = parser.parse_args()
    if not 1 <= args.requests <= 5000 or not 1 <= args.concurrency <= 50 or not 0 <= args.duration_seconds <= 600:
        parser.error('Requests must be 1..5000, concurrency 1..50 and duration 0..600 seconds')
    config = json.loads(Path(args.config).read_text())
    parts = urllib.parse.urlsplit(config['url'])
    fixture = Path(config['fixture']).resolve()
    if (parts.scheme != 'http' or parts.hostname != '127.0.0.1' or parts.username or parts.password
            or config.get('environment') != 'staging'
            or not config['database'].startswith('idlm_test_production_')
            or not str(fixture).startswith('/private/tmp/')
            or not (fixture / 'wordpress/wp-config.php').is_file()):
        parser.error('Only an owned temporary staging fixture on loopback is permitted')
    actors = config.get('actors', [])
    if not actors or len(actors) > 50 or any(not a.get('cookie') or not a.get('nonce') for a in actors):
        parser.error('Require 1..50 synthetic cookie/nonce actors')
    if args.action in ['idlm_get_kanban_leads', 'idlm_get_dashboard_stats', 'idlm_filter_quotation_list']:
        actors = [a for a in actors if a['role'] != 'idlm_pre_sales']
    if args.action in ['idlm_get_lead_details', 'idlm_get_lead_activity']:
        actors = [a for a in actors if a.get('lead_id')]
    if not actors:
        parser.error('No eligible actors for this action')
    plan = {'schema': 'idlm-team-load/v1', 'builder': 'Shashi', 'profile': args.profile,
            'actors': len(actors), 'action': args.action, 'requests': args.requests, 'concurrency': args.concurrency,
            'read_only': True, 'paced_duration_seconds': args.duration_seconds, 'contains_credentials_or_response_bodies': False,
            'php_server': config['php_server'], 'wordpress': config['wordpress'],
            'artifact_sha256': config['artifact_sha256']}
    if not args.execute:
        print(json.dumps(dict(plan, executed=False), indent=2))
        return 0
    if args.confirm != 'qualify-idlm-team-load':
        parser.error('Execution requires --confirm=qualify-idlm-team-load')
    run_id = hashlib.sha256((args.profile + str(time.time_ns())).encode()).hexdigest()[:16]

    scheduled_start = time.perf_counter()

    def request(index):
        delay = scheduled_start + args.duration_seconds * index / args.requests - time.perf_counter()
        if delay > 0:
            time.sleep(delay)
        actor = actors[index % len(actors)]
        choices = ['idlm_get_leads_table', 'idlm_get_rep_tasks']
        if actor.get('lead_id'):
            choices += ['idlm_get_lead_details', 'idlm_get_lead_activity']
        if actor['role'] != 'idlm_pre_sales':
            choices += ['idlm_get_kanban_leads', 'idlm_get_dashboard_stats']
        action = choices[(index // len(actors)) % len(choices)] if args.action == 'mixed' else args.action
        data = {'action': action, 'nonce': actor['nonce'], 'page': '1', 'per_page': '25',
                'date_basis': 'assigned_at', 'view': 'attention', 'lead_id': actor.get('lead_id', 0)}
        if action == 'idlm_get_dashboard_stats':
            # The deterministic seeder spans this historical year; exercise populated aggregates.
            data.update(period='custom', start_date='2025-09-01', end_date='2026-09-01')
        url = config['url'] + '/wp-admin/admin-ajax.php'
        payload = urllib.parse.urlencode(data).encode()
        if action == 'idlm_filter_quotation_list':
            if not actor.get('quote_nonce'):
                raise ValueError('Quotation scenario requires a dedicated synthetic quote nonce')
            data.update(nonce=actor['quote_nonce'], quote_page='1')
            url += '?' + urllib.parse.urlencode(data)
            payload = None
        req = urllib.request.Request(url, data=payload,
                                     headers={'Cookie': actor['cookie'], 'X-IDLM-Qualification': run_id})
        started = time.perf_counter()
        outcome, size = 'transport_error', 0
        try:
            with urllib.request.urlopen(req, timeout=45) as response:
                raw = response.read(4 * 1024 * 1024 + 1)
                size = len(raw)
                if size > 4 * 1024 * 1024:
                    outcome = 'response_budget_exceeded'
                else:
                    body = json.loads(raw)
                    outcome = 'success' if response.status == 200 and body.get('success') is True else 'application_error'
        except urllib.error.HTTPError:
            outcome = 'http_error'
        except (ValueError, UnicodeDecodeError):
            outcome = 'invalid_response'
        except (OSError, TimeoutError):
            pass
        return {'action': action, 'role': actor['role'], 'outcome': outcome, 'bytes': size,
                'duration_ms': round((time.perf_counter() - started) * 1000, 3)}

    started = time.perf_counter()
    with concurrent.futures.ThreadPoolExecutor(max_workers=args.concurrency) as pool:
        samples = list(pool.map(request, range(args.requests)))
    server = []
    metrics = fixture / 'logs/metrics.ndjson'
    if metrics.exists():
        for line in metrics.read_text().splitlines():
            row = json.loads(line)
            if row.get('run_id') == run_id:
                server.append(row)
    groups = {}
    for sample in samples:
        groups.setdefault(sample['action'], []).append(sample)
    result = dict(plan, executed=True, duration_seconds=round(time.perf_counter() - started, 3),
                  summary=summarize(samples), actions={k: summarize(v) for k, v in groups.items()})
    result['server'] = {'samples': len(server), 'peak_memory_bytes': max((s['peak_bytes'] for s in server), default=None),
                        'queries_p95': percentile([s['queries'] for s in server], .95),
                        'database_ms_p95': percentile([s['database_ms'] for s in server], .95)}
    result['roles'] = {role: summarize([s for s in samples if s['role'] == role])
                       for role in sorted({s['role'] for s in samples})}
    Path(args.output).write_text(json.dumps(result, indent=2) + '\n')
    print(json.dumps({'profile': args.profile, 'concurrency': args.concurrency,
                      'requests': len(samples), 'errors': result['summary']['errors'],
                      'p95_ms': result['summary']['latency_ms']['p95']}, indent=2))
    return 1 if result['summary']['errors'] else 0


if __name__ == '__main__':
    raise SystemExit(main())
