# H1: add_instance() stores a tree without validating its configuration and reach.
s#\n        if \(!empty\(\$fields\) && array_key_exists\(self::FIELD_CONDITIONS, \$fields\)\) \{\n            \$this->require_valid_conditions\(\(string\) \$fields\[self::FIELD_CONDITIONS\], null, \(int\) \$course->id\);\n        \}\n#\n# or die "h1: pattern did not match\n";
