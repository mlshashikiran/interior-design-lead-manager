#!/usr/bin/env python3
"""Read-only acceptance for real WordPress legacy admin redirects. Builder: Shashi."""
import argparse
import json
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--config', required=True)
    p.add_argument('--output', required=True)
    p.add_argument('--execute', action='store_true')
    p.add_argument('--confirm')
    a = p.parse_args()
    c = json.loads(Path(a.config).read_text())
    target = urllib.parse.urlsplit(c['url'])
    if (target.scheme != 'http' or target.hostname != '127.0.0.1' or target.username or target.password
            or c.get('environment') != 'staging' or not c['database'].startswith('idlm_test_production_')
            or not str(Path(c['fixture']).resolve()).startswith('/private/tmp/')
            or not (Path(c['fixture']) / 'wordpress/wp-config.php').is_file()):
        p.error('Only an owned temporary loopback staging fixture is permitted')
    routes = {'interior-design-lead-manager': 'dashboard', 'idlm_leads_list': 'leads',
              'idlm_kanban': 'pipeline', 'idlm_quotations': 'quotations', 'idlm_capi_logs': 'capi-logs',
              'idlm_audience_sync_logs': 'audience-logs', 'idlm_settings': 'settings', 'idlm_users_access': 'users'}
    if not a.execute:
        print(json.dumps({'legacy_routes': len(routes), 'read_only': True, 'executed': False}))
        return 0
    if a.confirm != 'qualify-idlm-legacy-routes':
        p.error('Require --confirm=qualify-idlm-legacy-routes')
    actors = {role: next(x for x in c['actors'] if x['role'] == role) for role in ['administrator', 'sales_executive', 'idlm_pre_sales']}
    if any(not x.get('admin_cookie') for x in actors.values()):
        p.error('Require synthetic WordPress admin cookies')
    opener = urllib.request.build_opener(NoRedirect)

    def request(route, role):
        query = {'page': route, 'quote_search': 'synthetic', 'redirect_to': 'https://example.invalid/blocked'}
        req = urllib.request.Request(c['url'] + '/wp-admin/admin.php?' + urllib.parse.urlencode(query),
                                     headers={'Cookie': actors[role]['admin_cookie']})
        try:
            with opener.open(req, timeout=20) as r:
                return r.status, r.headers.get('Location', '')
        except urllib.error.HTTPError as e:
            return e.code, e.headers.get('Location', '')
        except urllib.error.URLError:
            return 0, ''

    results = []
    for route, view in routes.items():
        status, location = request(route, 'administrator')
        parsed = urllib.parse.urlsplit(location)
        query = urllib.parse.parse_qs(parsed.query)
        passed = (status == 302 and parsed.netloc == target.netloc and query.get('idlm_view') == [view]
                  and query.get('quote_search') == ['synthetic'] and 'redirect_to' not in query)
        results.append({'case': route + ':administrator', 'status': status, 'pass': passed})
    for route, role, expected_status, expected_view in [('idlm_kanban', 'sales_executive', 302, 'pipeline'),
                                                      ('idlm_settings', 'sales_executive', 403, None),
                                                      ('idlm_kanban', 'idlm_pre_sales', 302, 'dashboard')]:
        status, location = request(route, role)
        parsed = urllib.parse.urlsplit(location)
        query = urllib.parse.parse_qs(parsed.query)
        passed = status == expected_status and (expected_view is None or (parsed.netloc == target.netloc and query.get('idlm_view') == [expected_view]))
        results.append({'case': route + ':' + role, 'status': status, 'pass': passed})
    report = {'builder': 'Shashi', 'artifact_sha256': c['artifact_sha256'], 'wordpress': c['wordpress'],
              'php_server': c['php_server'], 'real_wp_admin_entrypoint': True, 'read_only': True,
              'cases': results, 'errors': sum(not r['pass'] for r in results), 'credentials_or_response_bodies': False}
    Path(a.output).write_text(json.dumps(report, indent=2) + '\n')
    print(json.dumps({'cases': len(results), 'errors': report['errors']}, indent=2))
    return 1 if report['errors'] else 0


if __name__ == '__main__':
    raise SystemExit(main())
