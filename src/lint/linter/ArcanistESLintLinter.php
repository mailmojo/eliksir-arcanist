<?php

final class ArcanistESLintLinter extends ArcanistExternalLinter {

    protected $eslintenv;
    protected $eslintconfig;

    public function __construct() {
      // This sets the min supported version of this
      // linter. If you have set it in your .arcconfig or
      // .arclint file it will overwrite this. However if
      // the value is set lower it will cause the linter
      // to break.
      $this->setVersionRequirement('>=v1.0.0');
    }

    public function getInfoName() {
        return 'ESLint';
    }

    public function getInfoURI() {
        return 'https://www.eslint.org';
    }

    public function getInfoDescription() {
        return pht('ESLint is a linter for JavaScript source files.');
    }

    public function getVersion() {
        $output = exec('eslint_d --version');

        if (strpos($output, 'command not found') !== false) {
            return false;
        }

        return $output;
    }

    public function getLinterName() {
        return 'ESLINT';
    }

    public function getLinterConfigurationName() {
        return 'eslint';
    }

    public function getDefaultBinary() {
        return 'eslint_d';
    }

    public function getInstallInstructions() {
        return pht('Install ESLint using `%s`.', 'npm install -g eslint');
    }

    public function getUpgradeInstructions() {
        return pht('Upgrade ESLint using `%s`.', 'npm update -g eslint');
    }

    protected function getMandatoryFlags() {
        $options = array();

        $options[] = '--format=stylish';

        if ($this->eslintenv) {
            $options[] = '--env='.$this->eslintenv;
        }

        if ($this->eslintconfig) {
            $options[] = '--config='.$this->eslintconfig;
        }

        return $options;
    }

    public function getLinterConfigurationOptions() {
          $options = array(
              'eslint.eslintenv' => array(
                  'type' => 'optional string',
                  'help' => pht('enables specific environments.'),
              ),
              'eslint.eslintconfig' => array(
                  'type' => 'optional string',
                  'help' => pht('config file to use the default is .eslint.'),
              ),
          );

          return $options + parent::getLinterConfigurationOptions();
      }

      public function setLinterConfigurationValue($key, $value) {

          switch ($key) {
              case 'eslint.eslintenv':
                  $this->eslintenv = $value;
                  return;
              case 'eslint.eslintconfig':
                  $this->eslintconfig = $value;
                  return;
          }

          return parent::setLinterConfigurationValue($key, $value);
      }

    protected function canCustomizeLintSeverities() {
        return true;
    }

    protected function parseLinterOutput($path, $err, $stdout, $stderr) {
        $lines = phutil_split_lines($stdout, false);

        $messages = array();
        foreach ($lines as $line) {
            // Clean up nasty ESLint output
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
