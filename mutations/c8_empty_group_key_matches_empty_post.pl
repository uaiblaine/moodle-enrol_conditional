# C8: a group with an empty key matches an empty posted key (both empty-key guards of find_group() removed).
s#\n        if \(\$key === ''\) \{\n            return null;\n        \}\n#\n# or die "c8a: pattern did not match\n";
s#\$groupkey !== '' && hash_equals#hash_equals# or die "c8b: pattern did not match\n";
