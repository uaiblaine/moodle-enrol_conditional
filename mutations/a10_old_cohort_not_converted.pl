# A10: a backup's customint5 (the old cohort restriction) is not turned into a condition on restore.
s#if \(\$oldcohort !== 0\) \{#if (false) {# or die "a10: pattern did not match\n";
