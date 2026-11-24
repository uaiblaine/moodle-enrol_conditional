# N2: the condition editor is given instance id 0, whatever the instance.
s#\(int\) \(\$instance->id \?\? 0\),#0,# or die "n2: pattern did not match\n";
