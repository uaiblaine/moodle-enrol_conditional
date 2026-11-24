# A17: without a limit, the enrolment does not re-read whether the user is enrolled already.
s#\n            if \(\$DB->record_exists\('user_enrolments', \['enrolid' => \$instance->id, 'userid' => \$userid\]\)\) \{\n                return false;\n            \}\n(            \$this->enrol_user\()#\n$1# or die "a17: pattern did not match\n";
