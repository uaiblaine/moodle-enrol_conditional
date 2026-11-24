# C2: an instance without a key stops accepting a missing key: the early acceptance is gone.
s#\n        if \(!self::is_required\(\$instance\)\) \{\n            return self::ACCEPTED;\n        \}\n#\n# or die "c2: pattern did not match\n";
