# F4: get_instance_info returns the whole row of the instance next to its allowlist.
s#\n        return \$result;\n#\n        return \$result + (array) \$instance;\n# or die "f4: pattern did not match\n";
