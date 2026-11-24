# L4: can_hide_show_instance() no longer needs enrol/conditional:config.
s#\n        if \(!has_capability\('enrol/conditional:config', \$context\)\) \{\n            return false;\n        \}\n#\n# or die "l4: pattern did not match\n";
