#!/bin/bash

# Add xampp bin directory to get the right version of PHP
cd "$(dirname "$0")"
cd ../../../../../../../bin
bin_dir="$(pwd)"
PATH=$bin_dir:$PATH
#php --version

cd "$(dirname "$0")"
cd ../..
php bin/PHPDocumentor/phpDocumentor.phar --visibility="public,protected" -d ./includes/class_lib -t ../../../../phpdoc/reg_man_rc