# F3: the key field answers with the stored enrolment key instead of the label of the field.
s#\$result\['enrolpassword'\] = \$info->requiredparam->enrolpassword;#\$result['enrolpassword'] = \$instance->password;# or die "f3: pattern did not match\n";
