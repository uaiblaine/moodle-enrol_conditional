# G8: enrol_user gives no warning for a wrong key: the enrolment is attempted and throws.
s#\n            if \(\$check !== enrolment_key::ACCEPTED\) \{\n                // The message is PARAM_TEXT: the hint character is escaped.\n                \$warnings\[\] = self::warning\([^\n]*\n                continue;\n            \}\n#\n# or die "g8: pattern did not match\n";
