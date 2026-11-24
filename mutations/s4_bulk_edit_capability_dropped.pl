# S4: the bulk edit does not check enrol/conditional:manage when it runs.
s#\n        if \(!has_capability\("enrol/conditional:manage", \$manager->get_context\(\)\)\) \{\n            return false;\n        \}\n#\n# or die "s4: pattern did not match\n";
