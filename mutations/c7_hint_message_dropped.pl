# C7: a refusal with a hint is worded as one without.
s#if \(\$code === self::REFUSED_HINT\) \{#if (false) {# or die "c7: pattern did not match\n";
