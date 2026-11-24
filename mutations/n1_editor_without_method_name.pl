# N1: the condition editor is given no enrolment method name.
s#\$this->get_name\(\),\n                \(int\) \(\$instance->id \?\? 0\),#'',\n                (int) (\$instance->id ?? 0),# or die "n1: pattern did not match\n";
