# S2: the bulk edit changes the enrolments of other methods of the selected users.
s#fn\(stdClass \$enrolment\): bool => \$enrolment->enrolmentinstance->enrol === \$name#fn(stdClass \$enrolment): bool => true# or die "s2: pattern did not match\n";
