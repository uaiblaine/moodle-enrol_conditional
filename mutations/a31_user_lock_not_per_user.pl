# A31: the lock is of the instance, not of the instance and the user, so one user waits for another.
s#'enrol_' \. \$instance->id \. '_' \. \$userid, 10#'enrol_' . \$instance->id, 10# or die "a31: pattern did not match\n";
