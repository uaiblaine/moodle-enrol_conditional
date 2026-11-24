# W1: the tree writer no longer checks that the row is an instance of this method.
s#\$DB->get_record\('enrol', \['id' => \$instance->id \?\? 0, 'enrol' => 'conditional'\]\)#\$DB->get_record('enrol', ['id' => \$instance->id ?? 0])#
    or die "w1: pattern did not match\n";
