# B5: the enrol page and the link count one place too many.
s#\$count >= \$instance->customint3#\$count > \$instance->customint3# or die "b5: pattern did not match\n";
