# S3: the bulk delete does not check enrol/conditional:unenrol when it runs.
s#\n        if \(!has_capability\("enrol/conditional:unenrol", \$manager->get_context\(\)\)\) \{\n            return false;\n        \}\n#\n# or die "s3: pattern did not match\n";
