#!/bin/bash

# Add an optional statement to see that this is running in Travis CI.
echo "running scripts/travis/before_script.sh"

set -e $DRUPAL_TI_DEBUG

# Ensure the module is linked into the code base and enabled.
# Note: This function is re-entrant.
drupal_ti_ensure_module_linked

# Update composer
cd "$DRUPAL_TI_DRUPAL_DIR"
composer config repositories.foo git https://github.com/digicol/dcx-sdk-php
composer require digicol/dcx-sdk-php

composer update
