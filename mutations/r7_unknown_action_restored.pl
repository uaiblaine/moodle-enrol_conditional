# R7: a state record with any action is restored, not only suspend and none.
s#\n        if \(!in_array\(\$data->action, \[enforcer::STATE_SUSPEND, enforcer::STATE_NONE\], true\)\) \{\n            return;\n        \}\n#\n# or die "r7: pattern did not match\n";
