# R5: a suspension record is restored for an enrolment that is no longer suspended.
s#if \(\$data->action === enforcer::STATE_SUSPEND && \(int\) \$ue->status !== ENROL_USER_SUSPENDED\) \{#if (false) {# or die "r5: pattern did not match\n";
