#!/usr/bin/env bash
# Verify every en-GB language INI in the FlexiContent repo has a matching
# th-TH counterpart with identical key set + intact placeholders.
#
# Usage:  ./tools/i18n-qa.sh              # full report
#         ./tools/i18n-qa.sh --summary    # one-line summary only
#         ./tools/i18n-qa.sh --strict     # exit 1 on any failure

set -uo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
MODE="${1:-full}"
STRICT=""

if [ "$MODE" = "--strict" ]; then STRICT="--strict"; MODE="full"; fi
[ "$MODE" = "--summary" ] && SUMMARY=1 || SUMMARY=0

total=0; pass=0; fail=0; missing=0

cd "$REPO"

while IFS= read -r EN; do
	total=$((total+1))
	TH="${EN//en-GB/th-TH}"

	if [ ! -f "$TH" ]; then
		[ $SUMMARY -eq 0 ] && printf "MISSING  %s\n" "$EN"
		missing=$((missing+1)); fail=$((fail+1)); continue
	fi

	en_uniq=$(grep -oE '^[A-Z][A-Z0-9_]+' "$EN" 2>/dev/null | sort -u | wc -l | tr -d ' ')
	th_uniq=$(grep -oE '^[A-Z][A-Z0-9_]+' "$TH" 2>/dev/null | sort -u | wc -l | tr -d ' ')

	miss_k=$(comm -23 <(grep -oE '^[A-Z][A-Z0-9_]+' "$EN" | sort -u) \
	                   <(grep -oE '^[A-Z][A-Z0-9_]+' "$TH" | sort -u) | wc -l | tr -d ' ')
	extra_k=$(comm -13 <(grep -oE '^[A-Z][A-Z0-9_]+' "$EN" | sort -u) \
	                    <(grep -oE '^[A-Z][A-Z0-9_]+' "$TH" | sort -u) | wc -l | tr -d ' ')

	if [ "$en_uniq" = "$th_uniq" ] && [ "$miss_k" = "0" ] && [ "$extra_k" = "0" ]; then
		pass=$((pass+1))
		[ $SUMMARY -eq 0 ] && printf "PASS     %4d keys  %s\n" "$en_uniq" "$EN"
	else
		fail=$((fail+1))
		[ $SUMMARY -eq 0 ] && printf "FAIL     en=%d th=%d miss=%d extra=%d  %s\n" \
			"$en_uniq" "$th_uniq" "$miss_k" "$extra_k" "$EN"
	fi
done < <(find . -name "en-GB.*.ini" -type f \
	-not -path "*.claude/worktrees/*" \
	-not -path "*flexicontentadminhelper*" 2>/dev/null | sort)

# Placeholder integrity check via python (counts mismatches across all pairs).
phmismatch="(skipped — no python3)"
if command -v python3 >/dev/null 2>&1; then
	phmismatch=$(REPO="$REPO" python3 - <<'PY'
import os, re
root = os.environ.get('REPO', '.')
en_files = []
for d, _, fs in os.walk(root):
    if '/.claude/worktrees/' in d or 'flexicontentadminhelper' in d: continue
    for f in fs:
        if f.startswith('en-GB.') and f.endswith('.ini'):
            en_files.append(os.path.join(d, f))
def parse(p):
    out = {}
    try:
        with open(p, encoding='utf-8', errors='replace') as fh:
            for line in fh:
                m = re.match(r'^([A-Z][A-Z0-9_]+)="(.*)"\s*$', line)
                if m: out[m.group(1)] = m.group(2)
    except Exception: pass
    return out
def ph(s): return tuple(sorted(re.findall(r'%[0-9]?\$?[sdf]', s)))
mm = 0
for en in en_files:
    th = en.replace('en-GB', 'th-TH')
    if not os.path.exists(th): continue
    en_d, th_d = parse(en), parse(th)
    for k, v in en_d.items():
        if k in th_d and ph(v) != ph(th_d[k]): mm += 1
print(mm)
PY
)
fi

echo
echo "============================================"
printf "Total en-GB files:   %d\n" "$total"
printf "PASS (key parity):   %d\n" "$pass"
printf "FAIL (key parity):   %d\n" "$fail"
printf "  ...missing TH:     %d\n" "$missing"
printf "Placeholder mismatches across all pairs: %s\n" "$phmismatch"
echo "============================================"

if [ "$STRICT" = "--strict" ] && [ "$fail" -gt 0 ]; then exit 1; fi
exit 0
