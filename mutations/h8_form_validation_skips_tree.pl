# H8: the edit form's validation does not validate the tree.
s#if \(array_key_exists\(self::FIELD_CONDITIONS, \$data\)\) \{\n            \$message =#if (false) {\n            \$message =# or die "h8: pattern did not match\n";
