# B10: the capacity lock is never released.
s#\$lock->release\(\);\n        \}\n    \}(\n\n[^\n]*Override[^\n]*\n    public function enrol_page_hook)#\n        }\n    }$1# or die "b10: pattern did not match\n";
