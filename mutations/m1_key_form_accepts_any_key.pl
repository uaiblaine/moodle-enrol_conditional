# M1: the key form's validation never refuses a key.
s#if \(\$check !== enrolment_key::ACCEPTED\) \{\n            \$errors\['enrolpassword'\]#if (false) {\n            \$errors['enrolpassword']# or die "m1: pattern did not match\n";
