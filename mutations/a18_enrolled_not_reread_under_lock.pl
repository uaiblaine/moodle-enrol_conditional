# A18: with a limit, the enrolment does not re-read under the lock whether the user is enrolled already.
s#\n            if \(\$DB->record_exists\('user_enrolments', \['enrolid' => \$instance->id, 'userid' => \$userid\]\)\) \{\n                return false;\n            \}\n(            if \(\$DB->count_records)#\n$1# or die "a18: pattern did not match\n";
