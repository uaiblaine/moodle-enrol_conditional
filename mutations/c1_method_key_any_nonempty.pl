# C1: any non-empty key is taken for the method key (the comparison with the stored key is gone).
s#if \(\$key !== '' && hash_equals\(\$stored, \$key\)\) \{#if (\$key !== '') {# or die "c1: pattern did not match\n";
