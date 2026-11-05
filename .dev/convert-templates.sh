#!/usr/bin/env bash
# Converts the default theme's templates to Plates, from the *.template.php
# files as a commit has them. Safe to run as often as needed: each run starts
# again from the old file, so the result depends only on the commit and the
# converter.
#
#   .dev/convert-templates.sh Who Recent                 from HEAD
#   .dev/convert-templates.sh --from ea5ae213e Who       from another commit
#   .dev/convert-templates.sh --from ea5ae213e --all --skip Stats
#
# For each template it restores the old file, converts it with
# .dev/plates/convert.php into Themes/default/templates/<Template>/, applies
# the few fix-ups the converter cannot make on its own, removes the old file,
# drops the empty lines the conversion leaves where HTML does not need them
# (.dev/plates/tidy.php), and runs php-cs-fixer over the result.
#
# Convert callers before the templates they call: a converted template reaches
# either kind through $this->subTemplate(), while an old one calling a
# template_*() function breaks once that function is gone.
#
# Needs git and perl on the host; PHP runs wherever $SMF_RUNNER says.
set -euo pipefail

. "$(dirname -- "${BASH_SOURCE[0]}")/lib.sh"

parse_runner_args "$@"

FROM='HEAD'
ALL=''
SKIP=()
TEMPLATES=()

while [ $# -gt 0 ]; do
	case "$1" in
		--from) FROM="$2"; shift 2 ;;
		--from=*) FROM="${1#*=}"; shift ;;
		--all) ALL=1; shift ;;
		--skip) SKIP+=("$2"); shift 2 ;;
		--skip=*) SKIP+=("${1#*=}"); shift ;;
		--docker|--local) shift ;;
		-h|--help) sed -n '2,22p' "${BASH_SOURCE[0]}"; exit 0 ;;
		-*) die "unknown option: $1" ;;
		*) TEMPLATES+=("$1"); shift ;;
	esac
done

cd "$BOARD_DIR"

theme='Themes/default'

# The old templates, so the converter knows the parameter names of templates
# that have already been converted. Inside the checkout, where the container
# can read them too; ignored by git.
signatures='.dev/plates/signatures'
rm -rf "$signatures"
mkdir -p "$signatures"
trap 'rm -rf "$signatures"' EXIT

while IFS= read -r path; do
	git show "$FROM:$path" > "$signatures/$(basename -- "$path")"
done < <(git ls-tree --name-only "$FROM" "$theme/" | grep '\.template\.php$')

if [ -n "$ALL" ]; then
	for path in "$signatures"/*.template.php; do
		TEMPLATES+=("$(basename -- "$path" .template.php)")
	done
fi

files=()

for t in ${TEMPLATES[@]+"${TEMPLATES[@]}"}; do
	for s in ${SKIP[@]+"${SKIP[@]}"}; do
		[ "$t" = "$s" ] && continue 2
	done

	[ -f "$signatures/$t.template.php" ] || die "$FROM has no $theme/$t.template.php"

	cp "$signatures/$t.template.php" "$theme/$t.template.php"
	rm -rf "$theme/templates/$t"
	files+=("$theme/$t.template.php")
done

[ ${#files[@]} -gt 0 ] || die "nothing to convert; name the templates, or pass --all"

# The fallbacks Xml defines for when no index template is loaded are defined
# with the other legacy functions in Sources/TemplateFunctions.php.
if [ -f "$theme/Xml.template.php" ]; then
	perl -0pi -e 's{/\*\n \* This is just to hold off some errors if people are stupid\.\n \*/\nif \(!function_exists\(\x27template_button_strip\x27\)\) \{\n.*?\n\}\n\n}{}s' "$theme/Xml.template.php"

	if grep -q 'hold off some errors' "$theme/Xml.template.php"; then
		die "could not remove Xml's fallbacks"
	fi
fi

run_php .dev/plates/convert.php --signatures="$signatures" "${files[@]}"

# Applies a fix-up with perl, and fails if what it should have removed is still there.
fixup() {
	local file=$1 perl=$2 check=$3

	[ -f "$file" ] || return 0
	perl -0pi -e "$perl" "$file"

	if grep -qF -- "$check" "$file"; then
		die "fix-up did not apply to $file: $check"
	fi
}

# The moderation centre's blocks, called through a variable inside an echo.
fixup "$theme/templates/ModerationCenter/moderation_center.php" \
	's/<\?php \$block_function = \x27template_\x27 \. \$block; \?>//; s/function_exists\(\$block_function\) \? \$block_function\(\) : \x27\x27/\$this->hasSubTemplate(\$block) ? \$this->subTemplate(\$block) : \x27\x27/' \
	'$block_function'

# Returned its HTML for a list column; a Plates template outputs it instead.
fixup "$theme/templates/ModerationCenter/user_watch_post_callback.php" \
	's/<\?php return \$output_html; \?>/<?php echo \$output_html; ?>/' \
	'return $output_html'

# The quick buttons are always output; template_quickbuttons() returns them when asked.
fixup "$theme/templates/index/quickbuttons.php" \
	's/<\?php if \(\$output_method == \x27echo\x27\): \?><\?= \$output \?><\?php else: \?><\?php return \$output; \?><\?php endif; \?>/<?= \$output ?>/; s/ \* \@param string \$output_method [^\n]*\n/ * \@param string \$output_method Ignored: the buttons are output, and template_quickbuttons() returns them when asked.\n/; s/ \* \@return void\|string [^\n]*\n//' \
	'return $output'

converted=()

for file in "${files[@]}"; do
	t=$(basename -- "$file" .template.php)

	rm "$file"
	git rm -q --cached --ignore-unmatch "$file"

	while IFS= read -r path; do
		converted+=("$path")
	done < <(find "$theme/templates/$t" -name '*.php' ! -name index.php | sort)
done

run_php .dev/plates/tidy.php "${converted[@]}"
run_cmd php vendor/bin/php-cs-fixer fix --allow-risky=yes --quiet --config .php-cs-fixer.dist.php "${converted[@]}"

log "converted ${#files[@]} templates into ${#converted[@]} files"
