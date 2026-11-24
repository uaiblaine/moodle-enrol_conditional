# A20: the course upload accepts a key longer than the column.
s#core_text::strlen\(\(string\) \$enrolmentdata\['password'\]\) > 50#false# or die "a20: pattern did not match\n";
