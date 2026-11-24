# C5: the refusal never carries the hint, whatever the site setting says.
s#return get_config\('enrol_conditional', 'showhint'\) \? self::REFUSED_HINT : self::REFUSED;#return self::REFUSED;# or die "c5: pattern did not match\n";
