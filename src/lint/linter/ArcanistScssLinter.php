<?php

/**
 * A linter for SCSS files.
 *
 * This linter uses [[https://github.com/causes/scss-lint | scss-lint]]
 * to detect errors and potential problems in [[http://sass-lang.com | SCSS]] code.
 */
final class ArcanistScssLinter extends ArcanistExternalLinter {

  const LINT_WARNING          = 1;
  const LINT_SYNTAX_ERROR     = 2;
  const LINT_USAGE_ERROR      = 64;
  const LINT_FILE_ERROR       = 66;
  const LINT_UNEXPECTED_ERROR = 70;
  const LINT_CONFIG_ERROR     = 78;

  public function getInfoName() {
    return pht('SASS');
  }

  public function getInfoURI() {
    return 'http://sass-lang.com/';
  }

  public function getInfoDescription() {
    return pht(
      'Use the `scss-lint` tool to detect errors in SASS source files.');
  }

  public function getLinterName() {
    return 'SCSS-LINT';
  }

  public function getLinterConfigurationName() {
    return 'scss-lint';
  }

  public function getLinterConfigurationOptions() {
    return parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getLintNameMap() {
    return array(
      self::LINT_WARNING          => pht('Warning'),
      self::LINT_SYNTAX_ERROR     => pht('Syntax Error'),
      self::LINT_USAGE_ERROR      => pht('Usage Error'),
      self::LINT_FILE_ERROR       => pht('File Error'),
      self::LINT_UNEXPECTED_ERROR => pht('Unexpected Error'),
      self::LINT_CONFIG_ERROR      => pht('Configuration Error'),
    );
  }

  public function getDefaultBinary() {
    return 'scss-lint';
  }

  public function getVersion() {
    list($stdout) = execx('%C --version', $this->getExecutableCommand());

    $matches = array();
    $regex = '/^scss-lint (?P<version>\d+\.\d+\.\d+)\b/';
    if (preg_match($regex, $stdout, $matches)) {
      $version = $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install scss-lint using `gem install scss-lint`.');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function getLintSeverityMap () {
    return array(
      'E' => ArcanistLintSeverity::SEVERITY_ERROR,
      'W' => ArcanistLintSeverity::SEVERITY_WARNING
    );
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);

    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      $match = preg_match(
        '%^(?P<path>[\w/.]+):(?P<line>\d+) '.
        '\[(?P<level>[EW])\] (?P<description>.+)$%',
        $line,
        $matches);

      if ($match) {
        if ($matches['level'] == 'W') {
          list($name, $description) = explode(': ', $matches['description']);
        }
        else {
          $name = $this->getLintMessageName($err);
          $description = $matches['description'];
        }

        $message = new ArcanistLintMessage();
        $message->setPath($matches['path']);
        $message->setLine($matches['line']);
        $message->setCode($this->getLintMessageFullCode($err));
        $message->setSeverity($this->getLintMessageSeverity($matches['level']));
        $message->setName($name);
        $message->setDescription($description);

        $messages[] = $message;
      }
    }

    if ($err && !$messages) {
      return false;
    }

    return $messages;
  }

}

