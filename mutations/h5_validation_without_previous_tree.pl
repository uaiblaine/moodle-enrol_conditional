# H5: the stored tree is not passed to validate(), so a node left as it is gets the reach check again.
s#\$tree->validate\(\$allowed, \$context, \$previous\)#\$tree->validate(\$allowed, \$context, null)# or die "h5: pattern did not match\n";
