# A12: is_required() reads the key by truthiness, so a stored '0' is no key.
s#return self::is_key\(\(string\) \(\$instance->password \?\? ''\)\);#return !empty(\$instance->password);# or die "a12: pattern did not match\n";
