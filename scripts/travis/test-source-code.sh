# remove xdebug to make php execute faster
phpenv config-rm xdebug.ini

# globally require drupal coder for code tests
composer global require drupal/coder symfony/yaml:^3.0

# run phpcs
phpcs --config-set installed_paths ~/.composer/vendor/drupal/coder/coder_sniffer
phpcs --standard=Drupal --report=summary -p . --ignore=tests,README.md,CHANGELOG.md
phpcs --standard=DrupalPractice --report=summary -p .

# JS ESLint checking
set -x
source ~/.nvm/nvm.sh
set +x
nvm install 6
npm install -g eslint
eslint .
