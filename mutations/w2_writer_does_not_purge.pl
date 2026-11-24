# W2: the tree writer leaves the cached map of observed events as it was.
s#create_from_record\(\$current\)->trigger\(\);\n        \$this->purge_event_map\(\);\n#create_from_record(\$current)->trigger();\n#
    or die "w2: pattern did not match\n";
