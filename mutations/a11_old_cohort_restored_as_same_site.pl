# A11: the converted cohort condition keeps the backup's cohort id even from another site (R4 covers the whole tree).
s#->for_restore\(\$samesite\)->to_json\(\);#->for_restore(true)->to_json();# or die "a11: pattern did not match\n";
