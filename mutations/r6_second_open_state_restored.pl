# R6: a second open state record is restored for an enrolment that already has one.
s#\n        if \(\$DB->record_exists_select\(enforcer::TABLE, 'userenrolmentid = \? AND timeclosed IS NULL', \[\$ue->id\]\)\) \{\n            return;\n        \}\n#\n# or die "r6: pattern did not match\n";
