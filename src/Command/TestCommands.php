<?php

namespace DkanTools\Command;

use DkanTools\Util\Util;
use Symfony\Component\Console\Input\InputOption;
use Robo\ResultData;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class TestCommands extends \Robo\Tasks
{
    
    /**
     * Initialize test folders and install dependencies in directory.
     */
    private function testInstallDependencies($dir) {
        if (!file_exists($dir . '/vendor')) {
            $this->io()->section('Installing test dependencies in ' . $dir);
            $this->taskExec('composer install --prefer-source --no-interaction')
                ->dir($dir)
                ->run();
        }
    }

    /**
     * Initialize test subdirectories
     */
    private function testInitTestDirs($dir) {
        if (!file_exists($dir . '/assets')) {
            $this->io()->section('Creating test subdirectories in ' . $dir);
            $this->_mkdir($dir . '/assets/junit');
        }
    }

    /**
     * Establish links from a test environment to an environment with installed
     * test dependencies.
     */
    private function testLink($src_dir, $dest_dir) {
        $this->io()->section('Linking test environment ' . $dest_dir . ' to ' . $src_dir);
        $this->_mkdir($dest_dir . '/bin');
        $this->_symlink('../../../' . $src_dir . '/bin/behat', $dest_dir . '/bin/behat');
        $this->_symlink('../../../' . $src_dir . '/bin/phpunit', $dest_dir . '/bin/phpunit');
        $this->_symlink('../../' . $src_dir . '/vendor', $dest_dir . '/vendor');
    }
    
    /**
     * Initialize test folders and install dependencies for running tests.
     *
     * Initialize test folders and install dependencies for running tests. This
     * command will run composer install, and create an "assets" folder under
     * "dkan/test" for output. Usually this command does not need to be run on
     * its own as all other test commands run it first.
     */
    public function testInit()
    {
        $this->testInstallDependencies('dkan/test');
        $this->testInitTestDirs('dkan/test');
        if (is_dir('src/test')) {
            $this->testInitTestDirs('src/test');
            $this->testLink('dkan/test', 'src/test');
        }
    }

    /**
     * For each file in the array $paths, make sure it exists.  If not, throw an
     * Exception.
     */
    private function _ensureFilesExist(array $paths, $message) {
        foreach ($paths as $path) {
            if (! file_exists($path)) {
                $this->io()->error("${message} ${path} is missing.");
                throw new \Exception("{$path} is missing.");
            }
        }
    }

    /**
     * Helper function to run Behat tests in a particular directory.
     * 
     * @param string $dir test directory
     * @param string $suite name of the test suite to run
     * @param array $args additional arguments to pass to behat.
     */
    private function _testBehat($dir, $suite, array $args)
    {
        $files = array($dir . '/behat.yml', $dir . '/behat.docker.yml');
        $this->_ensureFilesExist($files, 'Behat config file');
        $this->testInit();
        $behatExec = $this->taskExec('bin/behat')
            ->dir($dir)
            ->arg('--colors')
            ->arg('--suite=' . $suite)
            ->arg('--format=pretty')
            ->arg('--out=std')
            ->arg('--format=junit')
            ->arg('--out=assets/junit')
            ->arg('--config=behat.docker.yml');

        foreach ($args as $arg) {
            $behatExec->arg($arg);
        }
        return $behatExec->run();
    }

    /**
     * Runs DKAN core Behat tests.
     *
     * Runs DKAN core Behat tests. Pass any additional behat options as
     * arguments. For example:
     *
     * dktl test:behat --name="Datastore API"
     *
     * or
     *
     * dktl test:behat features/workflow.feature
     *
     * @param array $args  Arguments to append to behat command.
     */
    public function testBehat(array $args)
    {
        return $this->_testBehat('dkan/test', 'dkan', $args);
    }

    /**
     * Runs custom Behat tests.
     *
     * Runs custom Behat tests. Pass any additional behat options as
     * arguments. For example:
     *
     * dktl test:behat-custom --name="Datastore API"
     *
     * or
     *
     * dktl test:behat-custom features/workflow.feature
     *
     * @param array $args  Arguments to append to behat command.
     */
    public function testBehatCustom(array $args)
    {
        return $this->_testBehat('src/test', 'custom', $args);
    }

    /**
     * Helper function to run PHPUnit tests in a particular directory.
     * 
     * @param string $dir test directory
     * @param array $args additional arguments to pass to PHPUnit.
     */
    public function _testPhpunit($dir, array $args)
    {
        $files = array($dir . '/phpunit/phpunit.xml');
        $this->_ensureFilesExist($files, 'PhpUnit config file');
        $this->testInit();
        $phpunitExec = $this->taskExec('bin/phpunit --verbose')
            ->dir($dir)
            ->arg('--configuration=phpunit');

        foreach ($args as $arg) {
            $phpunitExec->arg($arg);
        }
        return $phpunitExec->run();
    }

    /**
     * Runs DKAN core PhpUnit tests.
     *
     * dktl test:phpunit --testsuite="DKAN Harvest Test Suite"
     *
     * @see https://phpunit.de/manual/6.5/en/textui.html
     *
     * @param array $args  Arguments to append to full phpunit command.
     */
    public function testPhpunit(array $args)
    {
        return $this->_testPhpunit('dkan/test', $args);
    }

    /**
     * Runs custom PhpUnit tests.
     *
     * dktl test:phpunit-custom --testsuite="DKAN Harvest Test Suite"
     *
     * @see https://phpunit.de/manual/6.5/en/textui.html
     *
     * @param array $args  Arguments to append to full phpunit command.
     */
    public function testPhpunitCustom(array $args)
    {
        return $this->_testPhpunit('src/test', $args);
    }

    public function testCypress()
    {
        $proj_dir = Util::getProjectDirectory();
        $this->_exec("npm install cypress");
        $this->_exec("CYPRESS_baseUrl=http://web {$proj_dir}/node_modules/cypress/bin/cypress run");
    }

    private function getVendorCommand($binary_name) {
        $dktl_dir = Util::getDktlDirectory();
        return "{$dktl_dir}/vendor/bin/{$binary_name}";
    }

    /**
     * Proxy to phpcs.
     */
    public function phpcs(array $args) {
        $dktl_dir = Util::getDktlDirectory();

        $phpcs_command = $this->getVendorCommand("phpcs");

        $task = $this->taskExec("{$phpcs_command} --config-set installed_paths {$dktl_dir}/vendor/drupal/coder/coder_sniffer");
        $task->run();

        $task = $this->taskExec($phpcs_command);
        foreach ($args as $arg) {
            $task->arg($arg);
        }
        $task->run();
    }

    /**
     * Proxy to phpcbf.
     */
    public function phpcbf(array $args) {
        $phpcbf_command = $this->getVendorCommand("phpcbf");

        $task = $this->taskExec($phpcbf_command);
        foreach ($args as $arg) {
            $task->arg($arg);
        }
        $task->run();
    }

    /**
     * Preconfigured linting for paths inside of the repo.
     */
    public function testLint(array $paths) {
        $dktl_dir = Util::getDktlDirectory();
        $project_dir = Util::getProjectDirectory();

        $phpcs_command = $this->getVendorCommand("phpcs");

        $task = $this->taskExec("{$phpcs_command} --config-set installed_paths {$dktl_dir}/vendor/drupal/coder/coder_sniffer");
        $task->run();

        $task = $this->taskExec("{$phpcs_command} --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme,info");

        foreach ($paths as $path) {
            $task->arg("{$project_dir}/{$path}");
        }

        $task->run();
    }

    /**
     * Preconfigured lint fixing for paths inside of the repo.
     */
    public function testLintFix(array $paths) {
        $project_dir = Util::getProjectDirectory();

        $phpcbf_command = $this->getVendorCommand("phpcbf");

        $task = $this->taskExec("{$phpcbf_command} --standard=Drupal,DrupalPractice --extensions=php,module,inc,install,test,profile,theme,info");

        foreach ($paths as $path) {
            $task->arg("{$project_dir}/{$path}");
        }

        $task->run();
    }

    /**
     * Create QA users for each basic DKAN role.
     *
     * Running this command will create three users: sitemanager, editor, and
     * creator. They will be assigned the corresponding role and a password
     * equal to the username.
     *
     * @option $workflow Create workflow users as well.
     */
    public function testQaUsers($opts = ['workflow|w' => false]) {
        $users = [
            'sitemanager' => ['site manager'],
            'editor' => ['editor'],
            'creator' => ['content creator']
        ];
        if ($opts['workflow']) {
            if ($this->hasWorkflow()) {
                $users += [
                    'contributor' => ['content creator', 'Workflow Contributor'],
                    'moderator' => ['editor' , 'Workflow Moderator'],
                    'supervisor' => ['site manager', 'Workflow Supervisor']
                ];
            }
            else {
                throw new \Exception('Workflow QA users requested, but dkan_workflow_permissions not enbled.');
            }
        }
        $stack = $this->taskExecStack()->stopOnFail()->dir('docroot');
        foreach($users as $user => $roles) {
            // Add stack of drush commands to create users and assign roles.
            $stack->exec("drush ucrt $user --mail={$user}@example.com --password={$user}");
            foreach($roles as $role) {
                $stack->exec("drush urol '{$role}' --name={$user}");
            }
        }
        $result = $stack->run();
        return $result;
    }

    /**
     * Use Drush to check if dkan_workflow_permissions module is enabled.
     */
    private function hasWorkflow() {
        $result = $this->taskExec('drush php-eval')
            ->arg('echo module_exists("dkan_workflow_permissions");')
            ->dir('docroot')
            ->printOutput(FALSE)
            ->run();
        if ($result->getExitCode() == 0) {
            return $result->getMessage();
        }
        else {
          throw new \Exception('Drush command failed; aborting');
        }
    }
}
