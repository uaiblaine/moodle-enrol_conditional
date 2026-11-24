# M4: the key form's access check no longer asks can_self_enrol() (the instance, the dates, the conditions).
s#if \(\$canselfenrol !== true\) \{#if (false) {# or die "m4: pattern did not match\n";
