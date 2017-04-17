<?php

/**
 * Very basic 'pytest' run in Docker unit test engine wrapper.
 * Based on the PyTestTestEngine included in arcanist.
 */
final class DockerizedPytestTestEngine extends ArcanistUnitTestEngine {

    private $projectRoot;

    public function run() {
        $working_copy = $this->getWorkingCopy();
        $this->projectRoot = $working_copy->getProjectRoot();

        $junit_file = 'junit.xml';
        $junit_nativepath = $this->projectRoot . "/$junit_file";
        $cover_file = 'coverage.xml';
        $cover_nativepath = $this->projectRoot . "/$cover_file";

        $future = $this->buildTestFuture($junit_file);
        $future->setCWD($this->projectRoot);
        list($err, $stdout, $stderr) = $future->resolve();

        if (!Filesystem::pathExists($junit_nativepath)) {
            throw new CommandException(
                pht('Command failed with error #%s!', $err),
                $future->getCommand(),
                $err,
                $stdout,
                $stderr);
        }

        // TODO: Limiting reporting to $this->getPaths() might speed up reporting somewhat
        $future = new ExecFuture('./bin/coverage-wrapper.sh xml -o %s', $cover_file);
        $future->setCWD($this->projectRoot);
        $future->resolvex();

        $result = $this->parseTestResults($junit_nativepath, $cover_nativepath);
        unlink($junit_nativepath);
        unlink($cover_nativepath);
        return $result;
    }

    public function buildTestFuture($junit_tmp) {
        /*
         * When coverage is enabled (always for us), this is executed inside the
         * container, hence the direct pytest call here.
         */
        $cmd_line = csprintf('pytest --junit-xml=%s', $junit_tmp);

        if ($this->getEnableCoverage() !== false) {
            $cmd_line = csprintf(
                './bin/coverage-wrapper.sh run -m %C',
                $cmd_line);
        }

        return new ExecFuture('%C', $cmd_line);
    }

    public function parseTestResults($junit_tmp, $cover_tmp) {
        $parser = new ArcanistXUnitTestResultParser();
        $results = $parser->parseTestResults(
            Filesystem::readFile($junit_tmp));

        if ($this->getEnableCoverage() !== false) {
            $coverage_report = $this->readCoverage($cover_tmp);
            foreach ($results as $result) {
                $result->setCoverage($coverage_report);
            }
        }

        return $results;
    }

    public function readCoverage($path) {
        $coverage_data = Filesystem::readFile($path);
        if (empty($coverage_data)) {
            return array();
        }

        $coverage_dom = new DOMDocument();
        $coverage_dom->loadXML($coverage_data);

        $paths = $this->getPaths();
        $reports = array();
        $classes = $coverage_dom->getElementsByTagName('class');

        foreach ($classes as $class) {
            // filename is actually python module path with ".py" at the end,
            // e.g.: tornado.web.py
            $relative_path = explode('.', $class->getAttribute('filename'));
            array_pop($relative_path);
            $relative_path = implode('/', $relative_path);

            // first we check if the path is a directory (a Python package), if it is
            // set relative and absolute paths to have __init__.py at the end.
            $absolute_path = $this->projectRoot . '/' . $relative_path;
            if (is_dir($absolute_path)) {
                $relative_path .= '/__init__.py';
                $absolute_path .= '/__init__.py';
            }

            // then we check if the path with ".py" at the end is file (a Python
            // submodule), if it is - set relative and absolute paths to have
            // ".py" at the end.
            if (is_file($absolute_path.'.py')) {
                $relative_path .= '.py';
                $absolute_path .= '.py';
            }

            if (!file_exists($absolute_path)) {
                continue;
            }

            if (!in_array($relative_path, $paths)) {
                continue;
            }

            // get total line count in file
            $line_count = count(file($absolute_path));

            $coverage = '';
            $start_line = 1;
            $lines = $class->getElementsByTagName('line');
            for ($ii = 0; $ii < $lines->length; $ii++) {
                $line = $lines->item($ii);

                $next_line = (int)$line->getAttribute('number');
                for ($start_line; $start_line < $next_line; $start_line++) {
                    $coverage .= 'N';
                }

                if ((int)$line->getAttribute('hits') == 0) {
                    $coverage .= 'U';
                } else if ((int)$line->getAttribute('hits') > 0) {
                    $coverage .= 'C';
                }

                $start_line++;
            }

            if ($start_line < $line_count) {
                foreach (range($start_line, $line_count) as $line_num) {
                    $coverage .= 'N';
                }
            }

            $reports[$relative_path] = $coverage;
        }

        return $reports;
    }
}
