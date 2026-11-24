# K1: a stored NULL falls back to the site default in PHP instead of meaning "at enrolment only".
s#!property_exists\(\$instance, self::FIELD_CHECKMODES\)#!isset(\$instance->{self::FIELD_CHECKMODES})# or die "k1: pattern did not match\n";
