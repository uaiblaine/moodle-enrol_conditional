# H3: update_instance() validates the tree even when it is the stored one, blocking the other fields.
s#\} else if \(\$this->is_stored_tree\(\$data->\{self::FIELD_CONDITIONS\}, \$instance\)\) \{#} else if (false) {# or die "h3: pattern did not match\n";
