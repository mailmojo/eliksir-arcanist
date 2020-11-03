<?php

/**
 * A linter for CSS/SCSS files using the `stylelint` tool.
 *
 * This linter uses [[https://stylelint.io/] | stylelint] to detect errors and potential problems in
 * CSS/SCSS code.
 */
final class ArcanistStyleLintLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return pht('CSS/SCSS');
  }

  public function getInfoURI() {
    return 'https://stylelint.io/';
  }

  public function getInfoDescription() {
    return pht(
      'Use the `stylelint` tool to detect errors in CSS/SCSS source files.');
  }

  public function getLinterName() {
    return 'stylelint';
  }

  public function getLinterConfigurationName() {
    return 'stylelint';
  }

  public function getDefaultBinary() {
    return 'stylelint';
  }

  protected function getMandatoryFlags() {
    return array(
       '--no-color',
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
    return pht('Install stylelint using `%s`.', 'npm install -g stylelint');
  }

  /**
   * Parse output from `stylelint`.
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
      $parts = explode(' ', trim($clean_line));

      if (isset($parts[1]) &&
          ($parts[1] === '✖' || $parts[1] === '⚠')) {
        $severity = $parts[1] === '✖' ?
            ArcanistLintSeverity::SEVERITY_ERROR :
            ArcanistLintSeverity::SEVERITY_WARNING;

        list($line, $char) = explode(':', $parts[0]);
        $rule = array_pop($parts);

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($line);
        $message->setChar($char);
        $message->setCode($this->getLinterName());
        $message->setName($this->getLinterName());
        $message->setDescription(implode(' ', $parts) . " [{$rule}]");
        $message->setSeverity($severity);

        $messages[] = $message;
      }
    }

    return $messages;
  }
}
