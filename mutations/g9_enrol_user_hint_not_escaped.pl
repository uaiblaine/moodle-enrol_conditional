# G9: the message of a key warning is not escaped for PARAM_TEXT.
s#self::warning\(\$instance, \$check, s\(enrolment_key::refusal_message\(\$instance, \$check\)\)\);#self::warning(\$instance, \$check, enrolment_key::refusal_message(\$instance, \$check));# or die "g9: pattern did not match\n";
