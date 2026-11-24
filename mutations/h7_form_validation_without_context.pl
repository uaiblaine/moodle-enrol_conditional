# H7: the edit form's validation passes no context to validate_conditions().
s#\$this->validate_conditions\(\(string\) \$data\[self::FIELD_CONDITIONS\], \$instance, \$context\)#\$this->validate_conditions((string) \$data[self::FIELD_CONDITIONS], \$instance, null)# or die "h7: pattern did not match\n";
