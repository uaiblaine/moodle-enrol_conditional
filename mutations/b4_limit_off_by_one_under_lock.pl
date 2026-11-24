# B4: the count under the lock lets one more user in than the limit.
s#(count_records\('user_enrolments', \['enrolid' => \$instance->id\]\)) >= \(int\) \$instance->customint3#$1 > (int) \$instance->customint3# or die "b4: pattern did not match\n";
