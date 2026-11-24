# L6: a disabled instance whose key breaks the password policy can be enabled.
s#if \(enrolment_key::is_required\(\$instance\) && \$this->get_config\('usepasswordpolicy'\)\) \{#if (false) {# or die "l6: pattern did not match\n";
