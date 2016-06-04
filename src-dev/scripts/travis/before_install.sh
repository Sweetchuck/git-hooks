#!/usr/bin/env bash

if [[ "${TRAVIS_OS_NAME}" == 'osx' ]]; then
  echo "Here's the OSX environment:"
  sw_vers
  brew --version

  echo 'Updating brew...'
  brew update

  if [[ "${_PHP}" == 'hhvm' ]]; then
      echo 'Adding brew HHVM dependencies...'
      brew tap hhvm/hhvm
  else
      echo 'Adding brew PHP dependencies...'
      brew tap homebrew/dupes
      brew tap homebrew/versions
      brew tap homebrew/homebrew-php

      src-dev/scripts/osx.homebrew-install.sh "$_PHP"
  fi

  test -d "$HOME/bin" || mkdir "$HOME/bin"
  export PATH="$HOME/bin:$PATH"

  curl -s http://getcomposer.org/installer | php
  ln -s "$(pwd)/composer.phar" "$HOME/bin/composer"
fi
