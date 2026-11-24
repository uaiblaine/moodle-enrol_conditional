# A28: the one definition of a posted key reads it by truthiness.
s#return \$key !== '';#return (bool) \$key;# or die "a28: pattern did not match\n";
