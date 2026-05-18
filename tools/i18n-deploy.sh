#!/usr/bin/env bash
# Deploy every th-TH language INI from this repo to a live Joomla install,
# mirroring the relative path under <joomla_root>.
#
# Usage:  ./tools/i18n-deploy.sh                    # default target
#         ./tools/i18n-deploy.sh /path/to/joomla    # custom target
#
# Also copies the top-level com_flexicontent translations to
# `<root>/language/th-TH/` and `<root>/administrator/language/th-TH/`
# so Joomla 4+'s primary lookup finds them.

set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
TARGET_ROOT="${1:-/Users/pisan/Sites/joomla54}"

if [ ! -d "$TARGET_ROOT" ]; then
	echo "ERROR: target Joomla root missing: $TARGET_ROOT" >&2
	exit 1
fi

cd "$REPO"
copied=0

while IFS= read -r SRC; do
	REL="${SRC#./}"
	DEST="$TARGET_ROOT/$REL"
	mkdir -p "$(dirname "$DEST")"
	cp "$SRC" "$DEST"
	copied=$((copied+1))
done < <(find . -name "th-TH.*.ini" -type f \
	-not -path "*.claude/worktrees/*" 2>/dev/null | sort)

SITE_TH="site/language/th-TH/th-TH.com_flexicontent.ini"
if [ -f "$SITE_TH" ]; then
	mkdir -p "$TARGET_ROOT/language/th-TH"
	cp "$SITE_TH" "$TARGET_ROOT/language/th-TH/"
fi

for f in admin/language/th-TH/th-TH.com_flexicontent.ini admin/language/th-TH/th-TH.com_flexicontent.sys.ini; do
	if [ -f "$f" ]; then
		mkdir -p "$TARGET_ROOT/administrator/language/th-TH"
		cp "$f" "$TARGET_ROOT/administrator/language/th-TH/"
	fi
done

echo
printf "Deployed %d th-TH file(s) → %s\n" "$copied" "$TARGET_ROOT"
echo "Joomla root-level frontend: $TARGET_ROOT/language/th-TH/th-TH.com_flexicontent.ini"
echo "Joomla root-level admin:    $TARGET_ROOT/administrator/language/th-TH/th-TH.com_flexicontent*.ini"
echo
echo "Next: Joomla admin → System → Maintenance → Clear Cache, then refresh."
