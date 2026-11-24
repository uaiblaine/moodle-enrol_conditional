# L3: can_delete_instance() is true for everybody.
s#return has_capability\('enrol/conditional:config', \$context\);#return true;# or die "l3: pattern did not match\n";
