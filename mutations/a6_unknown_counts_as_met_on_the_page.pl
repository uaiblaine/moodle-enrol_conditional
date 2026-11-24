# A6: get_self_enrol_status() explains only a result that is not met for sure, so an unknown one is offered.
s#if \(\$result !== null && !\$result->is_met\(\)\) \{\n                return \$this->render_condition_failures#if (\$result !== null && \$result->is_not_met()) {\n                return \$this->render_condition_failures# or die "a6: pattern did not match\n";
