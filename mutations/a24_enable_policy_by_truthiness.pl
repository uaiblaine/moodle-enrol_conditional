# A24: enabling an instance applies the password policy only to a key that is truthy.
s#if \(enrolment_key::is_required\(\$instance\) && \$this->get_config\('usepasswordpolicy'\)\) \{#if (!empty(\$instance->password) && \$this->get_config('usepasswordpolicy')) {# or die "a24: pattern did not match\n";
