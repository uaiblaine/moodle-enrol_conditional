# P1: the "Enrol me" button enrols on a GET without the session key (a cross-site request enrols the user).
s#optional_param\('action', null, PARAM_TEXT\) === 'enrol' && confirm_sesskey\(\)#optional_param('action', null, PARAM_TEXT) === 'enrol'# or die "p1: pattern did not match\n";
