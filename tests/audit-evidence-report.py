"""Read-only evidence aggregation for the 2026-10-06 audit. Author: Shashi."""
import collections
import csv
import hashlib
import json
from pathlib import Path
import re
import shutil
import zipfile

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs/audit-2026-10-06'
E = OUT / 'evidence'

raw = (E / 'plugin-check.json').read_text()
diagnostics = []
for match in re.finditer(r'FILE: (.*?)\n(\[.*?\])(?=\n\nFILE:|\s*$)', raw, re.S):
    for item in json.loads(match[2]):
        diagnostics.append({'file': match[1], **item})
summary = {
    'author': 'Shashi', 'raw_format': 'FILE header followed by one JSON array per file',
    'diagnostic_count': len(diagnostics),
    'by_type': dict(collections.Counter(x['type'] for x in diagnostics)),
    'by_code': dict(collections.Counter(x['code'] for x in diagnostics)),
    'file_count': len(set(x['file'] for x in diagnostics)),
    'interpretation': 'Static diagnostics require triage; counts are not confirmed vulnerability counts.',
}
(E / 'plugin-check-summary.json').write_text(json.dumps(summary, indent=2) + '\n')
(E / 'plugin-check-normalized.json').write_text(json.dumps(diagnostics, indent=2) + '\n')

manifest = json.loads((E / 'source-manifest.json').read_text())
changed = []
for entry in manifest['files']:
    p = ROOT / entry['path']
    if not p.is_file() or hashlib.sha256(p.read_bytes()).hexdigest() != entry['sha256']:
        changed.append(entry['path'])
(E / 'source-preservation.json').write_text(json.dumps({'author':'Shashi','checked_files':len(manifest['files']),'changed_original_files':changed},indent=2)+'\n')

candidate = E / 'candidate/interior-design-lead-manager-1.102.42.zip'
installed = Path('/private/tmp/idlm-audit-20261006/wordpress/wp-content/plugins/interior-design-lead-manager')
differences = []
with zipfile.ZipFile(candidate) as z:
    paths = set()
    for name in z.namelist():
        if name.endswith('/'): continue
        relative = name.split('/', 1)[1]
        paths.add(relative)
        p = installed / relative
        if not p.is_file() or p.read_bytes() != z.read(name): differences.append(relative)
    extras = sorted(str(p.relative_to(installed)) for p in installed.rglob('*') if p.is_file() and str(p.relative_to(installed)) not in paths)
(E / 'installed-artifact-identity.json').write_text(json.dumps({'author':'Shashi','zip_sha256':hashlib.sha256(candidate.read_bytes()).hexdigest(),'files':len(paths),'modified_or_missing':differences,'extra_files':extras},indent=2)+'\n')

for extension in ['csv','pdf','xlsx']:
    name = f'Q-2026-000001-R01-Synthetic-24-Audit.{extension}'
    shutil.copyfile(Path('/Users/shashi_protecto/Downloads')/name, E/name)
for p in Path('/private/tmp/idlm-audit-20261006').glob('pdf-page-*.png'):
    shutil.copyfile(p,E/p.name)
shutil.copyfile('/private/tmp/idlm-audit-20261006/php-ui-errors.log',E/'php-ui-errors.log')

rows = []
def row(identifier, operation, status, environment, evidence, limit=''):
    rows.append(dict(id=identifier,operation=operation,status=status,environment=environment,evidence=evidence,limitation=limit))
for profile, filename in [('FAST','fast-suite.txt'),('NATIVE','native-suite.txt'),('ARTIFACT-NATIVE','artifact-native-suite.txt')]:
    i = 0
    for line in (E/filename).read_text().splitlines():
        if line.startswith(('PASS ','FAIL ')):
            i+=1; status, name=line.split(' ',1)
            row(f'{profile}-{i:03}',name,status,'PHP8.4.14 doubles' if profile=='FAST' else 'PHP8.4.14 / WP7.1.2 / MySQL8.4 InnoDB',f'evidence/{filename}', 'Assertion scope only; read the test, not a broad workflow certification. Artifact profile repeats 17 native cases.')
for file in ['core-synthetic-results.json','core-native-results.json']:
    d=json.loads((OUT/file).read_text())
    for result in d['results']:
        name=result.get('id',result.get('scenario'))
        row(name.split(' ')[0],name,result['status'],'Disposable native DB' if 'native' in file else 'PHP doubles',file,'Specific invariant; see CORE-REVIEW.md for source/native distinctions.')
for line in (E/'quotation-probes.jsonl').read_text().splitlines():
    d=json.loads(line); row(d['scenario'],d['scenario'],d['status'],'PHP doubles','evidence/quotation-probes.jsonl','Helper/SQL/schema contract only; see QUOTATION-REVIEW.md.')
d=json.loads((E/'quotation-revision-binding-observation.json').read_text())
row('QUO-S09','New quotation revision binding', 'FAIL','PHP handler doubles','evidence/quotation-revision-binding-observation.json','Synthetic in-memory records; no real victim data.')
integrations = [
 ('Direct forbidden table rejection','PASS'),('Mixed JOIN forbidden table rejection','FAIL'),('Authentication column projection denial','FAIL'),('Emergency-disabled MCP','PASS'),('Incomplete backup rejected before writes','FAIL'),('Insert failure requests rollback','PASS'),('Disabled queued Interakt suppressed','FAIL'),('Deleted lead Interakt suppressed','PASS'),('Wrong webhook HMAC rejected','PASS'),('Scoped secret encrypted round trip','PASS'),('Current Google default manual retry delivery','FAIL'),('Meta batch processes all messages','FAIL'),('Meta replay idempotency','FAIL')]
for i,(name,status) in enumerate(integrations,1): row(f'INT-S{i:02}',name,status,'PHP doubles and mocked HTTP','INTEGRATION-SECURITY-REVIEW.md','Recorder/helper result; actual providers and native restore not exercised.')

ui=[('UI-01','Administrator login and workspace/list24','PASS'),('UI-02','Details/activity/WhatsApp/task drawer tabs','PASS'),('UI-03','Blank task error and focus target','PASS'),('UI-04','Desktop task drawer at1440x1000','PASS'),('UI-05','Mobile task form at390x844 remains usable','FAIL'),('UI-06','Ordinary quantity2 accepted by native form validation','FAIL'),('UI-07','Quantity2.01 saves Draft R01 and total2372','PASS'),('UI-08','Saved revision CSV download and totals','PASS'),('UI-09','PDF download, two-page visual/text totals','PASS'),('UI-10','XLSX download and workbook totals','PASS'),('UI-11','Wrong fixture email visible login error','PASS'),('UI-12','Representative navigation hides admin controls','PASS'),('UI-13','Fresh representative reads only own12 leads','FAIL'),('UI-14','Drawer task controls have programmatic labels','FAIL'),('UI-15','Quotation downloads produce no PHP warnings','FAIL')]
ui.extend([('UI-16','Asynchronous search24-to1 and retained input','PASS'),('UI-17','Valid subscriber denied CRM by actual UI','PASS'),('UI-18','Observed browser console has no errors/warnings','PASS')])
for identifier,name,status in ui: row(identifier,name,status,'Installed ZIP, synthetic24-lead UI, WP7.1.2/PHP8.4.14','UI-REVIEW.md','Bounded browser observation; no all-role/slow-network guarantee.')
for identifier,name,status,evidence in [
 ('REL-S01','Version fields aligned','PASS','evidence/release-preflight.json'),('REL-S02','Runtime syntax301PHP files','PASS','evidence/php-lint.json'),('REL-S03','431 packaged files match installed artifact','PASS','evidence/installed-artifact-identity.json'),('REL-S04','Byte reproducible independent ZIP rebuild','FAIL','evidence/reproducibility.json'),('REL-S05','Candidate passes Plugin Check without diagnostics','FAIL','evidence/plugin-check-summary.json'),('REL-S06','Clean committed candidate with exact CI attestation','BLOCKED','evidence/candidate-verification.txt'),('REL-S07','Untracked vendor upstream archive provenance independently attested','NOT RUN','RELEASE.md'),('REL-S08','Supported PHP7.4/8.1/8.4 x WP6.6/latest CI executed','BLOCKED','RELEASE.md'),('REL-S09','Declared WP5.8 minimum compatibility','NOT RUN','RELEASE.md')]: row(identifier,name,status,'Local packaging / PHP8.4.14',evidence,'No CI attestation inferred from local passing tests.')

operations = ['frontend authentication/recovery/accounts','lead intake identity/conflict review','assignment/pre-sales/handoff','lead edit/stage reversal/tasks/comments','lead list/drawer/filter/pagination','dashboard revenue/date scope','inbound webhook/feed','CAPI/Google/Meta/LinkedIn delivery','WhatsApp events/batch/replies','quotation save/autosave/revision/numbering','catalogue import/export/snapshot','quote print/PDF/CSV/XLSX','attachment upload/retrieve/delete','queue scheduling/retry/cancel','MCP stdio/HTTP/SQL grants','backup/restore/reset/retention']
dimensions = ['malformed/oversized/Unicode and date boundaries','all roles/mixed roles/disabled/revoked caps and ownership','missing/expired/cross-user nonce and credential rotation','cross-object identifiers through actual HTTP','duplicate clicks/replays/retries and stale state','partial DB/downstream failure and recovery','concurrent requests/overlapping jobs and large data']
for i,op in enumerate(operations,1):
    for j,dim in enumerate(dimensions,1):
        row(f'GAP-{i:02}-{j}',f'{op}: complete {dim} matrix','NOT RUN','Required disposable end-to-end matrix','SCENARIOS.md','Some individual samples above ran; the complete operation-specific dimension remains unverified. Not a claim that no sample was tested.')
for i,op in enumerate(['Historical supported upgrades and rerun after migration failure','Native backup round trip/incomplete rejection/worker fencing/rollback','Deactivation/reactivation jobs and pending work','Multisite activation/network scope/site isolation','Missing PDF/ZIP/GD/crypto extension behavior','WP-Cron fallback/Action Scheduler unavailable and stalled runner','Large catalogue/lead/log/PDF resource and timeout budgets','Full keyboard focus trap/escape/reopening and contrast audit','Admin/frontend same-filter parity and scope overrides','HTTP download headers/cache/nonces and full ownership matrix','Native conflict review operator resolution and PII erasure','Historical settings/catalogue/quote migration preservation','Real provider sandbox acceptance with synthetic data and consent']):
    row(f'GAP-S{i+1:02}',op,'NOT RUN','Required isolated environment','SCENARIOS.md','No production credentials or customer data permitted; local mocks cannot establish provider acceptance.')
row('NA-01','Real customer messages/audiences/production mutation','NOT APPLICABLE','User expressly prohibited','README.md','Excluded from audit scope; synthetic provider sandbox acceptance remains separate.')
with (OUT/'scenario-matrix.csv').open('w',newline='') as f:
    w=csv.DictWriter(f,fieldnames=rows[0].keys());w.writeheader();w.writerows(rows)
counts=dict(collections.Counter(x['status'] for x in rows))
(E/'scenario-counts.json').write_text(json.dumps({'author':'Shashi','row_count':len(rows),'statuses':counts,'warning':'Assertion rows plus explicit broad gap rows; counts are not code coverage or unique end-to-end workflows. 17 artifact-native rows repeat native cases.'},indent=2)+'\n')
print(json.dumps({'plugin_check':summary,'original_files_changed':changed,'installed_differences':differences,'installed_extras':extras,'scenarios':counts},indent=2))
