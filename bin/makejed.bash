#!/bin/bash

# Add xampp bin directory to get the right version of PHP
cd "$(dirname "$0")"
cd ../../../../../../bin
bin_dir="$(pwd)"
PATH=$bin_dir:$PATH
echo $PATH
#php --version

cd "$(dirname "$0")"
cd ../languages

wp i18n make-json .