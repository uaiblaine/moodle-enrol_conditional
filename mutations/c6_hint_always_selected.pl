# C6: the refusal always carries the hint, whatever the site setting says.
s#return get_config\('enrol_conditional', 'showhint'\) \? self::REFUSED_HINT : self::REFUSED;#return self::REFUSED_HINT;# or die "c6: pattern did not match\n";
