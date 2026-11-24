# L8: allow_manage() refuses, so nobody can change the period or status of these enrolments.
s#tweak period and status\.\n        return true;#tweak period and status.\n        return false;# or die "l8: pattern did not match\n";
