# K2: the SQL reads the column as it is, so a stored NULL selects no mode (the PHP reading says "at enrolment only").
s#'COALESCE\(e\.' \. self::FIELD_CHECKMODES \. ', ' \. self::CHECK_ENROL \. '\)'#'e.' . self::FIELD_CHECKMODES# or die "k2: pattern did not match\n";
