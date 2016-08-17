<?php

/**
 * A linter for SCSS files using the `sass-lint` tool.
 *
 * This linter uses [[https://github.com/sasstools/sass-lint] | sass-lint]
 * to detect errors and potential problems in [[http://sass-lang.com | SCSS]] code.
 */
final class ArcanistSassLintLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return pht('SASS');
  }

  public function getInfoURI() {
    return 'http://sass-lang.com/';
  }

  public function getInfoDescription() {
    return pht(
      'Use the `sass-lint` tool to detect errors in SASS source files.');
  }

  public function getLinterName() {
    return 'sass-lint';
  }

  public function getLinterConfigurationName() {
    return 'sass-lint';
  }

  public function getDefaultBinary() {
    return 'sass-lint';
  }

  protected function getMandatoryFlags() {
    return array(
      '--format=stylish',
      '--no-exit',
      '--verbose'
    );
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^(?P<version>\d+\.\d+(?:\.\d+)?)\b/', $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install sass-lint using `%s`.', 'npm install -g sass-lint');
  }

  /**
   * Parse output from `sass-lint`.
   *
   * The output will be in the `stylish` format, as popularized by other
   * linters like jshint and eslint.
   *
   * For each file with linter errors the first line will be the file path,
   * followed by one or more lines of errors. Each error line has the format:
   * <line>:<char> <severity> <message> <rule>
   *
   * The error lines for each file will be formatted as a table, meaning there
   * might be more than one space between each part of the error line to align
   * each part for all lines.
   */
  protected function parseLinterOutput ($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);

    $messages = array();
    foreach ($lines as $line) {
      $clean_line = $output = preg_replace('!\s+!', ' ', $line);
      $parts = explode(' ', ltrim($clean_line));

      if (isset($parts[1]) &&
          ($parts[1] === 'error' || $parts[1] === 'warning')) {
        $severity = $parts[1] === 'error' ?
            ArcanistLintSeverity::SEVERITY_ERROR :
            ArcanistLintSeverity::SEVERITY_WARNING;

        list($line, $char) = explode(':', $parts[0]);

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($line);
        $message->setChar($char);
        $message->setCode($this->getLinterName());
        $message->setName($this->getLinterName());
        $message->setDescription(implode(' ', $parts));
        $message->setSeverity($severity);

        $messages[] = $message;
      }
    }

    return $messages;
  }
}
