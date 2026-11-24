# G10: enrol_user enrols without handing the posted key to enrol_conditional().
s#\$plugin->enrol_conditional\(\$instance, \(object\) \['enrolpassword' => \$params\['password'\]\]\);#\$plugin->enrol_conditional(\$instance, (object) []);# or die "g10: pattern did not match\n";
